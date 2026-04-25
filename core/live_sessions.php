<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/router.php";

use RouterOS\Query;

/**
 * Fetch live users from all routers (Hotspot + PPPoE) — list only, no speed.
 * Used as the original function for backwards compatibility.
 */
function get_live_users(PDO $pdo, $user_id): array
{
    return _fetch_live_users($pdo, $user_id, false);
}

/**
 * Fetch live users WITH real-time speed data.
 * Called by the WebSocket server every 5s.
 */
function get_live_users_with_speed(PDO $pdo, $user_id): array
{
    return _fetch_live_users($pdo, $user_id, true);
}

/**
 * Core fetch — shared by both above functions.
 */
function _fetch_live_users(PDO $pdo, $user_id, bool $withSpeed): array
{
    $liveUsers = [];

    $stmt = $pdo->prepare("
        SELECT r.*
        FROM user_router_access ura
        JOIN routers r ON ura.router_id = r.router_id
        WHERE ura.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($routers as $router) {
        try {
            $client = router_connect($router['router_id']);
            if (!$client) continue;

            /* ── Hotspot ── */
            $hotspotUsers = $client->query('/ip/hotspot/user/print')->read();
            $userProfiles = [];
            foreach ($hotspotUsers as $hu) {
                $name = $hu['name'] ?? '';
                $userProfiles[$name] = [
                    'profile' => $hu['profile'] ?? '',
                    'comment' => $hu['comment'] ?? '',
                ];
            }

            $hotspotActives = $client->query('/ip/hotspot/active/print')->read();
            foreach ($hotspotActives as $a) {
                $username = $a['user'] ?? '';
                $profile  = $userProfiles[$username]['profile'] ?? '';
                $device   = $a['host-name'] ?? $userProfiles[$username]['comment'] ?? '';

                $entry = [
                    'router_id'   => $router['router_id'],
                    'router_name' => $router['name'],
                    'username'    => $username,
                    'mac_address' => $a['mac-address'] ?? '',
                    'device'      => $device,
                    'ip_address'  => $a['address']     ?? '',
                    'uptime'      => $a['uptime']       ?? '',
                    'profile'     => $profile,
                    'type'        => 'Hotspot',
                    'session_id'  => $a['.id']          ?? '',
                    'rx_bps'      => 0,
                    'tx_bps'      => 0,
                    'rx_bytes'    => (int)($a['bytes-in']  ?? 0),
                    'tx_bytes'    => (int)($a['bytes-out'] ?? 0),
                ];

                if ($withSpeed) {
                    /* bytes-in/out cumulative — server tracks deltas below */
                    $entry['rx_bytes'] = (int)($a['bytes-in']  ?? 0);
                    $entry['tx_bytes'] = (int)($a['bytes-out'] ?? 0);
                }

                $liveUsers[] = $entry;
            }

            /* ── PPPoE ── */
            $pppSecrets  = $client->query('/ppp/secret/print')->read();
            $pppProfiles = [];
            foreach ($pppSecrets as $ps) {
                $name = $ps['name'] ?? '';
                $pppProfiles[$name] = [
                    'profile' => $ps['profile'] ?? '',
                    'comment' => $ps['comment'] ?? '',
                ];
            }

            $pppoeActives = $client->query('/ppp/active/print')->read();
            foreach ($pppoeActives as $p) {
                $name    = $p['name']    ?? '';
                $profile = $pppProfiles[$name]['profile'] ?? '';
                $device  = $pppProfiles[$name]['comment'] ?? '';

                $rxBps = 0;
                $txBps = 0;

                if ($withSpeed) {
                    /* PPPoE speed via interface monitor-traffic */
                    try {
                        $iface = 'pppoe-' . $name;
                        $q = new Query('/interface/monitor-traffic');
                        $q->equal('interface', $iface);
                        $q->equal('once', '');
                        $traffic = $client->query($q)->read()[0] ?? [];
                        $rxBps   = (int)($traffic['rx-bits-per-second'] ?? 0);
                        $txBps   = (int)($traffic['tx-bits-per-second'] ?? 0);
                    } catch (\Throwable $e) {
                        /* interface may not exist yet */
                    }
                }

                $liveUsers[] = [
                    'router_id'   => $router['router_id'],
                    'router_name' => $router['name'],
                    'username'    => $name,
                    'mac_address' => $p['caller-id'] ?? '',
                    'device'      => $device,
                    'ip_address'  => $p['address']   ?? '',
                    'uptime'      => $p['uptime']     ?? '',
                    'profile'     => $profile,
                    'type'        => 'PPPoE',
                    'session_id'  => $p['.id']        ?? '',
                    'rx_bps'      => $rxBps,
                    'tx_bps'      => $txBps,
                    'rx_bytes'    => 0,
                    'tx_bytes'    => 0,
                ];
            }

        } catch (\Exception $e) {
            error_log("Router {$router['name']} ({$router['router_id']}) error: " . $e->getMessage());
            continue;
        }
    }

    return $liveUsers;
}