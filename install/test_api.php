<?php
require_once "../core/db.php";
require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;

$router_id = $_POST['router_id'];

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$r = $stmt->fetch();

try {
    $client = new Client(new Config([
        'host' => $r['vpn_ip'],
        'user' => 'admin',
        'pass' => $r['api_pass'],
    ]));

    echo "✅ Connected to router";

} catch (Exception $e) {
    echo "❌ Failed: " . $e->getMessage();
}