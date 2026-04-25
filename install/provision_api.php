<?php
require_once "../core/db.php";
require_once "../core/auth.php";

require_once __DIR__ . '/../vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config as RouterConfig;
use RouterOS\Query;

if (!is_logged_in()) { http_response_code(401); exit; }

$router_id = $_POST['router_id'] ?? null;
if (!$router_id) { echo "data: " . json_encode(['error' => 'Missing router_id']) . "\n\n"; exit; }

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();
if (!$router) { echo "data: " . json_encode(['error' => 'Router not found']) . "\n\n"; exit; }

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

set_time_limit(300);
ini_set('default_socket_timeout', 300);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function send($step, $status, $message) {
    echo "data: " . json_encode(['step' => $step, 'status' => $status, 'message' => $message]) . "\n\n";
    ob_flush(); flush();
}

function make_client($host, $pass, $port = 8728) {
    $config = new RouterConfig([
        'host'           => $host,
        'user'           => 'admin',
        'pass'           => $pass,
        'port'           => $port,
        'timeout'        => 30,
        'socket_timeout' => 300,
    ]);
    return new Client($config);
}

function api_run($client, $path, $params = []) {
    $q = new Query($path);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') continue;
        $q->equal($k, $v);
    }
    
    try {
        $result = $client->query($q)->read();
        
        if (is_array($result)) {
            foreach ($result as $row) {
                if (isset($row['!trap'])) {
                    $msg = $row['message'] ?? (is_string($row['!trap']) ? $row['!trap'] : json_encode($row));
                    throw new \RuntimeException("RouterOS [{$path}]: {$msg}");
                }
                if (isset($row['.type']) && $row['.type'] === '!trap') {
                    $msg = $row['message'] ?? 'Unknown error';
                    throw new \RuntimeException("RouterOS [{$path}]: {$msg}");
                }
            }
        }
        return $result;
    } catch (Exception $e) {
        throw new \RuntimeException("API command {$path} failed: " . $e->getMessage());
    }
}

function api_get($client, $path) {
    return $client->query(new Query("$path/print"))->read();
}

function api_removeByComment($client, $path, $prefix = 'inovatech') {
    if (in_array($path, ['/ip/hotspot', '/ip/hotspot/profile'])) return;
    foreach (api_get($client, $path) as $row) {
        if (isset($row['.id']) && str_starts_with($row['comment'] ?? '', $prefix)) {
            try { api_run($client, "$path/remove", ['.id' => $row['.id']]); } catch (Exception $e) {}
        }
    }
}

function ensure($client, $path, $matchKey, $matchVal, $params) {
    foreach (api_get($client, $path) as $row) {
        if (($row[$matchKey] ?? '') === $matchVal) {
            api_run($client, "$path/set", array_merge(['.id' => $row['.id']], $params));
            return;
        }
    }
    api_run($client, "$path/add", array_merge([$matchKey => $matchVal], $params));
}

// ---------------------------------------------------------------------------
// Network topology configuration
// ---------------------------------------------------------------------------

$BRIDGE        = "bridge";
$LAN_IP        = "192.168.50.1";
$LAN_NET       = "192.168.50.0/24";

$HS_POOL       = "192.168.50.10-192.168.50.150";
$HS_SSID       = "Inovatech WiFi";
$HS_DNS_NAME   = "hotspot.inovatech.co.ke";

$PPPOE_POOL    = "192.168.50.200-192.168.50.250";
$PPPOE_PROFILE = "inovatech-pppoe";

$WG_IFACE      = "wg-billing";
$WG_SUBNET     = $router['vpn_subnet'] ?? "10.50.0.0/24";
$VPS_IP        = "102.68.86.80";

$VPN_IP        = $router['vpn_ip'];
$API_PASS      = $router['api_pass'];
$API_PORT      = 8728;

// ---------------------------------------------------------------------------
// Provision
// ---------------------------------------------------------------------------
try {

    send('connect', 'running', "Connecting to {$router['name']} via VPN ({$VPN_IP})...");
    $client = make_client($VPN_IP, $API_PASS, $API_PORT);
    send('connect', 'ok', "Connected");

    // =========================================================================
    // STEP 1 — WiFi
    // =========================================================================

    send('hotspot', 'running', "Configuring wlan1 SSID...");
    foreach (api_get($client, '/interface/wireless') as $w) {
        if (($w['name'] ?? '') === 'wlan1' && isset($w['.id'])) {
            api_run($client, '/interface/wireless/set', [
                '.id'      => $w['.id'],
                'ssid'     => $HS_SSID,
                'mode'     => 'ap-bridge',
                'disabled' => 'no',
            ]);
        }
    }

    // =========================================================================
    // STEP 2 — Hotspot
    // =========================================================================

    send('hotspot', 'running', "Creating hotspot IP pool...");
    ensure($client, '/ip/pool', 'name', 'hotspot_pool', [
        'ranges'  => $HS_POOL,
        'comment' => 'inovatech-hotspot',
    ]);

    send('hotspot', 'running', "Creating DHCP server on bridge...");
    ensure($client, '/ip/dhcp-server', 'name', 'dhcp-bridge', [
        'interface'    => $BRIDGE,
        'address-pool' => 'hotspot_pool',
        'disabled'     => 'no',
        'lease-time'   => '1h',
        'comment'      => 'inovatech-hotspot',
    ]);
    ensure($client, '/ip/dhcp-server/network', 'address', $LAN_NET, [
        'gateway'    => $LAN_IP,
        'dns-server' => '8.8.8.8,8.8.4.4',
        'comment'    => 'inovatech-hotspot',
    ]);

    send('hotspot', 'running', "Creating hotspot profile...");
    
    foreach (api_get($client, '/ip/hotspot/profile') as $row) {
        if (($row['name'] ?? '') === 'inovatech-profile' && isset($row['.id'])) {
            api_run($client, '/ip/hotspot/profile/remove', ['.id' => $row['.id']]);
        }
    }
    
    // Try minimal profile first
    try {
        api_run($client, '/ip/hotspot/profile/add', [
            'name'            => 'inovatech-profile',
            'hotspot-address' => $LAN_IP,
            'login-by'        => 'http-chap,cookie',
        ]);
    } catch (Exception $e) {
        // Fallback to even more minimal
        api_run($client, '/ip/hotspot/profile/add', [
            'name'            => 'inovatech-profile',
            'hotspot-address' => $LAN_IP,
        ]);
    }
    
    // Update with remaining settings
    try {
        api_run($client, '/ip/hotspot/profile/set', [
            '.id'                  => 'inovatech-profile',
            'dns-name'             => $HS_DNS_NAME,
            'http-cookie-lifetime' => '1d',
            'use-radius'           => 'no',
            'comment'              => 'inovatech-hotspot',
        ]);
    } catch (Exception $e) {
        // Non-critical
    }

    send('hotspot', 'running', "Creating hotspot server...");
    
    foreach (api_get($client, '/ip/hotspot') as $row) {
        if (($row['name'] ?? '') === 'hotspot1' && isset($row['.id'])) {
            api_run($client, '/ip/hotspot/remove', ['.id' => $row['.id']]);
        }
    }
    
    $serverCreated = false;
    
    // Try approach 1: Full command
    try {
        api_run($client, '/ip/hotspot/add', [
            'name'         => 'hotspot1',
            'interface'    => $BRIDGE,
            'profile'      => 'inovatech-profile',
            'address-pool' => 'hotspot_pool',
            'dns-name'     => $HS_DNS_NAME,
            'disabled'     => 'no',
            'comment'      => 'inovatech-hotspot',
        ]);
        $serverCreated = true;
    } catch (Exception $e) {
        // Try approach 2: Without address-pool first
        try {
            api_run($client, '/ip/hotspot/add', [
                'name'      => 'hotspot1',
                'interface' => $BRIDGE,
                'profile'   => 'inovatech-profile',
                'dns-name'  => $HS_DNS_NAME,
                'disabled'  => 'yes',
                'comment'   => 'inovatech-hotspot',
            ]);
            api_run($client, '/ip/hotspot/set', [
                '.id'          => 'hotspot1',
                'address-pool' => 'hotspot_pool',
                'disabled'     => 'no',
            ]);
            $serverCreated = true;
        } catch (Exception $e2) {
            // Try approach 3: Without profile first
            try {
                api_run($client, '/ip/hotspot/add', [
                    'name'         => 'hotspot1',
                    'interface'    => $BRIDGE,
                    'address-pool' => 'hotspot_pool',
                    'dns-name'     => $HS_DNS_NAME,
                    'disabled'     => 'yes',
                    'comment'      => 'inovatech-hotspot',
                ]);
                api_run($client, '/ip/hotspot/set', [
                    '.id'      => 'hotspot1',
                    'profile'  => 'inovatech-profile',
                    'disabled' => 'no',
                ]);
                $serverCreated = true;
            } catch (Exception $e3) {
                send('hotspot', 'error', "Could not create hotspot server automatically. Please use Fix C in Step 6.");
            }
        }
    }
    
    if ($serverCreated) {
        send('hotspot', 'running', "Configuring walled garden...");
        try {
            ensure($client, '/ip/hotspot/walled-garden', 'comment', 'inovatech-wg-wildcard', ['dst-host' => '*.inovatech.co.ke', 'action' => 'allow']);
            ensure($client, '/ip/hotspot/walled-garden', 'comment', 'inovatech-wg-bare', ['dst-host' => 'inovatech.co.ke', 'action' => 'allow']);
            ensure($client, '/ip/hotspot/walled-garden/ip', 'comment', 'inovatech-wg-vps', ['dst-address' => $VPS_IP, 'action' => 'accept']);
        } catch (Exception $e) {
            // Non-critical
        }
        send('hotspot', 'ok', "Hotspot ready — {$HS_SSID}");
    }

    // =========================================================================
    // STEP 3 — PPPoE
    // =========================================================================

    send('pppoe', 'running', "Creating PPPoE IP pool...");
    ensure($client, '/ip/pool', 'name', 'pppoe_pool', [
        'ranges'  => $PPPOE_POOL,
        'comment' => 'inovatech-pppoe',
    ]);

    send('pppoe', 'running', "Creating PPPoE profile...");
    ensure($client, '/ppp/profile', 'name', $PPPOE_PROFILE, [
        'local-address'   => $LAN_IP,
        'remote-address'  => 'pppoe_pool',
        'use-compression' => 'no',
        'use-encryption'  => 'no',
        'dns-server'      => '8.8.8.8,8.8.4.4',
        'comment'         => 'inovatech-pppoe',
    ]);

    send('pppoe', 'running', "Creating PPPoE server...");
    ensure($client, '/interface/pppoe-server/server', 'service-name', 'PPPoE_Inovatech', [
        'interface'        => $BRIDGE,
        'default-profile'  => $PPPOE_PROFILE,
        'authentication'   => 'chap,mschap2',
        'one-session-per-host' => 'yes',
        'disabled'         => 'no',
        'comment'          => 'inovatech-pppoe',
    ]);

    send('pppoe', 'ok', "PPPoE server ready");

    // =========================================================================
    // STEP 4 — Firewall, DNS, Identity
    // =========================================================================

    send('firewall', 'running', "Clearing existing inovatech firewall rules...");
    api_removeByComment($client, '/ip/firewall/filter');
    api_removeByComment($client, '/ip/firewall/nat');

    send('firewall', 'running', "Writing firewall rules...");

    $filter_rules = [
        ['chain' => 'input', 'in-interface' => $WG_IFACE, 'action' => 'accept', 'comment' => 'inovatech-vpn-accept'],
        ['chain' => 'input', 'connection-state' => 'established,related', 'action' => 'accept', 'comment' => 'inovatech-input-est'],
        ['chain' => 'input', 'connection-state' => 'invalid', 'action' => 'drop', 'comment' => 'inovatech-input-invalid'],
        ['chain' => 'input', 'protocol' => 'tcp', 'dst-port' => '8291', 'action' => 'accept', 'comment' => 'inovatech-winbox'],
        ['chain' => 'input', 'protocol' => 'udp', 'dst-port' => '53', 'action' => 'accept', 'comment' => 'inovatech-dns-udp'],
        ['chain' => 'input', 'protocol' => 'tcp', 'dst-port' => '53', 'action' => 'accept', 'comment' => 'inovatech-dns-tcp'],
        ['chain' => 'input', 'protocol' => 'tcp', 'dst-port' => '8728', 'src-address' => $WG_SUBNET, 'action' => 'accept', 'comment' => 'inovatech-api-vpn'],
        ['chain' => 'input', 'protocol' => 'tcp', 'dst-port' => '8728', 'action' => 'drop', 'comment' => 'inovatech-api-drop'],
        ['chain' => 'input', 'protocol' => 'udp', 'dst-port' => '67', 'in-interface' => $BRIDGE, 'action' => 'accept', 'comment' => 'inovatech-dhcp'],
        ['chain' => 'input', 'action' => 'drop', 'comment' => 'inovatech-input-drop'],
        ['chain' => 'forward', 'protocol' => 'tcp', 'connection-limit' => '50,32', 'action' => 'drop', 'comment' => 'inovatech-conn-limit'],
        ['chain' => 'forward', 'in-interface' => $BRIDGE, 'action' => 'accept', 'comment' => 'inovatech-forward-bridge'],
        ['chain' => 'forward', 'connection-state' => 'established,related', 'action' => 'accept', 'comment' => 'inovatech-forward-est'],
        ['chain' => 'forward', 'connection-state' => 'invalid', 'action' => 'drop', 'comment' => 'inovatech-forward-invalid'],
        ['chain' => 'forward', 'action' => 'drop', 'comment' => 'inovatech-forward-drop'],
    ];

    foreach ($filter_rules as $rule) {
        api_run($client, '/ip/firewall/filter/add', $rule);
    }

    send('firewall', 'running', "Writing NAT rules...");

    $nat_rules = [
        ['chain' => 'srcnat', 'action' => 'masquerade', 'out-interface' => 'ether1', 'comment' => 'inovatech-masquerade'],
    ];

    foreach ($nat_rules as $rule) {
        api_run($client, '/ip/firewall/nat/add', $rule);
    }

    send('firewall', 'running', "Setting DNS and system identity...");
    api_run($client, '/ip/dns/set', [
        'allow-remote-requests' => 'yes',
        'servers'               => '8.8.8.8,8.8.4.4',
    ]);
    api_run($client, '/system/identity/set', ['name' => $router['name']]);

    send('firewall', 'ok', "Firewall, NAT, DNS, identity applied");

    // =========================================================================
    // Mark provisioned in DB
    // =========================================================================
    $pdo->prepare("UPDATE routers SET provisioned=1, provisioned_at=NOW() WHERE router_id=?")
        ->execute([$router_id]);

    send('done', 'ok', "✅ {$router['name']} provisioned successfully");

} catch (Exception $e) {
    send('error', 'error', $e->getMessage());
}
?>