<?php

date_default_timezone_set('Africa/Nairobi');
require_once __DIR__ . "/config/config.php";

require_once __DIR__ . "/../core/db.php";
require_once __DIR__ . "/../core/router.php";
require_once __DIR__ . "/../core/sms.php";


use RouterOS\Query;


/* =================================================
   CREATE LOG DIRECTORY
================================================= */

$logDir = __DIR__ . "/logs";

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

function logMsg($file, $msg)
{
    global $logDir;

    file_put_contents(
        $logDir . "/" . $file . "_" . date("Ymd") . ".log",
        date("H:i:s") . " | " . $msg . PHP_EOL,
        FILE_APPEND
    );
}


/* =================================================
   ROUTER SAFE QUERY (Detect !trap)
================================================= */

function routerQuery($client, Query $query)
{
    $response = $client->query($query)->read();

    foreach ($response as $r) {
        if (isset($r['!trap'])) {
            throw new Exception("Router trap: " . json_encode($r));
        }
    }

    return $response;
}



/* =================================================
   READ WEBHOOK
================================================= */

$raw = file_get_contents("php://input");
logMsg("pesaflux", $raw);

$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    exit("Invalid JSON");
}


/* =================================================
   EXTRACT DATA
================================================= */

$transaction_id = $data['TransactionID'] ?? null;
$status = ($data['ResponseCode'] ?? 1) == 0 ? 'SUCCESS' : 'FAILED';

if (!$transaction_id) {
    http_response_code(400);
    exit("Missing transaction id");
}


/* =================================================
   DB TRANSACTION
================================================= */

$pdo->beginTransaction();

try {

    /* LOCK PAYMENT */
    $stmt = $pdo->prepare("
        SELECT * FROM payments
        WHERE transaction_request_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$transaction_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        throw new Exception("Payment not found");
    }


    /* STOP DUPLICATES */
    if (in_array($payment['status'], ['used', 'active'])) {
        $pdo->commit();
        exit("Already processed");
    }


    /* FAILED PAYMENT */
    if ($status !== 'SUCCESS') {

        $pdo->prepare("
            UPDATE payments
            SET status='failed'
            WHERE payment_id=?
        ")->execute([$payment['payment_id']]);

        $pdo->commit();
        exit("Payment failed");
    }


    /* LOAD PLAN */
    $stmt = $pdo->prepare("
        SELECT * FROM hotspot_profiles
        WHERE id=? LIMIT 1
    ");

    $stmt->execute([$payment['plan_id']]);
    $plan = $stmt->fetch();

    if (!$plan) {
        throw new Exception("Invalid hotspot plan");
    }


    /* CONNECT ROUTER */
    $client = router_connect($payment['router_id']);

    if (!$client) {
        throw new Exception("Router connection failed");
    }

    logMsg("router", "Connected to router");


    $username = $payment['username'];
    $password = '123456';

    $limit = null;

    if (!empty($plan['validity_hours'])) {
        $limit = $plan['validity_hours'] . "h";
    } elseif (!empty($plan['validity_days'])) {
        $limit = $plan['validity_days'] . "d";
    }



/* =================================================
   REMOVE ACTIVE SESSION
================================================= */

$active = new Query('/ip/hotspot/active/print');
$active->where('user', $username);

$actives = routerQuery($client, $active);

foreach ($actives as $a) {

    $remove = new Query('/ip/hotspot/active/remove');
    $remove->equal('.id', $a['.id']);

    routerQuery($client, $remove);
}

logMsg("router", "Active sessions cleared");


/* =================================================
   REMOVE HOST CACHE (VERY IMPORTANT)
================================================= */
$macRouter = strtoupper(implode(":", str_split(substr($username,4),2)));

$host = new Query('/ip/hotspot/host/print');
$host->where('mac-address', $macRouter);

$hosts = routerQuery($client, $host);

foreach ($hosts as $h) {

    $remove = new Query('/ip/hotspot/host/remove');
    $remove->equal('.id', $h['.id']);

    routerQuery($client, $remove);
}

logMsg("router", "Host cache cleared");


/* =================================================
   DELETE OLD USER
================================================= */

$check = new Query('/ip/hotspot/user/print');
$check->where('name', $username);

$users = routerQuery($client, $check);

foreach ($users as $u) {

    $remove = new Query('/ip/hotspot/user/remove');
    $remove->equal('.id', $u['.id']);

    routerQuery($client, $remove);
}

logMsg("router", "Old user removed");


/* =================================================
   CREATE NEW USER
================================================= */

$add = new Query('/ip/hotspot/user/add');
$add->equal('name', $username);
$add->equal('password', $password);
$add->equal('profile', $plan['profile_name']);
$add->equal('comment', 'Paid ' . date("Y-m-d H:i"));

if ($limit) {
    $add->equal('limit-uptime', $limit);
}

routerQuery($client, $add);

logMsg("router", "User created");


/* =================================================
   VERIFY USER EXISTS (CRITICAL)
================================================= */

$verify = new Query('/ip/hotspot/user/print');
$verify->where('name', $username);

$result = routerQuery($client, $verify);

if (empty($result)) {
    throw new Exception("Router failed to create user");
}

/* =================================================
   MARK PAYMENT USED
================================================= */


$mac = $username;
$phone = $payment['phone'];

if(!empty($phone)){

    $stmt = $pdo->prepare("
        SELECT id FROM hotspot_devices
        WHERE mac=?
        LIMIT 1
    ");
    $stmt->execute([$mac]);

    $device = $stmt->fetch();

    if(!$device){

        // save device
        $pdo->prepare("
            INSERT INTO hotspot_devices (mac, phone, welcome_sms_sent)
            VALUES (?, ?, 1)
        ")->execute([$mac, $phone]);


        $message = "PAY G WiFi Active ✅

Check remaining time or reconnect:
http://portal.inovatech.co.ke

Enjoy fast internet!
- Inovatech";

        sendSMS($phone, $message);

        logMsg("sms", "Welcome SMS sent to $phone");
    }
}



/* =================================================
   MARK PAYMENT USED
================================================= */

$pdo->prepare("
    UPDATE payments
    SET status='used',
        confirmed_at=NOW()
    WHERE payment_id=?
")->execute([$payment['payment_id']]);

$pdo->commit();

logMsg("success", "USER: {$username} | TX: {$transaction_id}");

echo "OK";

} catch (Throwable $e) {

    $pdo->rollBack();

    logMsg("errors", $e->getMessage());

    http_response_code(500);
    echo "ERROR";
}
