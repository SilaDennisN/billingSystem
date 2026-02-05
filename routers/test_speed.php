<?php
session_start();

require_once "../core/db.php";
require_once "../core/router.php";

use RouterOS\Query;

header("Content-Type: application/json");

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['online' => false, 'speed' => 0]);
    exit;
}

/* =========================
   Connect to router
========================= */
$client = router_connect($id);
if (!$client) {
    echo json_encode([
        'online' => false,
        'status' => 'Offline',
        'download' => 0,
        'upload' => 0,
        'idle' => false
    ]);
    exit;
}

/* =========================
   Get WAN traffic
========================= */
$query = new Query('/interface/monitor-traffic');
$query->equal('interface', 'ether1'); // CHANGE IF NEEDED
$query->equal('once', '');

$res = $client->query($query)->read();

$rxBps = (int)($res[0]['rx-bits-per-second'] ?? 0);
$txBps = (int)($res[0]['tx-bits-per-second'] ?? 0);

$rxMbps = $rxBps / 1_000_000;
$txMbps = $txBps / 1_000_000;

/* =========================
   SESSION STORAGE
========================= */
$_SESSION['router_stats'] ??= [];

$stats = &$_SESSION['router_stats'][$id];
$stats ??= [
    'rx_ema' => 0,
    'tx_ema' => 0,
    'idle_count' => 0
];

/* =========================
   SMART SMOOTHING (EMA)
========================= */
$alpha = 0.3; // smoothing factor (0.2–0.4 ideal)

$stats['rx_ema'] = ($rxMbps * $alpha) + ($stats['rx_ema'] * (1 - $alpha));
$stats['tx_ema'] = ($txMbps * $alpha) + ($stats['tx_ema'] * (1 - $alpha));

$download = round($stats['rx_ema'], 2);
$upload   = round($stats['tx_ema'], 2);

/* =========================
   IDLE DETECTION
========================= */
if ($download < 0.1 && $upload < 0.1) {
    $stats['idle_count']++;
} else {
    $stats['idle_count'] = 0;
}

$isIdle = $stats['idle_count'] >= 3; // 3 polls × 5s = 15s idle

/* =========================
   Update router table
========================= */
$pdo->prepare("
    UPDATE routers 
    SET online_status='online', last_seen=NOW()
    WHERE router_id=?
")->execute([$id]);

echo json_encode([
    'online' => true,
    'status' => $isIdle ? 'Idle' : 'Online',
    'download' => $download,
    'upload' => $upload,
    'speed' => $download + $upload, // backward compatibility
    'idle' => $isIdle,
    'last_seen' => date('Y-m-d H:i:s')
]);
