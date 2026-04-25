<?php
require_once "../core/db.php";
require_once "../core/router.php";

header('Content-Type: application/json');

$router_id = (int)($_GET['router_id'] ?? 0);
$username  = trim($_GET['username'] ?? '');

if (!$router_id || !$username) {
    echo json_encode(["status" => "Invalid"]);
    exit;
}

try {
    $client = router_connect($router_id);
} catch (Exception $e) {
    echo json_encode(["status" => "Router Error"]);
    exit;
}

$status = "Offline";
$rx = 0;
$tx = 0;

/* =====================================================
   1️⃣ CHECK HOTSPOT ACTIVE USERS
=====================================================*/
try {
    $query = new RouterOS\Query('/ip/hotspot/active/print');
    $query->where('user', $username);
    $result = $client->query($query)->read();

    if (!empty($result)) {
        $user = $result[0];

        $rx = (int)($user['bytes-in'] ?? 0);
        $tx = (int)($user['bytes-out'] ?? 0);
        $status = "Online";

        echo json_encode([
            "status" => $status,
            "rx_bytes" => $rx,
            "tx_bytes" => $tx
        ]);
        exit;
    }
} catch (Exception $e) {
    // continue to PPPoE
}

/* =====================================================
   2️⃣ CHECK PPPoE ACTIVE USERS
=====================================================*/
try {

    $query = new RouterOS\Query('/ppp/active/print');
    $query->where('name', $username);
    $result = $client->query($query)->read();

    if (!empty($result)) {

        $interface = "pppoe-" . $username;

        $q = new RouterOS\Query('/interface/monitor-traffic');
        $q->equal('interface', $interface);
        $q->equal('once', '');

        $traffic = $client->query($q)->read();

        if (!empty($traffic)) {
            $rx = (int)($traffic[0]['rx-bits-per-second'] ?? 0) / 8;
            $tx = (int)($traffic[0]['tx-bits-per-second'] ?? 0) / 8;
        }

        echo json_encode([
            "status" => "Online",
            "rx_bytes" => $rx,
            "tx_bytes" => $tx
        ]);

        exit;
    }

} catch (Exception $e) {
}
/* =====================================================
   3️⃣ USER NOT ONLINE
=====================================================*/
echo json_encode([
    "status" => "Offline",
    "rx_bytes" => 0,
    "tx_bytes" => 0
]);