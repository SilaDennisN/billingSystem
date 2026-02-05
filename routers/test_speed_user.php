<?php
require_once "../core/db.php";
require_once "../core/router.php"; // your RouterOS client

header('Content-Type: application/json');

$router_id = (int)($_GET['router_id'] ?? 0);
$username  = $_GET['username'] ?? '';

if (!$router_id || !$username) {
    echo json_encode(["status" => "Invalid"]);
    exit;
}

// get router credentials
// $stmt = $pdo->prepare("SELECT * FROM routers WHERE id=?");
// $stmt->execute([$router_id]);
// $r = $stmt->fetch();
// if (!$r) {
//     echo json_encode(["status" => "Router not found"]);
//     exit;
// }

$client = router_connect($router_id);

$query = new RouterOS\Query('/ip/hotspot/active/print');
$actives = $client->query($query)->read();

$rx = 0;
$tx = 0;
$status = "Offline";

foreach ($actives as $a) {
    if (($a['user'] ?? '') === $username) {
        $rx = (int)($a['bytes-in'] ?? 0);
        $tx = (int)($a['bytes-out'] ?? 0);
        $status = "Online";
        break;
    }
}

echo json_encode([
    "status" => $status,
    "rx_bytes" => $rx,
    "tx_bytes" => $tx
]);
