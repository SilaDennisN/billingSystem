<?php
require_once '../db.php';
require_once __DIR__ . '/../config.php'; // loads loadPesafluxConfig + constants
require_once __DIR__ . '/../pesaflux.php';       // stkPush() and verifySTK()

$conn = $pdo;

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'pay';


/* ══════════════════════════════════════════════════════════
   ACTION: get_pending_invoice
   Returns the oldest unpaid/overdue invoice for this user.
   The app uses this to display the amount (locked, no manual entry).
══════════════════════════════════════════════════════════ */
if ($action === 'get_pending_invoice') {
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if (!$user_id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT invoice_id, invoice_number, amount, status, period_start, period_end
         FROM invoices
         WHERE user_id = ?
           AND status IN ('unpaid', 'overdue')
         ORDER BY period_start ASC
         LIMIT 1"
    );
    $stmt->execute([$user_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {
        echo json_encode(['status' => 'ok', 'invoice' => $invoice]);
    } else {
        echo json_encode(['status' => 'none', 'message' => 'No pending invoices']);
    }
    exit;
}


/* ══════════════════════════════════════════════════════════
   ACTION: pay
   - Looks up the invoice to get router_id, plan_id, username
   - Triggers STK push via stkPush()
   - Records a row in payments table (status = 'pending')
   - Returns payment_id + transaction_request_id for polling
══════════════════════════════════════════════════════════ */
if ($action === 'pay') {
    $user_id    = isset($_POST['user_id'])    ? (int)$_POST['user_id']    : 0;
    $phone      = trim($_POST['phone']   ?? '');
    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;

    if (!$user_id || !$phone || !$invoice_id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    // ── Fetch invoice details ─────────────────────────────
    $stmt = $conn->prepare(
        "SELECT i.invoice_id, i.invoice_number, i.amount,
                i.router_id, i.plan_id, i.status,
                u.username
         FROM invoices i
         JOIN hotspot_users u ON u.user_id = i.user_id
         WHERE i.invoice_id = ?
           AND i.user_id    = ?
         LIMIT 1"
    );
    $stmt->execute([$invoice_id, $user_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
        exit;
    }

    if ($invoice['status'] === 'paid') {
        echo json_encode(['status' => 'error', 'message' => 'Invoice already paid']);
        exit;
    }

    $amount    = $invoice['amount'];
    $router_id = $invoice['router_id'];
    $plan_id   = $invoice['plan_id'];
    $username  = $invoice['username'];
    $reference = $invoice['invoice_number'] ?? 'INV-' . $invoice_id;

    // ── Trigger STK push ──────────────────────────────────
    $stk = stkPush($router_id, $amount, $phone, $reference);

    if (!$stk || empty($stk['transaction_request_id'])) {
        error_log("STK push failed for invoice $invoice_id: " . json_encode($stk));
        echo json_encode([
            'status'  => 'error',
            'message' => $stk['message'] ?? 'STK push failed. Please try again.'
        ]);
        exit;
    }

    $txn_id = $stk['transaction_request_id'];

    // ── Record payment attempt in payments table ──────────
    $token = bin2hex(random_bytes(32));

    $stmt = $conn->prepare(
        "INSERT INTO payments
            (router_id, plan_id, username, amount, user_id,
             status, payment_method, user_type,
             payment_token, transaction_request_id, phone)
         VALUES
            (?, ?, ?, ?, ?,
             'pending', 'Online', 'pppoe',
             ?, ?, ?)"
    );
    $stmt->execute([
        $router_id, $plan_id, $username, $amount, $user_id,
        $token, $txn_id, $phone
    ]);
    $payment_id = $conn->lastInsertId();

    echo json_encode([
        'status'                 => 'success',
        'message'                => 'Check your phone for the M-Pesa prompt',
        'payment_id'             => (int)$payment_id,
        'transaction_request_id' => $txn_id,
    ]);
    exit;
}


/* ══════════════════════════════════════════════════════════
   ACTION: poll_payment
   - Calls verifySTK() to check live payment status from Pesaflux
   - If paid: updates payments.status = 'used', payments.confirmed_at
              and invoices.status = 'paid', invoices.paid_at
   - Returns current status to the Flutter app
══════════════════════════════════════════════════════════ */
if ($action === 'poll_payment') {
    $payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

    if (!$payment_id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing payment_id']);
        exit;
    }

    // Load the payment row
    $stmt = $conn->prepare(
        "SELECT payment_id, status, transaction_request_id,
                router_id, amount, user_id, username
         FROM payments
         WHERE payment_id = ?
         LIMIT 1"
    );
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo json_encode(['status' => 'not_found']);
        exit;
    }

    // Already resolved — return cached result immediately
    if ($payment['status'] === 'used') {
        echo json_encode(['status' => 'paid', 'mpesa_code' => null]);
        exit;
    }
    if ($payment['status'] === 'failed') {
        echo json_encode(['status' => 'failed']);
        exit;
    }

    // ── Ask Pesaflux for the real status ──────────────────
    $verify = verifySTK($payment['router_id'], $payment['transaction_request_id']);

    // Pesaflux returns: { "status": "paid"|"pending"|"failed", "mpesa_code": "..." }
    // Adjust field names below if your Pesaflux version uses different keys
    $pf_status   = strtolower($verify['status']   ?? 'pending');
    $mpesa_code  = $verify['mpesa_code']           ?? $verify['MpesaReceiptNumber'] ?? null;

    if ($pf_status === 'paid' || $pf_status === 'success' || $pf_status === 'completed') {
        // Mark payment as used
        $conn->prepare(
            "UPDATE payments
             SET status = 'used', confirmed_at = NOW()
             WHERE payment_id = ?"
        )->execute([$payment_id]);

        // Mark the invoice as paid — find it via user_id + amount + unpaid status
        $conn->prepare(
            "UPDATE invoices
             SET status = 'paid', paid_at = NOW()
             WHERE user_id  = ?
               AND status  IN ('unpaid', 'overdue')
               AND amount   = ?
             ORDER BY period_start ASC
             LIMIT 1"
        )->execute([$payment['user_id'], $payment['amount']]);

        echo json_encode(['status' => 'paid', 'mpesa_code' => $mpesa_code]);

    } elseif ($pf_status === 'failed' || $pf_status === 'cancelled' || $pf_status === 'error') {
        $conn->prepare(
            "UPDATE payments SET status = 'failed' WHERE payment_id = ?"
        )->execute([$payment_id]);

        echo json_encode(['status' => 'failed']);

    } else {
        // Still pending
        echo json_encode(['status' => 'pending']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);