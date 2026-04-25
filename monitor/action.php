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

        /* ── Kick hotspot user ── */
        case 'kick_hotspot': {
            $name = $body['name'] ?? '';
            if (!$name) throw new \Exception('Missing name');

            $q = new Query('/ip/hotspot/active/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();

            echo json_encode(['success' => true]);
            break;
        }

        /* ── Kick PPPoE user ── */
        case 'kick_pppoe': {
            $q = new Query('/ppp/active/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();

            echo json_encode(['success' => true]);
            break;
        }

        /* ── Get IP bindings ── */
        case 'get_bindings': {
            $bindings = $client->query('/ip/hotspot/ip-binding/print')->read();
            echo json_encode(['success' => true, 'data' => $bindings]);
            break;
        }

        /* ── Add binding ── */
        case 'add_binding': {
            $q = new Query('/ip/hotspot/ip-binding/add');
            if (!empty($body['mac']))     $q->equal('mac-address', $body['mac']);
            if (!empty($body['ip']))      $q->equal('address',     $body['ip']);
            if (!empty($body['server']))  $q->equal('server',      $body['server']);
            $q->equal('type', $body['type'] ?? 'bypassed');
            $client->query($q)->read();

            echo json_encode(['success' => true]);
            break;
        }

        /* ── Block binding ── */
        case 'block_binding': {
            $q = new Query('/ip/hotspot/ip-binding/set');
            $q->equal('.id',  $body['id']);
            $q->equal('type', 'blocked');
            $client->query($q)->read();

            echo json_encode(['success' => true]);
            break;
        }

        /* ── Unblock binding (set to bypassed) ── */
        case 'unblock_binding': {
            $q = new Query('/ip/hotspot/ip-binding/set');
            $q->equal('.id',  $body['id']);
            $q->equal('type', 'bypassed');
            $client->query($q)->read();

            echo json_encode(['success' => true]);
            break;
        }

        /* ── Delete binding ── */
        case 'delete_binding': {
            $q = new Query('/ip/hotspot/ip-binding/remove');
            $q->equal('.id', $body['id']);
            $client->query($q)->read();

            echo json_encode(['success' => true]);
            break;
        }

        /* ── Get IP neighbors ── */
        case 'get_neighbors': {
            $neighbors = $client->query('/ip/neighbor/print')->read();
            echo json_encode(['success' => true, 'data' => $neighbors]);
            break;
        }

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}