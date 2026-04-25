<?php
require_once "../core/db.php";
require_once "../core/router.php";

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id = ?");
$stmt->execute([$id]);
$router = $stmt->fetch();

$client = router_connect($router['router_id']);

$resource = $client->query('/system/resource/print')->read()[0];

echo json_encode([
    "model" => $resource['board-name'],
    "version" => $resource['version'],
    "uptime" => $resource['uptime'],
    "cpu" => $resource['cpu-load'],
    "free_memory" => $resource['free-memory'],
    "total_memory" => $resource['total-memory']
]);
