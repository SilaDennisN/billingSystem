<?php
/**
 * profiles/fetch.php
 * AJAX endpoint – returns router profiles as JSON.
 * Called by profiles/index.php via fetch()
 */

require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

use RouterOS\Query;

/* ── Always return JSON ── */
header('Content-Type: application/json');

/* ── Auth guard ── */
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised. Please log in again.']);
    exit;
}

$user_id         = $_SESSION['user']['id'];
$selected_router = $_GET['router_id'] ?? null;
check_subscription_gate($pdo, $user_id);
/* ── Validate input ── */
if (!$selected_router) {
    echo json_encode(['error' => 'No router selected.']);
    exit;
}

/* ── Verify router belongs to this user and is active ── */
$stmt = $pdo->prepare("
    SELECT r.*
    FROM routers r
    INNER JOIN user_router_access ura ON ura.router_id = r.router_id
    WHERE r.router_id = ?
      AND ura.user_id  = ?
      AND r.status     = 'active'
    LIMIT 1
");
$stmt->execute([$selected_router, $user_id]);
$router = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$router) {
    echo json_encode(['error' => 'Router not found or access denied.']);
    exit;
}

/* ── Pull DB-configured profiles for this router ── */
$dbStmt = $pdo->prepare("SELECT * FROM hotspot_profiles WHERE router_id = ?");
$dbStmt->execute([$selected_router]);
$dbIndex = [];
foreach ($dbStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $dbIndex[$p['profile_name']] = $p;
}

/* ── Connect to router (timeout set inside router_connect) ── */
$client = router_connect($selected_router);

if (!$client) {
    echo json_encode(['error' => 'Router is unreachable. Check connection or credentials.']);
    exit;
}

/* ── Fetch profiles from RouterOS ── */
$hotspotRaw  = [];
$pppoeRaw    = [];
$fetchErrors = [];

try {
    $clientHotspot = router_connect($selected_router);
    if ($clientHotspot) {
        $hotspotRaw = $clientHotspot->query(
            new Query('/ip/hotspot/user/profile/print')
        )->read();
    } else {
        $fetchErrors[] = 'Hotspot: could not connect to router.';
    }
} catch (Exception $e) {
    $fetchErrors[] = 'Hotspot profiles unavailable.';
}

try {
    $clientPppoe = router_connect($selected_router);
    if ($clientPppoe) {
        $pppoeRaw = $clientPppoe->query(
            new Query('/ppp/profile/print')
        )->read();
    } else {
        $fetchErrors[] = 'PPPoE: could not connect to router.';
    }
} catch (Exception $e) {
    $fetchErrors[] = 'PPPoE profiles unavailable.';
}

/* ── Merge and filter ── */
$profiles     = [];
$hotspotCount = 0;
$pppoeCount   = 0;

foreach ($hotspotRaw as $p) {
    $merged = mergeProfile($p, 'hotspot', $dbIndex);
    if (!$merged['in_db']) continue;   // skip profiles not configured in DB
    unset($merged['in_db']);           // don't expose internal flag to client
    $profiles[] = $merged;
    $hotspotCount++;
}

foreach ($pppoeRaw as $p) {
    $merged = mergeProfile($p, 'pppoe', $dbIndex);
    if (!$merged['in_db']) continue;
    unset($merged['in_db']);
    $profiles[] = $merged;
    $pppoeCount++;
}

/* ── Respond ── */
echo json_encode([
    'profiles'     => $profiles,
    'hotspotCount' => $hotspotCount,
    'pppoeCount'   => $pppoeCount,
    'total'        => count($profiles),
    'warnings'     => $fetchErrors,   // partial failure info (optional use in frontend)
]);
exit;

/* ── Helper ── */
function mergeProfile(array $routerProfile, string $type, array $dbIndex): array
{
    $name = $routerProfile['name'] ?? '';
    $db   = $dbIndex[$name] ?? [];

    return [
        'name'         => $name,
        'type'         => $type,
        'rate_limit'   => $routerProfile['rate-limit']   ?? '-',
        'shared_users' => $routerProfile['shared-users'] ?? '-',
        'price'        => $db['price']          ?? '—',
        'validity'     => isset($db['validity_days'])
            ? "{$db['validity_days']}d {$db['validity_hours']}h"
            : '—',
        'in_db'        => isset($db['profile_name']),
    ];
}