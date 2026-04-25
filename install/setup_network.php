<?php
require_once "../core/db.php";
require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

$router_id = $_POST['router_id'];

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$r = $stmt->fetch();

$client = new Client(new Config([
    'host' => $r['vpn_ip'],
    'user' => 'admin',
    'pass' => $r['api_pass'],
]));

function run($client, $path, $params=[]) {
    $q = new Query($path);
    foreach ($params as $k=>$v) $q->equal($k,$v);
    return $client->query($q)->read();
}

// 1. Bridge
run($client, '/interface/bridge/add', [
    'name' => 'bridge'
]);

// 2. Ports
foreach (['ether2','ether3','ether4','ether5','wlan1'] as $p) {
    run($client, '/interface/bridge/port/add', [
        'bridge' => 'bridge',
        'interface' => $p
    ]);
}

// 3. IP
run($client, '/ip/address/add', [
    'address' => '192.168.50.1/24',
    'interface' => 'bridge'
]);

// 4. WAN DHCP
run($client, '/ip/dhcp-client/add', [
    'interface' => 'ether1'
]);

// 5. NAT
run($client, '/ip/firewall/nat/add', [
    'chain' => 'srcnat',
    'action' => 'masquerade',
    'out-interface' => 'ether1'
]);

echo "✅ Network setup complete";