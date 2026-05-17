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
 * Handles four AJAX actions POSTed as JSON:
 *   action=initiate      → sends STK push for platform/gateway invoices
 *   action=verify        → checks payment status, marks invoice paid on success
 *   action=test_gateway  → sends KES 1 STK push using user's own gateway keys
 *   action=verify_test   → polls status of a test_gateway transaction
 */

require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/mailer.php";
require_once __DIR__ . "/../config/config.php";
require_once "../config/subscription_payment_config.php";

/**
 * Load the logged-in user's own gateway credentials (mpesa, pesaflux, or intasend).
 * Defines the relevant constants and returns the provider string, or false on failure.
 *
 * For M-Pesa:
 *   MPESA_SHORTCODE = head-office/business shortcode (used in Password hash + BusinessShortCode)
 *   MPESA_PARTYB    = money destination (till number for BuyGoods; same as shortcode for Paybill)
 *   MPESA_GATEWAY_TYPE = 'till' | 'paybill'  (needed to pick TransactionType)
 */
function loadUserProviderKeys(int $user_id, $pdo)
{
    $stmt = $pdo->prepare("
        SELECT k.*, s.gateway_type, s.gateway_account
        FROM user_api_keys k
        LEFT JOIN subscriptions s ON s.user_id = k.user_id
        WHERE k.user_id = ? AND k.provider IN ('mpesa','pesaflux','intasend')
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row) {
        error_log("[SubPay] No gateway API key found for user_id=$user_id");
        return false;
    }

    $provider = strtolower($row['provider']);

    // ── PesaFlux ──────────────────────────────────────────────
    if ($provider === 'pesaflux') {
        $decrypted = decrypt_key($row['api_key_encrypted']);
        if (!$decrypted) {
            error_log("[SubPay] PesaFlux key decryption failed for user_id=$user_id");
            return false;
        }
        if (!defined('PESAFLUX_API_KEY')) define('PESAFLUX_API_KEY', $decrypted);
        if (!defined('PESAFLUX_EMAIL'))   define('PESAFLUX_EMAIL',   $row['email']);
        return 'pesaflux';
    }

    // ── M-Pesa Daraja ─────────────────────────────────────────
    if ($provider === 'mpesa') {
        $ck = decrypt_key($row['consumer_key_encrypted']    ?? '');
        $cs = decrypt_key($row['consumer_secret_encrypted'] ?? '');
        $pk = decrypt_key($row['passkey_encrypted']         ?? '');
        $sc = $row['shortcode'] ?? '';

        // partyb is stored separately; fall back to shortcode for paybill users
        // who saved before this column existed.
        $pb = !empty($row['partyb']) ? $row['partyb'] : $sc;

        if (!$ck || !$cs || !$pk) {
            error_log("[SubPay] M-Pesa key decryption failed for user_id=$user_id");
            return false;
        }

        if (!defined('MPESA_CONSUMER_KEY'))    define('MPESA_CONSUMER_KEY',    $ck);
        if (!defined('MPESA_CONSUMER_SECRET')) define('MPESA_CONSUMER_SECRET', $cs);
        if (!defined('MPESA_PASSKEY'))         define('MPESA_PASSKEY',         $pk);
        if (!defined('MPESA_SHORTCODE'))       define('MPESA_SHORTCODE',       $sc);
        if (!defined('MPESA_PARTYB'))          define('MPESA_PARTYB',          $pb);

        // Store gateway_type so STK push can pick the right TransactionType
        $gwType = $row['gateway_type'] ?? 'till';
        if (!defined('MPESA_GATEWAY_TYPE'))    define('MPESA_GATEWAY_TYPE',    $gwType);
        if (!defined('MPESA_ACCOUNT_REF'))     define('MPESA_ACCOUNT_REF',     $row['gateway_account'] ?? '');

        return 'mpesa';
    }

    // ── IntaSend ──────────────────────────────────────────────
    if ($provider === 'intasend') {
        $pub = decrypt_key($row['public_key_encrypted']  ?? '');
        $sec = decrypt_key($row['secret_key_encrypted']  ?? '');
        if (!$pub || !$sec) {
            error_log("[SubPay] IntaSend key decryption failed for user_id=$user_id");
            return false;
        }
        if (!defined('INTASEND_PUBLIC_KEY')) define('INTASEND_PUBLIC_KEY', $pub);
        if (!defined('INTASEND_SECRET_KEY')) define('INTASEND_SECRET_KEY', $sec);
        return 'intasend';
    }

    return false;
}

// Backward-compat alias used in older code paths
function loadUserPesaflux(int $user_id, $pdo): bool
{
    return loadUserProviderKeys($user_id, $pdo) !== false;
}

/**
 * Load the platform's own PesaFlux credentials (for billing the user).
 */
function loadPlatformPesaflux(): bool
{
    if (!defined('SUB_PESAFLUX_API_KEY') || !defined('SUB_PESAFLUX_EMAIL')) {
        error_log('[SubPay] Platform PesaFlux credentials not configured.');
        return false;
    }
    if (!defined('PESAFLUX_API_KEY')) define('PESAFLUX_API_KEY', SUB_PESAFLUX_API_KEY);
    if (!defined('PESAFLUX_EMAIL'))   define('PESAFLUX_EMAIL',   SUB_PESAFLUX_EMAIL);
    return true;
}

// ── Helper: get a fresh M-Pesa Daraja access token ────────────
function mpesaAccessToken(string $consumerKey, string $consumerSecret, bool $sandbox = false): string
{
    $url = $sandbox
        ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    return $data['access_token'] ?? '';
}

// ── Helper: build M-Pesa STK payload ─────────────────────────
function mpesaStkPayload(
    string $shortcode,
    string $passkey,
    string $partyb,
    string $gwType,      // 'till' or 'paybill'
    int    $amount,
    string $phone,
    string $callbackUrl,
    string $accountRef,
    string $desc
): array {
    $timestamp = date('YmdHis');
    $password  = base64_encode($shortcode . $passkey . $timestamp);

    $txnType = ($gwType === 'till') ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline';

    return [
        'BusinessShortCode' => $shortcode,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => $txnType,
        'Amount'            => $amount,
        'PartyA'            => $phone,
        'PartyB'            => $partyb,       // till number OR paybill number
        'PhoneNumber'       => $phone,
        'CallBackURL'       => $callbackUrl,
        'AccountReference'  => $accountRef,
        'TransactionDesc'   => $desc,
    ];
}

// ── Helper: build M-Pesa STK query payload ───────────────────
function mpesaStkQueryPayload(string $shortcode, string $passkey, string $checkoutId): array
{
    $timestamp = date('YmdHis');
    $password  = base64_encode($shortcode . $passkey . $timestamp);
    return [
        'BusinessShortCode' => $shortcode,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'CheckoutRequestID' => $checkoutId,
    ];
}

// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$input   = json_decode(file_get_contents('php://input'), true);
$action  = $input['action'] ?? '';


/* ══════════════════════════════════════════════════════════════
   ACTION: initiate — send STK push for platform/gateway invoice
   Supports both subscription_invoices (invoice_id) and
   gateway_invoices (gateway_invoice_id). Always uses platform
   PesaFlux credentials since this is money going TO the platform.
   ══════════════════════════════════════════════════════════════ */
if ($action === 'initiate') {

    $invoice_id    = (int)($input['invoice_id']         ?? 0);
    $gw_invoice_id = (int)($input['gateway_invoice_id'] ?? 0);
    $phone         = trim($input['phone'] ?? '');

    if (!$invoice_id && !$gw_invoice_id) {
        echo json_encode(['success' => false, 'message' => 'No invoice specified.']);
        exit;
    }

    // Normalise phone to 254XXXXXXXXX
    $phone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phone, '0'))  $phone = '254' . substr($phone, 1);
    elseif (str_starts_with($phone, '+')) $phone = ltrim($phone, '+');
    if (!preg_match('/^2547\d{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number. Use 07XXXXXXXX format.']);
        exit;
    }

    // Fetch whichever invoice was passed
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

    // All platform billing goes through platform PesaFlux credentials
    if (!loadPlatformPesaflux()) {
        echo json_encode(['success' => false, 'message' => 'Platform payment gateway not configured. Please contact support.']);
        exit;
    }

    // Fire STK push via PesaFlux
    $payload = json_encode([
        'api_key'   => PESAFLUX_API_KEY,
        'email'     => PESAFLUX_EMAIL,
        'amount'    => $amount,
        'msisdn'    => $phone,
        'reference' => $reference,
    ]);

    $ch = curl_init(PESAFLUX_STK_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
    ]);
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

    // Save pending payment record
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

    echo json_encode([
        'success'                => true,
        'transaction_request_id' => $txn_id,
        'amount'                 => $amount,
        'message'                => 'STK push sent. Enter your M-Pesa PIN on your phone.',
    ]);
    exit;
}


/* ══════════════════════════════════════════════════════════════
   ACTION: verify — poll payment status for platform invoices
   ══════════════════════════════════════════════════════════════ */
if ($action === 'verify') {

    $txn_id        = trim($input['transaction_request_id'] ?? '');
    $invoice_id    = (int)($input['invoice_id']            ?? 0);
    $gw_invoice_id = (int)($input['gateway_invoice_id']    ?? 0);

    if (!$txn_id) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction ID.']);
        exit;
    }

    if (!loadPlatformPesaflux()) {
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured.']);
        exit;
    }

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
        echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Network error, retrying...']);
        exit;
    }
    curl_close($ch);

    error_log('[SubPay verify] txn=' . $txn_id . ' raw=' . $response);
    $result = json_decode($response, true);

    $transactionStatus = strtolower(trim($result['TransactionStatus'] ?? ''));
    $transactionCode   = trim((string)($result['TransactionCode']     ?? ''));
    $receipt           = trim($result['TransactionReceipt']           ?? '');

    // ── PAID ─────────────────────────────────────────────────
    if ($transactionStatus === 'completed' || $transactionCode === '0') {

        $mpesa_code = ($receipt !== '' && $receipt !== 'N/A') ? $receipt : '';

        if ($gw_invoice_id) {
            // Gateway invoice paid — activate gateway, extend expiry
            $stmt = $pdo->prepare("SELECT * FROM gateway_invoices WHERE id=? AND user_id=?");
            $stmt->execute([$gw_invoice_id, $user_id]);
            $gwInvoice = $stmt->fetch();

            if ($gwInvoice) {
                $stmt2 = $pdo->prepare("SELECT gateway_expires_at FROM subscriptions WHERE user_id=?");
                $stmt2->execute([$user_id]);
                $currentExpiry = $stmt2->fetchColumn();
                $baseDate = ($currentExpiry && strtotime($currentExpiry) > time())
                    ? $currentExpiry
                    : 'now';

                $expiresAt = $gwInvoice['plan'] === 'yearly'
                    ? date('Y-m-d H:i:s', strtotime('+1 year',  strtotime($baseDate)))
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
            // Platform subscription invoice paid
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

        echo json_encode(['success' => true, 'status' => 'paid', 'mpesa_code' => $mpesa_code, 'message' => 'Payment confirmed!']);
        exit;
    }

    // ── EXPLICITLY FAILED ────────────────────────────────────
    $explicitFailures = ['failed', 'cancelled', 'canceled', 'rejected', 'timeout', 'expired'];
    if (in_array($transactionStatus, $explicitFailures, true)) {
        if ($gw_invoice_id) {
            $pdo->prepare("UPDATE gateway_payments SET status='failed', updated_at=NOW() WHERE transaction_request_id=?")
                ->execute([$txn_id]);
        } else {
            $pdo->prepare("UPDATE subscription_payments SET status='failed', updated_at=NOW() WHERE transaction_request_id=?")
                ->execute([$txn_id]);
        }
        echo json_encode([
            'success' => false,
            'status'  => 'failed',
            'message' => $result['ResultDesc'] ?? 'Payment was cancelled or failed. Please try again.',
        ]);
        exit;
    }

    // ── PENDING ───────────────────────────────────────────────
    echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Waiting for payment confirmation...']);
    exit;
}


/* ══════════════════════════════════════════════════════════════
   ACTION: test_gateway — send KES 1 STK push via user's own keys
   Confirms their Till/Paybill works before going live.
   Supports pesaflux, mpesa (Daraja), and intasend.
   ══════════════════════════════════════════════════════════════ */
if ($action === 'test_gateway') {

    // Normalise phone
    $phone = trim($input['phone'] ?? '');
    $phone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phone, '0'))  $phone = '254' . substr($phone, 1);
    if (str_starts_with($phone, '+'))  $phone = ltrim($phone, '+');
    if (!preg_match('/^2547\d{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number. Use 07XXXXXXXX format.']);
        exit;
    }

    $provider = loadUserProviderKeys($user_id, $pdo);
    if ($provider === false) {
        echo json_encode(['success' => false, 'message' => 'No gateway API keys found. Please add them in Settings.']);
        exit;
    }

    // ── PesaFlux test ─────────────────────────────────────────
    if ($provider === 'pesaflux') {
        $payload = json_encode([
            'api_key'   => PESAFLUX_API_KEY,
            'email'     => PESAFLUX_EMAIL,
            'amount'    => 1,
            'msisdn'    => $phone,
            'reference' => 'TEST-GW-' . $user_id,
        ]);

        $ch = curl_init(PESAFLUX_STK_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('[TestGW pesaflux] curl error: ' . curl_error($ch));
            curl_close($ch);
            echo json_encode(['success' => false, 'message' => 'Could not reach payment gateway. Try again.']);
            exit;
        }
        curl_close($ch);
        error_log('[TestGW pesaflux] user_id=' . $user_id . ' raw=' . $response);
        $result = json_decode($response, true);
        if (empty($result['transaction_request_id'])) {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'STK push failed. Please try again.']);
            exit;
        }
        echo json_encode([
            'success'                => true,
            'provider'               => 'pesaflux',
            'transaction_request_id' => $result['transaction_request_id'],
        ]);
        exit;
    }

    // ── M-Pesa Daraja test ────────────────────────────────────
    if ($provider === 'mpesa') {

        $gwType     = defined('MPESA_GATEWAY_TYPE') ? MPESA_GATEWAY_TYPE : 'till';
        $accountRef = ($gwType === 'paybill' && defined('MPESA_ACCOUNT_REF') && MPESA_ACCOUNT_REF !== '')
            ? MPESA_ACCOUNT_REF
            : 'TEST-GW-' . $user_id;

        // Use production endpoint; switch to sandbox for testing if needed
        $accessToken = mpesaAccessToken(MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET, false);
        if (!$accessToken) {
            error_log('[TestGW mpesa] auth failed for user_id=' . $user_id);
            echo json_encode(['success' => false, 'message' => 'Could not authenticate with M-Pesa. Check your consumer key/secret.']);
            exit;
        }

        $callbackUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST']
            . '/subscriptions/mpesa_callback.php';

        $stkData = mpesaStkPayload(
            MPESA_SHORTCODE,
            MPESA_PASSKEY,
            MPESA_PARTYB,      // till number (BuyGoods) OR paybill number (PayBill)
            $gwType,           // determines TransactionType
            1,                 // KES 1 test amount
            $phone,
            $callbackUrl,
            $accountRef,
            'Gateway Test'
        );

        $ch = curl_init('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($stkData),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $stkResp = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('[TestGW mpesa] STK curl error: ' . curl_error($ch));
            curl_close($ch);
            echo json_encode(['success' => false, 'message' => 'Could not reach M-Pesa. Try again.']);
            exit;
        }
        curl_close($ch);

        error_log('[TestGW mpesa] user_id=' . $user_id . ' type=' . $gwType . ' raw=' . $stkResp);
        $resp = json_decode($stkResp, true);

        if (($resp['ResponseCode'] ?? '') !== '0') {
            echo json_encode([
                'success' => false,
                'message' => $resp['CustomerMessage'] ?? ($resp['errorMessage'] ?? 'STK push failed.'),
            ]);
            exit;
        }

        echo json_encode([
            'success'                => true,
            'provider'               => 'mpesa',
            'gateway_type'           => $gwType,
            'transaction_request_id' => $resp['CheckoutRequestID'],
        ]);
        exit;
    }

    // ── IntaSend test ─────────────────────────────────────────
    if ($provider === 'intasend') {
        $isPayload = json_encode([
            'public_key'   => INTASEND_PUBLIC_KEY,
            'amount'       => 1,
            'phone_number' => $phone,
            'currency'     => 'KES',
            'api_ref'      => 'TEST-GW-' . $user_id,
            'narrative'    => 'Gateway Test',
        ]);

        // Switch to live URL for production: https://payment.intasend.com/api/v1/payment/mpesa-stk-push/
        $isUrl = 'https://sandbox.intasend.com/api/v1/payment/mpesa-stk-push/';
        $ch    = curl_init($isUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $isPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . INTASEND_SECRET_KEY,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $isResp = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            echo json_encode(['success' => false, 'message' => 'Could not reach IntaSend. Try again.']);
            exit;
        }
        curl_close($ch);
        error_log('[TestGW intasend] user_id=' . $user_id . ' raw=' . $isResp);
        $isData = json_decode($isResp, true);
        $txnId  = $isData['invoice']['invoice_id'] ?? ($isData['id'] ?? '');
        if (!$txnId) {
            echo json_encode(['success' => false, 'message' => $isData['detail'] ?? 'STK push failed. Please try again.']);
            exit;
        }
        echo json_encode([
            'success'                => true,
            'provider'               => 'intasend',
            'transaction_request_id' => $txnId,
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unsupported gateway provider.']);
    exit;
}


/* ══════════════════════════════════════════════════════════════
   ACTION: verify_test — poll status of a test_gateway transaction
   Supports pesaflux, mpesa (Daraja), and intasend.
   ══════════════════════════════════════════════════════════════ */
if ($action === 'verify_test') {

    $txn_id = trim($input['transaction_request_id'] ?? '');
    if (!$txn_id) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction ID.']);
        exit;
    }

    $provider = loadUserProviderKeys($user_id, $pdo);
    if ($provider === false) {
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured.']);
        exit;
    }

    // ── PesaFlux verify ───────────────────────────────────────
    if ($provider === 'pesaflux') {
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
            curl_close($ch);
            echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Network error, retrying...']);
            exit;
        }
        curl_close($ch);
        error_log('[TestGW pesaflux verify] txn=' . $txn_id . ' raw=' . $response);
        $result = json_decode($response, true);
        $ts     = strtolower(trim($result['TransactionStatus'] ?? ''));
        $tc     = trim($result['TransactionCode']              ?? '');
        $code   = $result['TransactionReceipt']                ?? '';

        if ($ts === 'completed' || $tc === '0') {
            echo json_encode(['success' => true, 'status' => 'paid', 'mpesa_code' => $code]);
            exit;
        }
        if (in_array($ts, ['failed', 'cancelled', 'canceled', 'rejected', 'timeout', 'expired'])) {
            echo json_encode(['success' => true, 'status' => 'failed', 'message' => 'Payment was declined or cancelled.']);
            exit;
        }
        echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Waiting...']);
        exit;
    }

    // ── M-Pesa Daraja verify ──────────────────────────────────
    if ($provider === 'mpesa') {

        // Use production endpoint; switch to sandbox for testing if needed
        $accessToken = mpesaAccessToken(MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET, false);
        if (!$accessToken) {
            echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Auth retry...']);
            exit;
        }

        $qPayload = json_encode(mpesaStkQueryPayload(MPESA_SHORTCODE, MPESA_PASSKEY, $txn_id));

        $ch = curl_init('https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $qPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $qResp = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Retrying...']);
            exit;
        }
        curl_close($ch);

        error_log('[TestGW mpesa verify] txn=' . $txn_id . ' raw=' . $qResp);
        $qData = json_decode($qResp, true);
        $rc    = (string)($qData['ResultCode'] ?? '');

        if ($rc === '0') {
            echo json_encode([
                'success'    => true,
                'status'     => 'paid',
                'mpesa_code' => $qData['MpesaReceiptNumber'] ?? '',
            ]);
            exit;
        }
        if ($rc !== '') {
            // Any non-empty non-zero code means failure/cancellation
            echo json_encode([
                'success' => true,
                'status'  => 'failed',
                'message' => $qData['ResultDesc'] ?? 'Payment failed or cancelled.',
            ]);
            exit;
        }

        // Empty ResultCode = still processing
        echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Waiting...']);
        exit;
    }

    // ── IntaSend verify ───────────────────────────────────────
    if ($provider === 'intasend') {
        // Switch to live URL for production: https://payment.intasend.com/api/v1/payment/{id}/
        $isUrl = 'https://sandbox.intasend.com/api/v1/payment/' . urlencode($txn_id) . '/';
        $ch    = curl_init($isUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . INTASEND_SECRET_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $isResp = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Retrying...']);
            exit;
        }
        curl_close($ch);
        error_log('[TestGW intasend verify] txn=' . $txn_id . ' raw=' . $isResp);
        $isData = json_decode($isResp, true);
        $state  = strtolower($isData['invoice']['state'] ?? ($isData['state'] ?? ''));

        if ($state === 'complete') {
            echo json_encode([
                'success'    => true,
                'status'     => 'paid',
                'mpesa_code' => $isData['invoice']['mpesa_receipt_number'] ?? '',
            ]);
            exit;
        }
        if (in_array($state, ['failed', 'cancelled', 'expired'])) {
            echo json_encode(['success' => true, 'status' => 'failed', 'message' => 'Payment failed or cancelled.']);
            exit;
        }
        echo json_encode(['success' => true, 'status' => 'pending', 'message' => 'Waiting...']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unsupported provider.']);
    exit;
}


echo json_encode(['success' => false, 'message' => 'Invalid action.']);