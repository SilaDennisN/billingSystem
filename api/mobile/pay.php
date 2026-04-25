<?php
require_once '../db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../pesaflux.php';    // stkPush() and verifySTK()
require_once __DIR__ . '/send_push.php';      // sendPushToUser()

$conn = $pdo;

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'pay';


/* ══════════════════════════════════════════════════════════
   ACTION: get_pending_invoice
══════════════════════════════════════════════════════════ */
if ($action === 'get_pending_invoice') {
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if (!$user_id) { echo json_encode(['status' => 'error', 'message' => 'Missing user_id']); exit; }

    $stmt = $conn->prepare(
        "SELECT invoice_id, invoice_number, amount, status, period_start, period_end
         FROM invoices
         WHERE user_id = ? AND status IN ('unpaid', 'overdue')
         ORDER BY period_start ASC LIMIT 1"
    );
    $stmt->execute([$user_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($invoice
        ? ['status' => 'ok',   'invoice' => $invoice]
        : ['status' => 'none', 'message' => 'No pending invoices']);
    exit;
}


/* ══════════════════════════════════════════════════════════
   ACTION: pay — trigger STK push
   
   Supports both:
   - Single invoice: POST invoice_id
   - Multiple invoices: POST invoice_ids (comma-separated)
══════════════════════════════════════════════════════════ */
if ($action === 'pay') {
    $user_id    = isset($_POST['user_id'])    ? (int)$_POST['user_id']    : 0;
    $phone      = trim($_POST['phone']   ?? '');
    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $invoice_ids = isset($_POST['invoice_ids']) ? $_POST['invoice_ids'] : '';  // ← NEW: For "Pay All"

    if (!$user_id || !$phone) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']); exit;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HANDLE MULTIPLE INVOICES (Pay All)
    // ═══════════════════════════════════════════════════════════════════════
    if (!empty($invoice_ids)) {
        // Parse comma-separated invoice IDs
        $ids = array_filter(array_map('intval', explode(',', $invoice_ids)));
        if (empty($ids)) {
            echo json_encode(['status' => 'error', 'message' => 'No valid invoice IDs provided']); exit;
        }

        // Fetch all invoices matching the IDs
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare(
            "SELECT i.invoice_id, i.invoice_number, i.amount, i.router_id, i.plan_id, i.status,
                    u.username
             FROM invoices i
             JOIN hotspot_users u ON u.user_id = i.user_id
             WHERE i.invoice_id IN ($placeholders) AND i.user_id = ?
             ORDER BY i.period_start ASC"
        );
        $params = array_merge($ids, [$user_id]);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($invoices)) {
            echo json_encode(['status' => 'error', 'message' => 'No matching invoices found']); exit;
        }

        // Check if any invoice is already paid
        foreach ($invoices as $inv) {
            if ($inv['status'] === 'paid') {
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'One or more invoices are already paid'
                ]); 
                exit;
            }
        }

        // ✅ Calculate TOTAL amount for all invoices
        $total_amount = 0;
        foreach ($invoices as $inv) {
            $total_amount += (float)$inv['amount'];
        }

        // Use first invoice's router and plan info
        $router_id = (int)$invoices[0]['router_id'];
        $plan_id = (int)$invoices[0]['plan_id'];
        $username = $invoices[0]['username'];
        
        // Create reference: MULTI-{count}-INV-{id1}-{id2}...
        $invoice_ids_for_ref = implode('-', array_column($invoices, 'invoice_id'));
        $reference = 'MULTI-' . count($invoices) . '-INV-' . $invoice_ids_for_ref;
        
        // Store all invoice IDs as comma-separated for later lookup
        $invoice_ids_list = implode(',', array_column($invoices, 'invoice_id'));

        error_log("PAY_ALL: Processing " . count($invoices) . " invoices, Total: {$total_amount}, Ref: {$reference}");

    } else {
        // ═══════════════════════════════════════════════════════════════════
        // HANDLE SINGLE INVOICE
        // ═══════════════════════════════════════════════════════════════════
        if (!$invoice_id) {
            echo json_encode(['status' => 'error', 'message' => 'Missing invoice_id or invoice_ids']); exit;
        }

        $stmt = $conn->prepare(
            "SELECT i.invoice_id, i.invoice_number, i.amount, i.router_id, i.plan_id, i.status,
                    u.username
             FROM invoices i
             JOIN hotspot_users u ON u.user_id = i.user_id
             WHERE i.invoice_id = ? AND i.user_id = ? LIMIT 1"
        );
        $stmt->execute([$invoice_id, $user_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            echo json_encode(['status' => 'error', 'message' => 'Invoice not found']); exit;
        }
        if ($invoice['status'] === 'paid') {
            echo json_encode(['status' => 'error', 'message' => 'Invoice already paid']); exit;
        }

        $invoices = [$invoice];
        // $total_amount = (float)$invoice['amount'];
        $total_amount = 1;
        $router_id = (int)$invoice['router_id'];
        $plan_id = (int)$invoice['plan_id'];
        $username = $invoice['username'];
        $reference = $invoice['invoice_number'] ?? 'INV-' . $invoice_id;
        $invoice_ids_list = (string)$invoice_id;

        error_log("PAY_SINGLE: Invoice {$invoice_id}, Amount: {$total_amount}, Ref: {$reference}");
    }


    // ═══════════════════════════════════════════════════════════════════════
    // TRIGGER STK PUSH WITH TOTAL AMOUNT
    // ═══════════════════════════════════════════════════════════════════════
    $stk = stkPush($router_id, $total_amount, $phone, $reference);

    if (!$stk || empty($stk['transaction_request_id'])) {
        error_log("STK push failed: " . json_encode($stk));
        $errorMsg = $stk['message'] ?? 'STK push failed. Please try again.';
        echo json_encode(['status' => 'error', 'message' => $errorMsg]);
        exit;
    }

    $txn_id = $stk['transaction_request_id'];
    $token  = bin2hex(random_bytes(32));

    // ═══════════════════════════════════════════════════════════════════════
    // RECORD PAYMENT ATTEMPT IN DATABASE
    // ═══════════════════════════════════════════════════════════════════════
    $stmt = $conn->prepare(
        "INSERT INTO payments 
         (router_id, plan_id, username, amount, user_id, status,
          payment_method, user_type, payment_token, transaction_request_id, phone, invoice_ids)
         VALUES (?, ?, ?, ?, ?, 'pending', 'Online', 'pppoe', ?, ?, ?, ?)"
    );
    
    $success = $stmt->execute([
        $router_id, 
        $plan_id, 
        $username, 
        $total_amount, 
        $user_id, 
        $token, 
        $txn_id, 
        $phone,
        $invoice_ids_list  // ← Store all invoice IDs
    ]);

    if (!$success) {
        error_log("Failed to insert payment record: " . json_encode($stmt->errorInfo()));
        echo json_encode(['status' => 'error', 'message' => 'Failed to record payment']);
        exit;
    }

    $payment_id = $conn->lastInsertId();

    echo json_encode([
        'status'                 => 'success',
        'message'                => 'Check your phone for the M-Pesa prompt',
        'payment_id'             => (int)$payment_id,
        'transaction_request_id' => $txn_id,
        'amount'                 => $total_amount,
        'invoice_count'          => count($invoices),
    ]);
    exit;
}


/* ══════════════════════════════════════════════════════════
   ACTION: poll_payment — verify via Pesaflux + update tables + push notification
══════════════════════════════════════════════════════════ */
if ($action === 'poll_payment') {
    $payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
    if (!$payment_id) { echo json_encode(['status' => 'error', 'message' => 'Missing payment_id']); exit; }

    $stmt = $conn->prepare(
        "SELECT payment_id, status, transaction_request_id, router_id, amount, user_id, username, invoice_ids
         FROM payments WHERE payment_id = ? LIMIT 1"
    );
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo json_encode(['status' => 'not_found']); exit;
    }
    if ($payment['status'] === 'used') {
        echo json_encode(['status' => 'paid', 'mpesa_code' => null]); exit;
    }
    if ($payment['status'] === 'failed') {
        echo json_encode(['status' => 'failed']); exit;
    }

    // Ask Pesaflux for real status
    $verify = verifySTK($payment['router_id'], $payment['transaction_request_id']);

    // ✅ Log the full response for debugging
    error_log("Pesaflux verify response: " . json_encode($verify));

    // ✅ Map actual Pesaflux response fields
    $result_code       = $verify['ResultCode']        ?? null;
    $txn_status        = strtolower($verify['TransactionStatus'] ?? '');
    $txn_code          = $verify['TransactionCode']   ?? null;
    $mpesa_code        = $verify['TransactionReceipt'] ?? null;  // e.g. "SIS48OB9N4"

    // ✅ Determine if paid: ResultCode=200 AND TransactionStatus=completed AND TransactionCode=0
    $is_paid = (
        $result_code == '200' &&
        $txn_code    == '0'   &&
        in_array($txn_status, ['completed', 'success'])
    );

    // ✅ Determine if definitively failed
    $is_failed = (
        !empty($txn_status) &&
        in_array($txn_status, ['failed', 'cancelled', 'canceled']) 
    ) || (
        $result_code !== null && $result_code != '200' && !empty($txn_status)
    );

    if ($is_paid) {

        // Update payments → used
        $conn->prepare("UPDATE payments SET status = 'used', confirmed_at = NOW() WHERE payment_id = ?")
             ->execute([$payment_id]);

        // ✅ Mark ALL invoices as paid
        if (!empty($payment['invoice_ids'])) {
            $invoice_ids = array_filter(array_map('intval', explode(',', $payment['invoice_ids'])));

            if (!empty($invoice_ids)) {
                $placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
                $conn->prepare(
                    "UPDATE invoices SET status = 'paid', paid_at = NOW()
                     WHERE invoice_id IN ($placeholders) AND user_id = ?"
                )->execute(array_merge($invoice_ids, [(int)$payment['user_id']]));

                error_log("Marked " . count($invoice_ids) . " invoices as paid for user " . $payment['user_id']);
            }
        } else {
            // Fallback for legacy records without invoice_ids
            $conn->prepare(
                "UPDATE invoices SET status = 'paid', paid_at = NOW()
                 WHERE user_id = ? AND status IN ('unpaid','overdue') AND amount = ?
                 ORDER BY period_start ASC LIMIT 1"
            )->execute([(int)$payment['user_id'], (float)$payment['amount']]);
        }

        // ✅ Re-enable user internet access
        // Update the hotspot_users table so their subscription is active
        $conn->prepare(
            "UPDATE hotspot_users 
             SET status = 'active',
                 expiry_date = DATE_ADD(NOW(), INTERVAL (
                     SELECT duration_days FROM plans WHERE plan_id = (
                         SELECT plan_id FROM payments WHERE payment_id = ?
                     )
                 ) DAY)
             WHERE user_id = ?"
        )->execute([$payment_id, (int)$payment['user_id']]);

        error_log("User " . $payment['user_id'] . " internet access re-enabled");

        // Send push notification
        sendPushToUser(
            $conn,
            (int)$payment['user_id'],
            '✅ Payment Confirmed',
            'KES ' . number_format($payment['amount'], 2) . ' received. Your subscription is active.',
            ['type' => 'payment', 'amount' => (string)$payment['amount']]
        );

        echo json_encode(['status' => 'paid', 'mpesa_code' => $mpesa_code]);

    } elseif ($is_failed) {

        $conn->prepare("UPDATE payments SET status = 'failed' WHERE payment_id = ?")
             ->execute([$payment_id]);

        echo json_encode(['status' => 'failed']);

    } else {
        // Still pending — return full debug info in dev, strip before production
        echo json_encode([
            'status'  => 'pending',
            'debug'   => $verify   // ← remove this line in production
        ]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);