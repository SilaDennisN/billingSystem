<?php
require_once('../core/app.php');
/*
|--------------------------------------------------------------------------
| API CONSTANTS
|--------------------------------------------------------------------------
| NEVER expose this file publicly.
| Block access using .htaccess
*/

function decrypt_key($encrypted)
{
    $key = hash('sha256', APP_KEY);
    $iv  = substr(hash('sha256', APP_KEY), 0, 16);

    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}
/**
 * subscriptions/pay.php
 *
 * Handles two AJAX actions POSTed as JSON:
 *   action=initiate  → sends STK push, returns transaction_request_id
 *   action=verify    → checks payment status, marks invoice paid on success
 */

require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/mailer.php";
require_once __DIR__ . "/../config/config.php";                 // decrypt_key() + PESAFLUX_STK_URL/VERIFY_URL
require_once "../config/subscription_payment_config.php"; // SUB_PESAFLUX_API_KEY, SUB_PESAFLUX_EMAIL

/**
 * Load the logged-in user's own PesaFlux credentials.
 * Subscription billing is platform-level — no router context needed.
 * Falls back gracefully with a JSON error so the caller gets a clean message.
 */
function loadUserPesaflux(int $user_id, $pdo): bool
{
    $stmt = $pdo->prepare("
        SELECT api_key_encrypted, email
        FROM user_api_keys
        WHERE user_id = ? AND provider = 'pesaflux'
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row) {
        error_log("[SubPay] No PesaFlux API key found for user_id=$user_id");
        return false;
    }

    $decrypted = decrypt_key($row['api_key_encrypted']);
    if (!$decrypted) {
        error_log("[SubPay] API key decryption failed for user_id=$user_id");
        return false;
    }

    if (!defined('PESAFLUX_API_KEY')) define('PESAFLUX_API_KEY', $decrypted);
    if (!defined('PESAFLUX_EMAIL'))   define('PESAFLUX_EMAIL',   $row['email']);

    return true;
}

/**
 * Load Inovatech's own platform PesaFlux credentials (from config).
 * Used when the user is paying the platform — gateway activation fee.
 */
function loadPlatformPesaflux(): bool
{
    if (!defined('SUB_PESAFLUX_API_KEY') || !defined('SUB_PESAFLUX_EMAIL')) {
        error_log('[SubPay] Platform PesaFlux credentials not configured in subscription_payment_config.php');
        return false;
    }
    if (!defined('PESAFLUX_API_KEY')) define('PESAFLUX_API_KEY', SUB_PESAFLUX_API_KEY);
    if (!defined('PESAFLUX_EMAIL'))   define('PESAFLUX_EMAIL',   SUB_PESAFLUX_EMAIL);
    return true;
}

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$input   = json_decode(file_get_contents('php://input'), true);
$action  = $input['action'] ?? '';

/* ══════════════════════════════════════════════════════════════
   ACTION: initiate — send STK push
   Supports both platform invoices (invoice_id) and
   gateway invoices (gateway_invoice_id).
   ══════════════════════════════════════════════════════════════ */
if ($action === 'initiate') {

    $invoice_id    = (int)($input['invoice_id']         ?? 0);
    $gw_invoice_id = (int)($input['gateway_invoice_id'] ?? 0);
    $phone         = trim($input['phone'] ?? '');

    if (!$invoice_id && !$gw_invoice_id) {
        echo json_encode(['success' => false, 'message' => 'No invoice specified.']);
        exit;
    }

    // ── Normalise phone to 254XXXXXXXXX ──────────────────────
    $phone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phone, '0'))  $phone = '254' . substr($phone, 1);
    elseif (str_starts_with($phone, '+')) $phone = ltrim($phone, '+');
    if (!preg_match('/^2547\d{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number. Use 07XXXXXXXX format.']);
        exit;
    }

    // ── Fetch whichever invoice was passed ────────────────────
    if ($gw_invoice_id) {
        $stmt = $pdo->prepare("SELECT * FROM gateway_invoices WHERE id=? AND user_id=? AND status IN ('pending','overdue')");
        $stmt->execute([$gw_invoice_id, $user_id]);
        $invoice   = $stmt->fetch();
        $amount    = $invoice ? (int)ceil($invoice['amount']) : 0;
        $reference = 'GW-' . str_pad($gw_invoice_id, 5, '0', STR_PAD_LEFT);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM subscription_invoices WHERE id=? AND user_id=? AND status IN ('pending','overdue')");
        $stmt->execute([$invoice_id, $user_id]);
        $invoice   = $stmt->fetch();
        $amount    = $invoice ? (int)ceil($invoice['total_amount']) : 0;
        $reference = 'INV-' . str_pad($invoice_id, 5, '0', STR_PAD_LEFT);
    }

    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found or already paid.']);
        exit;
    }

    // ── Load the right PesaFlux credentials ──────────────────
    // Gateway invoice → Inovatech collects (platform keys)
    // Platform fee invoice → user's own keys
    $credLoaded = $gw_invoice_id
        ? loadPlatformPesaflux()
        : loadUserPesaflux($user_id, $pdo);

    if (!$credLoaded) {
        $msg = $gw_invoice_id
            ? 'Platform payment gateway not configured. Please contact support.'
            : 'Payment gateway not configured. Please add your PesaFlux API key in Settings.';
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // ── Fire STK push ─────────────────────────────────────────
    $payload = json_encode([
        'api_key'   => PESAFLUX_API_KEY,
        'email'     => PESAFLUX_EMAIL,
        'amount'    => $amount,
        'msisdn'    => $phone,
        'reference' => $reference,
    ]);

    $ch = curl_init(PESAFLUX_STK_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>30]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('[SubPay initiate] curl error: ' . curl_error($ch));
        curl_close($ch);
        echo json_encode(['success' => false, 'message' => 'Could not reach payment gateway. Try again.']);
        exit;
    }
    curl_close($ch);

    error_log('[SubPay initiate] raw response: ' . $response);
    $result = json_decode($response, true);

    if (empty($result['transaction_request_id'])) {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'STK push failed. Please try again.']);
        exit;
    }

    $txn_id = $result['transaction_request_id'];

    // ── Save pending payment record ───────────────────────────
    if ($gw_invoice_id) {
        $pdo->prepare("
            INSERT INTO gateway_payments
                (user_id, gateway_invoice_id, transaction_request_id, phone, amount, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ON DUPLICATE KEY UPDATE phone=VALUES(phone), status='pending', updated_at=NOW()
        ")->execute([$user_id, $gw_invoice_id, $txn_id, $phone, $amount]);
    } else {
        $pdo->prepare("
            INSERT INTO subscription_payments
                (user_id, invoice_id, transaction_request_id, phone, amount, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ON DUPLICATE KEY UPDATE phone=VALUES(phone), status='pending', updated_at=NOW()
        ")->execute([$user_id, $invoice_id, $txn_id, $phone, $amount]);
    }

    echo json_encode(['success'=>true, 'transaction_request_id'=>$txn_id, 'amount'=>$amount,
        'message'=>'STK push sent. Enter your M-Pesa PIN on your phone.']);
    exit;
}

/* ══════════════════════════════════════════════════════════════
   ACTION: verify — poll payment status
   ══════════════════════════════════════════════════════════════ */
if ($action === 'verify') {

    $txn_id        = trim($input['transaction_request_id'] ?? '');
    $invoice_id    = (int)($input['invoice_id']            ?? 0);
    $gw_invoice_id = (int)($input['gateway_invoice_id']    ?? 0);

    if (!$txn_id) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction ID.']);
        exit;
    }

    // ── Load the right credentials for verify ─────────────────
    $credLoaded = $gw_invoice_id
        ? loadPlatformPesaflux()
        : loadUserPesaflux($user_id, $pdo);

    if (!$credLoaded) {
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured.']);
        exit;
    }

    // ── Ask PesaFlux for status ───────────────────────────────
    $payload = json_encode([
        'api_key'                => PESAFLUX_API_KEY,
        'email'                  => PESAFLUX_EMAIL,
        'transaction_request_id' => $txn_id,
    ]);

    $ch = curl_init(PESAFLUX_VERIFY_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('[SubPay verify] curl error: ' . curl_error($ch));
        curl_close($ch);
        // Don't fail the poll on a network error — just tell JS to keep trying
        echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Network error, retrying...']);
        exit;
    }
    curl_close($ch);

    // ── Log the raw response so we can see exactly what PesaFlux returns ──
    error_log('[SubPay verify] txn=' . $txn_id . ' raw=' . $response);

    $result = json_decode($response, true);

    error_log('[SubPay verify v3] txn=' . $txn_id . ' raw=' . $response);

    $transactionStatus = strtolower(trim($result['TransactionStatus'] ?? ''));
    $transactionCode   = trim((string)($result['TransactionCode']   ?? ''));
    $receipt           = trim($result['TransactionReceipt'] ?? '');

    error_log('[SubPay verify v3] status=' . $transactionStatus . ' code=' . $transactionCode . ' receipt=' . $receipt);

    // ── PAID ──────────────────────────────────────────────────
    if ($transactionStatus === 'completed' || $transactionCode === '0') {

        $mpesa_code = ($receipt !== '' && $receipt !== 'N/A') ? $receipt : '';

        if ($gw_invoice_id) {
            // ── Gateway payment: activate gateway, set expiry ─────
            $stmt = $pdo->prepare("SELECT * FROM gateway_invoices WHERE id=? AND user_id=?");
            $stmt->execute([$gw_invoice_id, $user_id]);
            $gwInvoice = $stmt->fetch();

            if ($gwInvoice) {
                // Extend from current expiry if still active, otherwise from now
                // This ensures early renewals stack on top of remaining time
                $stmt2 = $pdo->prepare("SELECT gateway_expires_at FROM subscriptions WHERE user_id=?");
                $stmt2->execute([$user_id]);
                $currentExpiry = $stmt2->fetchColumn();
                $baseDate = ($currentExpiry && strtotime($currentExpiry) > time())
                    ? $currentExpiry
                    : 'now';

                $expiresAt = $gwInvoice['plan'] === 'yearly'
                    ? date('Y-m-d H:i:s', strtotime('+1 year', strtotime($baseDate)))
                    : date('Y-m-d H:i:s', strtotime('+1 month', strtotime($baseDate)));

                $pdo->prepare("
                    UPDATE gateway_invoices SET status='paid', paid_at=NOW(), updated_at=NOW()
                    WHERE id=?
                ")->execute([$gw_invoice_id]);

                $pdo->prepare("
                    UPDATE subscriptions
                    SET gateway_enabled=1, gateway_expires_at=?, updated_at=NOW()
                    WHERE user_id=?
                ")->execute([$expiresAt, $user_id]);

                $pdo->prepare("
                    UPDATE gateway_payments SET status='paid', mpesa_code=?, updated_at=NOW()
                    WHERE transaction_request_id=?
                ")->execute([$mpesa_code, $txn_id]);
            }

        } else {
            // ── Platform subscription payment ─────────────────────
            $pdo->prepare("
                UPDATE subscription_invoices SET status='paid', paid_at=NOW(), updated_at=NOW()
                WHERE id=? AND user_id=?
            ")->execute([$invoice_id, $user_id]);

            $pdo->prepare("
                UPDATE subscriptions SET status='active', updated_at=NOW() WHERE user_id=?
            ")->execute([$user_id]);

            $pdo->prepare("
                UPDATE subscription_payments SET status='paid', mpesa_code=?, updated_at=NOW()
                WHERE transaction_request_id=?
            ")->execute([$mpesa_code, $txn_id]);

            // Send confirmation email
            $stmt = $pdo->prepare("SELECT full_names, email FROM users WHERE user_id=?");
            $stmt->execute([$user_id]);
            $userRow = $stmt->fetch();
            $stmt = $pdo->prepare("SELECT * FROM subscription_invoices WHERE id=?");
            $stmt->execute([$invoice_id]);
            $invoiceRow = $stmt->fetch();
            if ($userRow && $invoiceRow) {
                send_payment_confirmation_email($userRow['email'], $userRow['full_names'], $invoiceRow, $mpesa_code);
            }
        }

        echo json_encode(['success'=>true, 'status'=>'paid', 'mpesa_code'=>$mpesa_code,
            'message'=>'Payment confirmed!']);
        exit;
    }

    // ── EXPLICITLY FAILED ─────────────────────────────────────
    $explicitFailures = ['failed', 'cancelled', 'canceled', 'rejected', 'timeout', 'expired'];
    if (in_array($transactionStatus, $explicitFailures, true)) {
        if ($gw_invoice_id) {
            $pdo->prepare("UPDATE gateway_payments SET status='failed', updated_at=NOW() WHERE transaction_request_id=?")
                ->execute([$txn_id]);
        } else {
            $pdo->prepare("UPDATE subscription_payments SET status='failed', updated_at=NOW() WHERE transaction_request_id=?")
                ->execute([$txn_id]);
        }
        echo json_encode(['success'=>false, 'status'=>'failed',
            'message' => $result['ResultDesc'] ?? 'Payment was cancelled or failed. Please try again.']);
        exit;
    }

    // ── PENDING ───────────────────────────────────────────────
    echo json_encode(['success'=>true, 'status'=>'pending', 'message'=>'Waiting for payment confirmation...']);
    exit;
}


/* ══════════════════════════════════════════════════════════════
   ACTION: test_gateway — send KES 1 STK push to user's own phone
   Uses the user's own PesaFlux keys — confirms their Till/Paybill works.
   ══════════════════════════════════════════════════════════════ */
if ($action === 'test_gateway') {

    $phone = trim($input['phone'] ?? '');
    $phone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phone, '0'))  $phone = '254' . substr($phone, 1);
    if (str_starts_with($phone, '+'))  $phone = ltrim($phone, '+');
    if (!preg_match('/^2547\d{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
        exit;
    }

    // Load this user's own PesaFlux credentials (KES 1 goes into their Till/Paybill)
    if (!loadUserPesaflux($user_id, $pdo)) {
        echo json_encode(['success' => false, 'message' => 'PesaFlux API key not found. Please add it in Settings.']);
        exit;
    }

    $payload = json_encode([
        'api_key'   => PESAFLUX_API_KEY,
        'email'     => PESAFLUX_EMAIL,
        'amount'    => 1,
        'msisdn'    => $phone,
        'reference' => 'TEST-GW-' . $user_id,
    ]);

    $ch = curl_init(PESAFLUX_STK_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>30]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('[TestGW] curl error: ' . curl_error($ch));
        curl_close($ch);
        echo json_encode(['success' => false, 'message' => 'Could not reach payment gateway. Try again.']);
        exit;
    }
    curl_close($ch);

    error_log('[TestGW initiate] user_id=' . $user_id . ' raw=' . $response);
    $result = json_decode($response, true);

    if (empty($result['transaction_request_id'])) {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'STK push failed. Please try again.']);
        exit;
    }

    echo json_encode(['success' => true, 'transaction_request_id' => $result['transaction_request_id']]);
    exit;
}

/* ══════════════════════════════════════════════════════════════
   ACTION: verify_test — poll status of a test_gateway transaction
   ══════════════════════════════════════════════════════════════ */
if ($action === 'verify_test') {

    $txn_id = trim($input['transaction_request_id'] ?? '');
    if (!$txn_id) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction ID.']);
        exit;
    }

    if (!loadUserPesaflux($user_id, $pdo)) {
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured.']);
        exit;
    }

    $payload = json_encode([
        'api_key'                => PESAFLUX_API_KEY,
        'email'                  => PESAFLUX_EMAIL,
        'transaction_request_id' => $txn_id,
    ]);

    $ch = curl_init(PESAFLUX_VERIFY_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_TIMEOUT=>30]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Network error, retrying...']);
        exit;
    }
    curl_close($ch);

    error_log('[TestGW verify] txn=' . $txn_id . ' raw=' . $response);
    $result = json_decode($response, true);

    $transactionStatus = strtolower(trim($result['TransactionStatus'] ?? ''));
    $transactionCode   = trim($result['TransactionCode']   ?? '');
    $mpesaCode         = $result['TransactionReceipt']     ?? '';

    if ($transactionStatus === 'completed' || $transactionCode === '0') {
        echo json_encode(['success' => true, 'status' => 'paid', 'mpesa_code' => $mpesaCode]);
        exit;
    }

    $failedStatuses = ['failed','cancelled','canceled','rejected','timeout','expired'];
    if (in_array($transactionStatus, $failedStatuses)) {
        echo json_encode(['success' => true, 'status' => 'failed', 'message' => 'Payment was declined or cancelled.']);
        exit;
    }

    echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Waiting for payment confirmation...']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);