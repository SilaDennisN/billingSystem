<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();
require_once "../core/db.php";
require_once "../core/auth.php";
ob_end_clean();

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$router_id = $_GET['router_id'] ?? null;
if (!$router_id) {
    echo json_encode(['error' => 'Missing router_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();

if (!$router) {
    echo json_encode(['error' => 'Router not found']);
    exit;
}

$vpn_ip   = $router['vpn_ip'];
$api_user = $router['api_user'];
$api_pass = $router['api_pass'];

$results = [];

// ── Test 1: ICMP ping via exec
$results['vpn_ip'] = $vpn_ip;
$results['api_user'] = $api_user;

$ping = shell_exec("ping -c 2 -W 2 " . escapeshellarg($vpn_ip) . " 2>&1");
$results['ping'] = [
    'reachable' => str_contains($ping, '2 received') || str_contains($ping, '1 received'),
    'output'    => trim($ping),
];

// ── Test 2: TCP port check — try common ports
$ports = [8728, 8729, 21, 22, 23, 80, 8080];
$results['ports'] = [];
foreach ($ports as $port) {
    $conn = @fsockopen($vpn_ip, $port, $errno, $errstr, 3);
    $results['ports'][$port] = $conn ? 'open' : "closed ({$errstr})";
    if ($conn) fclose($conn);
}

// ── Test 3: If 8728 open, try API login
if (($results['ports'][8728] ?? '') === 'open') {
    $sock = @fsockopen($vpn_ip, 8728, $errno, $errstr, 5);
    if ($sock) {
        stream_set_timeout($sock, 5);
        // Read any banner
        $banner = @fread($sock, 256);
        $results['api_banner'] = bin2hex($banner);
        fclose($sock);
    }
}

// ── Test 4: Check VPS WireGuard interface
$wg = shell_exec("wg show wg0 2>&1");
if (!$wg) $wg = shell_exec("wg show 2>&1");
$results['wg_show'] = trim($wg ?: 'wg command not available');

// ── Test 5: Route to VPN IP
$route = shell_exec("ip route get " . escapeshellarg($vpn_ip) . " 2>&1");
$results['route_to_vpn'] = trim($route ?: 'ip command not available');

echo json_encode($results, JSON_PRETTY_PRINT);