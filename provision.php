<?php
/**
 * Router Provisioning Endpoint
 * Usage: provision.php?step=all or step=firewall-input, etc.
 */

require_once "../core/db.php";
require_once "../core/auth.php";

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/RouterAPI.php';
require_once __DIR__ . '/RouterProvisioner.php';

if (!is_logged_in()) { 
    http_response_code(401); 
    exit; 
}

$router_id = $_GET['router_id'] ?? $_POST['router_id'] ?? null;
if (!$router_id) { 
    http_response_code(400);
    echo json_encode(['error' => 'Missing router_id']);
    exit; 
}

// Get router from database
$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();

if (!$router) { 
    http_response_code(404);
    echo json_encode(['error' => 'Router not found']);
    exit; 
}

// Determine which step(s) to run
$step = $_GET['step'] ?? $_POST['step'] ?? 'all';

// =========================================================================
// SSE Headers for real-time updates
// =========================================================================

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

set_time_limit(300);
ini_set('default_socket_timeout', 300);

// =========================================================================
// Connect to Router
// =========================================================================

$api = new RouterAPI(
    $router['vpn_ip'],
    $router['api_pass'],
    'admin',
    8728
);

if (!$api->connect()) {
    echo "data: " . json_encode([
        'step'    => 'connect',
        'status'  => 'error',
        'message' => 'Failed to connect to router: ' . $api->getLastError()
    ]) . "\n\n";
    exit;
}

echo "data: " . json_encode([
    'step'    => 'connect',
    'status'  => 'ok',
    'message' => "Connected to {$router['name']} via VPN ({$router['vpn_ip']})"
]) . "\n\n";
ob_flush();
flush();

// =========================================================================
// Provision
// =========================================================================

$provisioner = new RouterProvisioner($api, [
    'prefix' => 'inovatech',
    'lan_ip' => $router['lan_ip'] ?? '192.168.50.1',
    'wan_interface' => 'ether1',
]);

// Map of steps
$steps_map = [
    'firewall-input'   => [$provisioner, 'stepFirewallInput'],
    'firewall-forward' => [$provisioner, 'stepFirewallForward'],
    'firewall-nat'     => [$provisioner, 'stepFirewallNAT'],
    'dns'              => [$provisioner, 'stepDNS'],
    'identity'         => [$provisioner, 'stepIdentity', $router['name']],
    'bridge'           => [$provisioner, 'stepBridge'],
    'bridge-ip'        => [$provisioner, 'stepBridgeIP'],
];

if ($step === 'all') {
    // Run all basic steps
    $success = $provisioner->provisionBasics($router['name']);
    
    if ($success) {
        // Mark as provisioned in DB
        $pdo->prepare("UPDATE routers SET provisioned=1, provisioned_at=NOW() WHERE router_id=?")
            ->execute([$router_id]);
            
        echo "data: " . json_encode([
            'step'    => 'done',
            'status'  => 'ok',
            'message' => "✅ {$router['name']} provisioned successfully"
        ]) . "\n\n";
    } else {
        echo "data: " . json_encode([
            'step'    => 'done',
            'status'  => 'error',
            'message' => "❌ Provisioning incomplete - check errors above"
        ]) . "\n\n";
    }
    
} elseif (isset($steps_map[$step])) {
    // Run single step
    $callable = $steps_map[$step];
    
    if (count($callable) === 3) {
        // Step with parameters
        call_user_func($callable[0], $callable[1], $callable[2]);
    } else {
        // Step without parameters
        call_user_func($callable);
    }
    
} else {
    echo "data: " . json_encode([
        'step'    => 'error',
        'status'  => 'error',
        'message' => "Unknown step: {$step}"
    ]) . "\n\n";
}

ob_flush();
flush();
?>
