<?php

require_once "../core/db.php";
require_once "../core/auth.php";
require_once "router_api_class.php";
require_once "hotspot_module.php";

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

function send($step, $status, $msg) {
    echo "data: " . json_encode([
        'step' => $step,
        'status' => $status,
        'message' => $msg
    ]) . "\n\n";
    flush();
}

$router_id = $_POST['router_id'];

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$r = $stmt->fetch();

if (!$r) {
    send('error', 'error', 'Router not found');
    exit;
}

try {

    // =========================
    // CONNECT
    // =========================
    send('connect', 'running', 'Connecting via WireGuard...');

    $api = new RouterAPI(
        $r['vpn_ip'],
        'admin',
        $r['api_pass']
    );

    send('connect', 'ok', 'Connected');

    // =========================
    // HOTSPOT STEP
    // =========================
    send('hotspot', 'running', 'Setting up hotspot + captive portal...');

    $msg = provision_hotspot($api);

    send('hotspot', 'ok', $msg);

    send('done', 'ok', 'Provision complete');

} catch (Exception $e) {
    send('error', 'error', $e->getMessage());
}