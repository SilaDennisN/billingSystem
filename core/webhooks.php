<?php

/**
 * PESAFLUX PRODUCTION WEBHOOK
 * --------------------------------
 * - Confirms STK payment
 * - Creates Mikrotik hotspot user
 * - Activates payment for auto-login
 */

date_default_timezone_set('Africa/Nairobi');

require_once __DIR__ . "/../core/db.php";
require_once __DIR__ . "/../core/router.php";

use RouterOS\Query;


/* -------------------------------------------------
   1. READ RAW INPUT + LOG (CRITICAL FOR DEBUGGING)
------------------------------------------------- */

$raw = file_get_contents("php://input");

file_put_contents(
    __DIR__ . "/pesaflux.log",
    date("Y-m-d H:i:s") . " " . $raw . PHP_EOL,
    FILE_APPEND
);

$data = json_decode($raw, true);

file_put_contents(
    __DIR__ . "/debug_ids.log",
    "TX_FROM_WEBHOOK: " . ($data['TransactionID'] ?? 'NONE') . PHP_EOL,
    FILE_APPEND
);


if (!$data) {
    http_response_code(400);
    exit("Invalid payload");
}


/* -------------------------------------------------
   2. EXTRACT REQUIRED FIELDS
------------------------------------------------- */
// THIS is your real request id
$transaction_id = $data['TransactionID'] ?? null;

// SUCCESS is ResponseCode = 0
$status = ($data['ResponseCode'] == 0) ? 'SUCCESS' : 'FAILED';

if (!$transaction_id) {
    http_response_code(400);
    exit("Missing transaction id");
}


/* -------------------------------------------------
   3. FETCH PAYMENT (LOCK ROW)
------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT * FROM payments
    WHERE transaction_request_id=?
    LIMIT 1
");
$stmt->execute([$transaction_id]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(404);
    exit("Payment not found");
}


/* -------------------------------------------------
   4. STOP IF ALREADY PROCESSED
------------------------------------------------- */

if (in_array($payment['status'], ['active', 'used'])) {
    http_response_code(200);
    exit("Already processed");
}


/* -------------------------------------------------
   5. HANDLE FAILED PAYMENT
------------------------------------------------- */

if ($status !== 'SUCCESS') {

    $pdo->prepare("
        UPDATE payments
        SET status='failed'
        WHERE payment_id=?
    ")->execute([$payment['payment_id']]);

    http_response_code(200);
    exit("Payment failed");
}


/* -------------------------------------------------
   6. ACTIVATE PAYMENT (TRANSACTION SAFE)
------------------------------------------------- */

$pdo->beginTransaction();

try {

    /* ---- Build credentials ---- */
    // $mac = $payment['mac'];
    // $username = 'mac_' . str_replace(':', '', strtolower($mac));
    $username = $payment['username'];
    $password = '123456'; // or generate dynamically


    /* ---- Load plan ---- */
    $stmt = $pdo->prepare("
        SELECT * FROM hotspot_profiles
        WHERE id=? AND plan_type='hotspot'
    ");
    $stmt->execute([$payment['plan_id']]);
    $plan = $stmt->fetch();

    if (!$plan) {
        throw new Exception("Invalid plan");
    }


    /* ---- Connect router ---- */
    $client = router_connect($payment['router_id']);
    if (!$client) {
        throw new Exception("Router connection failed");
    }


    /* ---- Create Mikrotik user ---- */
    $query = new Query('/ip/hotspot/user/add');
    $query->equal('name', $username);
    $query->equal('password', $password);
    $query->equal('profile', $plan['profile_name']);
    $query->equal('comment', 'Paid via STK');

    if ($plan['validity_hours']) {
        $query->equal('limit-uptime', $plan['validity_hours'] . 'h');
    } elseif ($plan['validity_days']) {
        $query->equal('limit-uptime', $plan['validity_days'] . 'd');
    }

    $client->query($query)->read();


    /* ---- Activate payment ---- */
    $stmt = $pdo->prepare("
        UPDATE payments
        SET status='used',
            username=?,
            confirmed_at=NOW()
        WHERE payment_id=?
    ");

    $stmt->execute([
        $username,
        $payment['payment_id']
    ]);


    $pdo->commit();

    http_response_code(200);
    echo "OK";
} catch (Throwable $e) {

    $pdo->rollBack();

    file_put_contents(
        __DIR__ . "/errors.log",
        date("Y-m-d H:i:s") . " " . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    http_response_code(500);
    echo "ERROR";
}
