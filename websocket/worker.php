<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/router_stats.php';

$stmt = $pdo->query("SELECT router_id FROM routers WHERE status = 'active'");
$routers = $stmt->fetchAll(PDO::FETCH_COLUMN);

// websocket/worker.php

while (true) {

    foreach ($routers as $router_id) {

        $data = getRouterStats($pdo, $router_id); // reuse your existing logic

        $server->broadcast($router_id, $data);
    }

    sleep(5);
}