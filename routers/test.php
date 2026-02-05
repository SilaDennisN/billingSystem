<?php
require_once "../core/router.php";
require_once "../core/db.php";

header("Content-Type: application/json");

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['online' => false, 'status' => 'Invalid']);
    exit;
}

$client = router_connect($id);



if ($client) {
    $pdo->prepare("
        UPDATE routers 
        SET last_seen = NOW(), online_status = 'online'
        WHERE router_id = ?
    ")->execute([$id]);

    echo json_encode([
        'online' => true,
        'status' => 'Online',
        'last_seen' => date('Y-m-d H:i:s')
    ]);
} else {
    $pdo->prepare("
        UPDATE routers 
        SET online_status = 'offline'
        WHERE router_id = ?
    ")->execute([$id]);

    echo json_encode([
        'online' => false,
        'status' => 'Offline'
    ]);
}
