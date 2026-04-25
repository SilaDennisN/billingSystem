<?php
require_once __DIR__ . '/db.php';

require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config as RouterConfig;

function router_connect($router_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id = ? AND status = 'active'");
    $stmt->execute([$router_id]);
    $router = $stmt->fetch();

    if (!$router) return false;

    try {
        $config = new RouterConfig([
            'host' => $router['host'],
            'user' => $router['api_user'],
            'pass' => $router['api_pass'],
            'port' => $router['api_port'],
            'timeout' => 90,
        ]);

        return new Client($config);

    } catch (Exception $e) {
        return false;
    }
}
