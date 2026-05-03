<?php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/router.php';

use RouterOS\Query;

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$body    = json_decode(file_get_contents('php://input'), true);

$action    = $body['action']    ?? '';
$router_id = (int)($body['router_id'] ?? 0);

if (!$router_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

/* Verify user owns this router */
$stmt = $pdo->prepare("SELECT router_id FROM user_router_access WHERE user_id = ? AND router_id = ?");
$stmt->execute([$user_id, $router_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$client = router_connect($router_id);
if (!$client) {
    echo json_encode(['success' => false, 'message' => 'Router unreachable']);
    exit;
}

try {
    switch ($action) {

        /* ══════════════════════════════════════════
           HOTSPOT — ACTIVE
        ══════════════════════════════════════════ */
        case 'kick_hotspot': {
            $q = new Query('/ip/hotspot/active/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           HOTSPOT — USERS (profiles)
        ══════════════════════════════════════════ */
        case 'get_hotspot_users': {
            $users = $client->query('/ip/hotspot/user/print')->read();
            echo json_encode(['success' => true, 'data' => $users]);
            break;
        }

        case 'add_hotspot_user': {
            $q = new Query('/ip/hotspot/user/add');
            $q->equal('name',     $body['name']     ?? '');
            $q->equal('password', $body['password'] ?? '');
            if (!empty($body['profile']))  $q->equal('profile',  $body['profile']);
            if (!empty($body['mac']))      $q->equal('mac-address', $body['mac']);
            if (!empty($body['comment']))  $q->equal('comment',  $body['comment']);
            if (!empty($body['limit_uptime']))    $q->equal('limit-uptime',    $body['limit_uptime']);
            if (!empty($body['limit_bytes_in']))  $q->equal('limit-bytes-in',  $body['limit_bytes_in']);
            if (!empty($body['limit_bytes_out'])) $q->equal('limit-bytes-out', $body['limit_bytes_out']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_hotspot_user': {
            $q = new Query('/ip/hotspot/user/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'enable_hotspot_user': {
            $q = new Query('/ip/hotspot/user/enable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'disable_hotspot_user': {
            $q = new Query('/ip/hotspot/user/disable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           HOTSPOT — HOSTS
        ══════════════════════════════════════════ */
        case 'get_hotspot_hosts': {
            $hosts = $client->query('/ip/hotspot/host/print')->read();
            echo json_encode(['success' => true, 'data' => $hosts]);
            break;
        }

        /* ══════════════════════════════════════════
           HOTSPOT — PROFILES
        ══════════════════════════════════════════ */
        case 'get_hotspot_profiles': {
            $profiles = $client->query('/ip/hotspot/user/profile/print')->read();
            echo json_encode(['success' => true, 'data' => $profiles]);
            break;
        }

        /* ══════════════════════════════════════════
           HOTSPOT — WALLED GARDEN
        ══════════════════════════════════════════ */
        case 'get_walled_garden': {
            $wg = $client->query('/ip/hotspot/walled-garden/print')->read();
            echo json_encode(['success' => true, 'data' => $wg]);
            break;
        }

        case 'add_walled_garden': {
            $q = new Query('/ip/hotspot/walled-garden/add');
            if (!empty($body['dst_host'])) $q->equal('dst-host', $body['dst_host']);
            if (!empty($body['dst_port'])) $q->equal('dst-port', $body['dst_port']);
            if (!empty($body['server']))   $q->equal('server',   $body['server']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_walled_garden': {
            $q = new Query('/ip/hotspot/walled-garden/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           IP BINDINGS
        ══════════════════════════════════════════ */
        case 'get_bindings': {
            $bindings = $client->query('/ip/hotspot/ip-binding/print')->read();
            echo json_encode(['success' => true, 'data' => $bindings]);
            break;
        }

        case 'add_binding': {
            $q = new Query('/ip/hotspot/ip-binding/add');
            if (!empty($body['mac']))    $q->equal('mac-address', $body['mac']);
            if (!empty($body['ip']))     $q->equal('address',     $body['ip']);
            if (!empty($body['server'])) $q->equal('server',      $body['server']);
            $q->equal('type', $body['type'] ?? 'bypassed');
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'block_binding': {
            $q = new Query('/ip/hotspot/ip-binding/set');
            $q->equal('.id', $body['id']); $q->equal('type', 'blocked');
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'unblock_binding': {
            $q = new Query('/ip/hotspot/ip-binding/set');
            $q->equal('.id', $body['id']); $q->equal('type', 'bypassed');
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_binding': {
            $q = new Query('/ip/hotspot/ip-binding/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           PPPoE — ACTIVE
        ══════════════════════════════════════════ */
        case 'kick_pppoe': {
            $q = new Query('/ppp/active/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           PPPoE — SECRETS (users)
        ══════════════════════════════════════════ */
        case 'get_pppoe_secrets': {
            $secrets = $client->query('/ppp/secret/print')->read();
            echo json_encode(['success' => true, 'data' => $secrets]);
            break;
        }

        case 'add_pppoe_secret': {
            $q = new Query('/ppp/secret/add');
            $q->equal('name',     $body['name']     ?? '');
            $q->equal('password', $body['password'] ?? '');
            $q->equal('service',  $body['service']  ?? 'pppoe');
            if (!empty($body['profile']))    $q->equal('profile',     $body['profile']);
            if (!empty($body['local_ip']))   $q->equal('local-address',  $body['local_ip']);
            if (!empty($body['remote_ip']))  $q->equal('remote-address', $body['remote_ip']);
            if (!empty($body['comment']))    $q->equal('comment',     $body['comment']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_pppoe_secret': {
            $q = new Query('/ppp/secret/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'enable_pppoe_secret': {
            $q = new Query('/ppp/secret/enable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'disable_pppoe_secret': {
            $q = new Query('/ppp/secret/disable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           PPPoE — PROFILES
        ══════════════════════════════════════════ */
        case 'get_pppoe_profiles': {
            $profiles = $client->query('/ppp/profile/print')->read();
            echo json_encode(['success' => true, 'data' => $profiles]);
            break;
        }

        /* ══════════════════════════════════════════
           DHCP — LEASES
        ══════════════════════════════════════════ */
        case 'get_dhcp_leases': {
            $leases = $client->query('/ip/dhcp-server/lease/print')->read();
            echo json_encode(['success' => true, 'data' => $leases]);
            break;
        }

        case 'make_static_lease': {
            $q = new Query('/ip/dhcp-server/lease/make-static');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_dhcp_lease': {
            $q = new Query('/ip/dhcp-server/lease/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           ARP TABLE
        ══════════════════════════════════════════ */
        case 'get_arp': {
            $arp = $client->query('/ip/arp/print')->read();
            echo json_encode(['success' => true, 'data' => $arp]);
            break;
        }

        /* ══════════════════════════════════════════
           NEIGHBORS
        ══════════════════════════════════════════ */
        case 'get_neighbors': {
            $neighbors = $client->query('/ip/neighbor/print')->read();
            echo json_encode(['success' => true, 'data' => $neighbors]);
            break;
        }

        /* ══════════════════════════════════════════
           FIREWALL — ADDRESS LISTS
        ══════════════════════════════════════════ */
        case 'get_firewall_address_lists': {
            $lists = $client->query('/ip/firewall/address-list/print')->read();
            echo json_encode(['success' => true, 'data' => $lists]);
            break;
        }

        case 'add_firewall_address': {
            $q = new Query('/ip/firewall/address-list/add');
            $q->equal('list',    $body['list']    ?? '');
            $q->equal('address', $body['address'] ?? '');
            if (!empty($body['comment'])) $q->equal('comment', $body['comment']);
            if (!empty($body['timeout'])) $q->equal('timeout', $body['timeout']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_firewall_address': {
            $q = new Query('/ip/firewall/address-list/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'enable_firewall_address': {
            $q = new Query('/ip/firewall/address-list/enable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'disable_firewall_address': {
            $q = new Query('/ip/firewall/address-list/disable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           FIREWALL — FILTER RULES (read-only view)
        ══════════════════════════════════════════ */
        case 'get_firewall_rules': {
            $rules = $client->query('/ip/firewall/filter/print')->read();
            echo json_encode(['success' => true, 'data' => $rules]);
            break;
        }

        /* ══════════════════════════════════════════
           FIREWALL — NAT RULES (read-only view)
        ══════════════════════════════════════════ */
        case 'get_nat_rules': {
            $rules = $client->query('/ip/firewall/nat/print')->read();
            echo json_encode(['success' => true, 'data' => $rules]);
            break;
        }

        /* ══════════════════════════════════════════
           INTERFACES
        ══════════════════════════════════════════ */
        case 'get_interfaces': {
            $ifaces = $client->query('/interface/print')->read();
            echo json_encode(['success' => true, 'data' => $ifaces]);
            break;
        }

        case 'enable_interface': {
            $q = new Query('/interface/enable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'disable_interface': {
            $q = new Query('/interface/disable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM LOG
        ══════════════════════════════════════════ */
        case 'get_logs': {
            $limit  = min((int)($body['limit'] ?? 100), 500);
            $q      = new Query('/log/print');
            $logs   = $client->query($q)->read();
            /* Return newest-first, capped */
            $logs = array_reverse(array_slice($logs, -$limit));
            echo json_encode(['success' => true, 'data' => $logs]);
            break;
        }

        /* ══════════════════════════════════════════
           TOOLS — PING
        ══════════════════════════════════════════ */
        case 'ping': {
            $host  = trim($body['host'] ?? '');
            $count = max(1, min((int)($body['count'] ?? 4), 10));
            if (!$host) throw new \Exception('Missing host');

            $q = new Query('/tool/ping');
            $q->equal('address', $host);
            $q->equal('count',   $count);
            $result = $client->query($q)->read();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
        }

        /* ══════════════════════════════════════════
           TOOLS — TRACEROUTE
        ══════════════════════════════════════════ */
        case 'traceroute': {
            $host = trim($body['host'] ?? '');
            if (!$host) throw new \Exception('Missing host');

            $q = new Query('/tool/traceroute');
            $q->equal('address', $host);
            $result = $client->query($q)->read();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM — REBOOT
        ══════════════════════════════════════════ */
        case 'reboot': {
            $client->query('/system/reboot')->read();
            echo json_encode(['success' => true, 'message' => 'Reboot command sent']);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM — RESOURCE INFO
        ══════════════════════════════════════════ */
        case 'get_resource': {
            $res = $client->query('/system/resource/print')->read();
            echo json_encode(['success' => true, 'data' => $res[0] ?? []]);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM — IDENTITY
        ══════════════════════════════════════════ */
        case 'get_identity': {
            $id = $client->query('/system/identity/print')->read();
            echo json_encode(['success' => true, 'data' => $id[0] ?? []]);
            break;
        }

        case 'set_identity': {
            $q = new Query('/system/identity/set');
            $q->equal('name', $body['name'] ?? '');
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           HOTSPOT — EDIT USER
        ══════════════════════════════════════════ */
        case 'edit_hotspot_user': {
            $q = new Query('/ip/hotspot/user/set');
            $q->equal('.id', $body['id'] ?? '');
            if (isset($body['password']))     $q->equal('password',      $body['password']);
            if (!empty($body['profile']))     $q->equal('profile',       $body['profile']);
            if (isset($body['mac']))          $q->equal('mac-address',   $body['mac']);
            if (isset($body['comment']))      $q->equal('comment',       $body['comment']);
            if (isset($body['limit_uptime'])) $q->equal('limit-uptime',  $body['limit_uptime']);
            if (isset($body['limit_bytes_in']))  $q->equal('limit-bytes-in',  $body['limit_bytes_in']);
            if (isset($body['limit_bytes_out'])) $q->equal('limit-bytes-out', $body['limit_bytes_out']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           PPPoE — EDIT SECRET
        ══════════════════════════════════════════ */
        case 'edit_pppoe_secret': {
            $q = new Query('/ppp/secret/set');
            $q->equal('.id', $body['id'] ?? '');
            if (isset($body['password']))   $q->equal('password',       $body['password']);
            if (!empty($body['profile']))   $q->equal('profile',        $body['profile']);
            if (!empty($body['service']))   $q->equal('service',        $body['service']);
            if (isset($body['local_ip']))   $q->equal('local-address',  $body['local_ip']);
            if (isset($body['remote_ip']))  $q->equal('remote-address', $body['remote_ip']);
            if (isset($body['comment']))    $q->equal('comment',        $body['comment']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           QUEUES — SIMPLE
        ══════════════════════════════════════════ */
        case 'get_queues': {
            $queues = $client->query('/queue/simple/print')->read();
            echo json_encode(['success' => true, 'data' => $queues]);
            break;
        }

        case 'add_queue': {
            $q = new Query('/queue/simple/add');
            $q->equal('name',   $body['name']   ?? '');
            $q->equal('target', $body['target'] ?? '');
            if (!empty($body['max_limit']))  $q->equal('max-limit',  $body['max_limit']);
            if (!empty($body['burst_limit'])) $q->equal('burst-limit', $body['burst_limit']);
            if (!empty($body['comment']))    $q->equal('comment',    $body['comment']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'edit_queue': {
            $q = new Query('/queue/simple/set');
            $q->equal('.id', $body['id'] ?? '');
            if (isset($body['max_limit']))   $q->equal('max-limit',  $body['max_limit']);
            if (isset($body['burst_limit'])) $q->equal('burst-limit',$body['burst_limit']);
            if (isset($body['comment']))     $q->equal('comment',    $body['comment']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_queue': {
            $q = new Query('/queue/simple/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'enable_queue': {
            $q = new Query('/queue/simple/enable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'disable_queue': {
            $q = new Query('/queue/simple/disable');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           QUEUES — TREE (read-only)
        ══════════════════════════════════════════ */
        case 'get_queue_tree': {
            $queues = $client->query('/queue/tree/print')->read();
            echo json_encode(['success' => true, 'data' => $queues]);
            break;
        }

        /* ══════════════════════════════════════════
           IP — ROUTES
        ══════════════════════════════════════════ */
        case 'get_routes': {
            $routes = $client->query('/ip/route/print')->read();
            echo json_encode(['success' => true, 'data' => $routes]);
            break;
        }

        /* ══════════════════════════════════════════
           IP — DNS CACHE
        ══════════════════════════════════════════ */
        case 'get_dns_cache': {
            $cache = $client->query('/ip/dns/cache/print')->read();
            echo json_encode(['success' => true, 'data' => $cache]);
            break;
        }

        case 'flush_dns_cache': {
            $client->query('/ip/dns/cache/flush')->read();
            echo json_encode(['success' => true, 'message' => 'DNS cache flushed']);
            break;
        }

        /* ══════════════════════════════════════════
           IP — DNS SETTINGS (read)
        ══════════════════════════════════════════ */
        case 'get_dns_settings': {
            $dns = $client->query('/ip/dns/print')->read();
            echo json_encode(['success' => true, 'data' => $dns[0] ?? []]);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM — USERS
        ══════════════════════════════════════════ */
        case 'get_system_users': {
            $users = $client->query('/user/print')->read();
            echo json_encode(['success' => true, 'data' => $users]);
            break;
        }

        case 'add_system_user': {
            $q = new Query('/user/add');
            $q->equal('name',     $body['name']     ?? '');
            $q->equal('password', $body['password'] ?? '');
            $q->equal('group',    $body['group']    ?? 'read');
            if (!empty($body['comment'])) $q->equal('comment', $body['comment']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete_system_user': {
            $q = new Query('/user/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM — SCHEDULER (read)
        ══════════════════════════════════════════ */
        case 'get_scheduler': {
            $jobs = $client->query('/system/scheduler/print')->read();
            echo json_encode(['success' => true, 'data' => $jobs]);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM — SCRIPTS (read)
        ══════════════════════════════════════════ */
        case 'get_scripts': {
            $scripts = $client->query('/system/script/print')->read();
            echo json_encode(['success' => true, 'data' => $scripts]);
            break;
        }

        case 'run_script': {
            $q = new Query('/system/script/run');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM — BACKUP (generate & list)
        ══════════════════════════════════════════ */
        case 'create_backup': {
            $q = new Query('/system/backup/save');
            if (!empty($body['name'])) $q->equal('name', $body['name']);
            $client->query($q)->read();
            echo json_encode(['success' => true, 'message' => 'Backup created']);
            break;
        }

        /* ══════════════════════════════════════════
           TOOLS — TRACEROUTE (result polling)
        ══════════════════════════════════════════ */
        case 'traceroute': {
            $host = trim($body['host'] ?? '');
            if (!$host) throw new \Exception('Missing host');
            $q = new Query('/tool/traceroute');
            $q->equal('address', $host);
            if (!empty($body['count'])) $q->equal('count', (int)$body['count']);
            $result = $client->query($q)->read();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
        }

        /* ══════════════════════════════════════════
           TOOLS — BANDWIDTH TEST (start)
        ══════════════════════════════════════════ */
        case 'bandwidth_test': {
            $q = new Query('/tool/bandwidth-test');
            $q->equal('address',  $body['address']  ?? '');
            $q->equal('protocol', $body['protocol'] ?? 'udp');
            $q->equal('duration', $body['duration'] ?? '5');
            $result = $client->query($q)->read();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
        }

        /* ══════════════════════════════════════════
           WIRELESS — STATION LIST (read)
        ══════════════════════════════════════════ */
        case 'get_wireless_stations': {
            $stations = $client->query('/interface/wireless/registration-table/print')->read();
            echo json_encode(['success' => true, 'data' => $stations]);
            break;
        }

        case 'get_wireless_interfaces': {
            $ifaces = $client->query('/interface/wireless/print')->read();
            echo json_encode(['success' => true, 'data' => $ifaces]);
            break;
        }

        /* ══════════════════════════════════════════
           HOTSPOT — SERVER PROFILES
        ══════════════════════════════════════════ */
        case 'get_hotspot_server_profiles': {
            $profiles = $client->query('/ip/hotspot/profile/print')->read();
            echo json_encode(['success' => true, 'data' => $profiles]);
            break;
        }

        /* ══════════════════════════════════════════
           PPPoE — ACTIVE with secret cross-ref
        ══════════════════════════════════════════ */
        case 'get_pppoe_active_detail': {
            $active  = $client->query('/ppp/active/print')->read();
            $secrets = $client->query('/ppp/secret/print')->read();
            /* Index secrets by name for quick lookup */
            $secretMap = [];
            foreach ($secrets as $s) $secretMap[$s['name']] = $s;
            foreach ($active as &$a) {
                $a['_profile'] = $secretMap[$a['name']]['profile'] ?? '—';
                $a['_comment'] = $secretMap[$a['name']]['comment'] ?? '';
            }
            echo json_encode(['success' => true, 'data' => $active]);
            break;
        }

        /* ══════════════════════════════════════════
           HOTSPOT — ACTIVE with comment cross-ref
        ══════════════════════════════════════════ */
        case 'get_hotspot_active_detail': {
            $active = $client->query('/ip/hotspot/active/print')->read();
            $users  = $client->query('/ip/hotspot/user/print')->read();
            $userMap = [];
            foreach ($users as $u) $userMap[$u['name']] = $u;
            foreach ($active as &$a) {
                $a['_profile'] = $userMap[$a['user']]['profile'] ?? '—';
                $a['_comment'] = $userMap[$a['user']]['comment'] ?? '';
            }
            echo json_encode(['success' => true, 'data' => $active]);
            break;
        }

        /* ══════════════════════════════════════════
           SYSTEM — CLOCK
        ══════════════════════════════════════════ */
        case 'get_clock': {
            $clock = $client->query('/system/clock/print')->read();
            echo json_encode(['success' => true, 'data' => $clock[0] ?? []]);
            break;
        }

        case 'set_clock': {
            $q = new Query('/system/clock/set');
            if (!empty($body['time'])) $q->equal('time', $body['time']);
            if (!empty($body['date'])) $q->equal('date', $body['date']);
            if (!empty($body['time_zone_name'])) $q->equal('time-zone-name', $body['time_zone_name']);
            $client->query($q)->read();
            echo json_encode(['success' => true]);
            break;
        }

        /* ══════════════════════════════════════════
           SNMP (read)
        ══════════════════════════════════════════ */
        case 'get_snmp': {
            $snmp = $client->query('/snmp/print')->read();
            echo json_encode(['success' => true, 'data' => $snmp[0] ?? []]);
            break;
        }

        /* ══════════════════════════════════════════
           IP — POOLS
        ══════════════════════════════════════════ */
        case 'get_ip_pools': {
            $pools = $client->query('/ip/pool/print')->read();
            echo json_encode(['success' => true, 'data' => $pools]);
            break;
        }

        /* ══════════════════════════════════════════
           DHCP — SERVER LIST
        ══════════════════════════════════════════ */
        case 'get_dhcp_servers': {
            $servers = $client->query('/ip/dhcp-server/print')->read();
            echo json_encode(['success' => true, 'data' => $servers]);
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}