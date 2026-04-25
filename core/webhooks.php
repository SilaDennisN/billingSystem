<?php

date_default_timezone_set('Africa/Nairobi');

/* =================================================
   READ INPUT ONCE — TOP OF FILE
================================================= */
$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);

/* =================================================
   LOG DIRECTORY — BEFORE ANYTHING ELSE
================================================= */
$logDir = __DIR__ . "/logs";
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

function logMsg($file, $msg) {
    global $logDir;
    file_put_contents(
        $logDir . "/" . $file . "_" . date("Ymd") . ".log",
        date("H:i:s") . " | " . $msg . PHP_EOL,
        FILE_APPEND
    );
}

function routerQuery($client, $query) {
    $response = $client->query($query)->read();
    foreach ($response as $r) {
        if (isset($r['!trap'])) {
            throw new Exception("Router trap: " . json_encode($r));
        }
    }
    return $response;
}

/* =================================================
   LOG EVERYTHING THAT ARRIVES
================================================= */
logMsg("raw", $raw);

if (!$data) {
    logMsg("errors", "Invalid JSON received");
    http_response_code(400);
    exit("Invalid JSON");
}

logMsg("debug", "Keys received: " . implode(", ", array_keys($data)));

/* =================================================
   INTASEND CHALLENGE
   Always respond to challenge first.
   Use output buffering so DB output doesn't conflict.
================================================= */
if (isset($data['challenge'])) {

    logMsg("debug", "Challenge received. invoice_id present: " . (isset($data['invoice_id']) ? 'YES' : 'NO'));

    if ($data['challenge'] !== 'Dennissila1256') {
        logMsg("errors", "Invalid challenge: " . $data['challenge']);
        http_response_code(403);
        exit;
    }

    // Respond to challenge immediately
    header('Content-Type: application/json');
    echo json_encode(["challenge" => $data['challenge']]);

    // If no invoice_id this is just a ping — stop here
    if (!isset($data['invoice_id'])) {
        logMsg("debug", "Challenge-only ping, no invoice. Exiting.");
        exit;
    }

    // Has invoice_id — flush challenge response and continue processing
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // Send HTTP response now, keep PHP running
    }

    logMsg("debug", "Challenge sent, continuing with payment processing...");
}

/* =================================================
   NOW LOAD DEPENDENCIES
================================================= */
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/../core/db.php";
require_once __DIR__ . "/../core/router.php";
require_once __DIR__ . "/../core/sms.php";

use RouterOS\Query;

/* =================================================
   DETECT PROVIDER
================================================= */
$provider = null;

if (isset($data['TransactionID'])) {
    $provider = 'pesaflux';
} elseif (isset($data['Body']['stkCallback'])) {
    $provider = 'mpesa';
} elseif (isset($data['invoice_id']) && isset($data['topic'])) {
    $provider = 'intasend';
} else {
    logMsg("errors", "Unknown provider. Keys: " . implode(", ", array_keys($data)));
    http_response_code(400);
    exit("Unknown provider");
}

logMsg("provider", "Detected: $provider");

/* =================================================
   EXTRACT DATA
================================================= */
$transaction_id = null;
$status         = 'FAILED';
$receipt        = null;
$phone          = null;

if ($provider === 'pesaflux') {
    $transaction_id = $data['TransactionID'] ?? null;
    $status         = ($data['ResponseCode'] ?? 1) == 0 ? 'SUCCESS' : 'FAILED';
}

if ($provider === 'mpesa') {
    $stk            = $data['Body']['stkCallback'];
    $transaction_id = $stk['CheckoutRequestID'] ?? null;
    $status         = ($stk['ResultCode'] ?? 1) == 0 ? 'SUCCESS' : 'FAILED';

    if (!empty($stk['CallbackMetadata']['Item'])) {
        foreach ($stk['CallbackMetadata']['Item'] as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') $receipt = $item['Value'];
            if ($item['Name'] === 'PhoneNumber')        $phone   = $item['Value'];
        }
    }
    logMsg("mpesa", "Receipt: $receipt | Phone: $phone");
}

if ($provider === 'intasend') {

    $state          = strtoupper($data['state'] ?? '');
    $transaction_id = $data['invoice_id'] ?? null;
    $receipt        = $data['invoice_id'] ?? null;
    $phone          = $data['account']    ?? null;
    $status         = ($state === 'COMPLETE') ? 'SUCCESS' : 'FAILED';

    logMsg("intasend", "invoice_id: $transaction_id | state: $state | phone: $phone | status: $status");

    // Skip intermediate states — don't touch the DB, just acknowledge
    if (in_array($state, ['PENDING', 'PROCESSING'])) {
        logMsg("intasend", "Intermediate state ($state) — skipping DB update");
        exit("OK");
    }
}

if (!$transaction_id) {
    logMsg("errors", "transaction_id is empty | provider: $provider");
    http_response_code(400);
    exit("Missing transaction id");
}

logMsg("debug", "Looking up payment with transaction_request_id = $transaction_id");

/* =================================================
   DB TRANSACTION
================================================= */
$pdo->beginTransaction();

try {

    $stmt = $pdo->prepare("
        SELECT * FROM payments
        WHERE transaction_request_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$transaction_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        // Log ALL pending payments to help diagnose mismatch
        $all = $pdo->query("SELECT payment_id, transaction_request_id, status FROM payments ORDER BY payment_id DESC LIMIT 10")->fetchAll();
        logMsg("errors", "Payment not found for: $transaction_id | Recent payments: " . json_encode($all));
        throw new Exception("Payment not found: $transaction_id");
    }

    logMsg("debug", "Payment found: ID={$payment['payment_id']} status={$payment['status']}");

    /* STOP DUPLICATES */
    if (in_array($payment['status'], ['used', 'active'])) {
        $pdo->commit();
        logMsg("debug", "Already processed: $transaction_id");
        exit("Already processed");
    }

    /* FAILED / CANCELLED PAYMENT */
    if ($status !== 'SUCCESS') {
        $terminalFail = in_array(strtoupper($data['state'] ?? ''), ['FAILED', 'CANCELLED']);
        if ($terminalFail) {
            $pdo->prepare("UPDATE payments SET status='failed' WHERE payment_id=?")
                ->execute([$payment['payment_id']]);
            logMsg("intasend", "Payment marked failed: $transaction_id | reason: " . ($data['failed_reason'] ?? 'unknown'));
        }
        $pdo->commit();
        exit("Payment not successful");
    }

    /* LOAD PLAN */
    $stmt = $pdo->prepare("SELECT * FROM hotspot_profiles WHERE id=? LIMIT 1");
    $stmt->execute([$payment['plan_id']]);
    $plan = $stmt->fetch();

    if (!$plan) throw new Exception("Plan not found: " . $payment['plan_id']);

    /* CONNECT ROUTER */
    $client = router_connect($payment['router_id']);
    if (!$client) throw new Exception("Router connection failed");
    logMsg("router", "Connected");

    $username = $payment['username'];
    $password = '123456';
    $limit    = null;

    if (!empty($plan['validity_hours'])) {
        $limit = $plan['validity_hours'] . "h";
    } elseif (!empty($plan['validity_days'])) {
        $limit = $plan['validity_days'] . "d";
    }

    /* REMOVE ACTIVE SESSION */
    $q = new Query('/ip/hotspot/active/print');
    $q->where('user', $username);
    foreach (routerQuery($client, $q) as $a) {
        $r = new Query('/ip/hotspot/active/remove');
        $r->equal('.id', $a['.id']);
        routerQuery($client, $r);
    }
    logMsg("router", "Active sessions cleared");

    /* REMOVE HOST CACHE */
    $macRouter = strtoupper(implode(":", str_split(substr($username, 4), 2)));
    $q = new Query('/ip/hotspot/host/print');
    $q->where('mac-address', $macRouter);
    foreach (routerQuery($client, $q) as $h) {
        $r = new Query('/ip/hotspot/host/remove');
        $r->equal('.id', $h['.id']);
        routerQuery($client, $r);
    }
    logMsg("router", "Host cache cleared");

    /* DELETE OLD USER */
    $q = new Query('/ip/hotspot/user/print');
    $q->where('name', $username);
    foreach (routerQuery($client, $q) as $u) {
        $r = new Query('/ip/hotspot/user/remove');
        $r->equal('.id', $u['.id']);
        routerQuery($client, $r);
    }
    logMsg("router", "Old user removed");

    /* CREATE NEW USER */
    $q = new Query('/ip/hotspot/user/add');
    $q->equal('name',     $username);
    $q->equal('password', $password);
    $q->equal('profile',  $plan['profile_name']);
    $q->equal('comment',  'Paid ' . date("Y-m-d H:i"));
    if ($limit) $q->equal('limit-uptime', $limit);
    routerQuery($client, $q);
    logMsg("router", "User created: $username");

    /* VERIFY USER */
    $q = new Query('/ip/hotspot/user/print');
    $q->where('name', $username);
    if (empty(routerQuery($client, $q))) {
        throw new Exception("Router failed to create user: $username");
    }

    /* HOTSPOT USERS TABLE */
    $start  = new DateTime();
    $expire = clone $start;
    if (!empty($plan['validity_hours'])) {
        $expire->modify("+{$plan['validity_hours']} hours");
    } elseif (!empty($plan['validity_days'])) {
        $expire->modify("+{$plan['validity_days']} days");
    }

    $pdo->prepare("
        INSERT INTO hotspot_users
        (router_id, plan_id, username, user_type, password, starts_at, expires_at, status)
        VALUES (?, ?, ?, 'hotspot', ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            plan_id    = VALUES(plan_id),
            password   = VALUES(password),
            starts_at  = VALUES(starts_at),
            expires_at = VALUES(expires_at),
            status     = 'active'
    ")->execute([
        $payment['router_id'],
        $payment['plan_id'],
        $username,
        $password,
        $start->format('Y-m-d H:i:s'),
        $expire->format('Y-m-d H:i:s')
    ]);

    /* WELCOME SMS */
    $mac        = $username;
    $payPhone   = $payment['phone'];

    if (!empty($payPhone)) {
        $stmt = $pdo->prepare("SELECT id FROM hotspot_devices WHERE mac=? LIMIT 1");
        $stmt->execute([$mac]);

        if (!$stmt->fetch()) {
            $pdo->prepare("
                INSERT INTO hotspot_devices (mac, phone, welcome_sms_sent) VALUES (?, ?, 1)
            ")->execute([$mac, $payPhone]);

            sendSMS($payPhone,
                "Welcome to PAYG Network WiFi ✅\n\nYour package is now active.\n\nCheck balance:\nwifi.inovatech.co.ke/status\n\nSupport: 0785633314\n\nEnjoy!"
            );
            logMsg("sms", "Welcome SMS sent to $payPhone");
        }
    }

    /* MARK PAYMENT USED */
    $pdo->prepare("
        UPDATE payments SET status='used', confirmed_at=NOW() WHERE payment_id=?
    ")->execute([$payment['payment_id']]);

    $pdo->commit();
    logMsg("success", "DONE | USER: $username | TX: $transaction_id | Provider: $provider");
    echo "OK";

} catch (Throwable $e) {
    $pdo->rollBack();
    logMsg("errors", "EXCEPTION: " . $e->getMessage() . " | TX: $transaction_id");
    http_response_code(500);
    echo "ERROR";
}