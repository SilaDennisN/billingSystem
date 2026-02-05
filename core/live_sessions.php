<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/router.php";

/**
 * Fetch live users from all routers (Hotspot + PPPoE)
 */
function get_live_users(PDO $pdo)
{
    $liveUsers = [];

    $routers = $pdo->query("SELECT * FROM routers WHERE status='active'")->fetchAll();

    foreach ($routers as $router) {
        try {
            $client = router_connect($router['router_id']);
            if (!$client) continue;

            // HOTSPOT
            // First, get all hotspot users with their profiles
            $hotspotUsers = $client->query('/ip/hotspot/user/print')->read();
            
            // Create a lookup map: username => profile
            $userProfiles = [];
            foreach ($hotspotUsers as $hu) {
                $username = $hu['name'] ?? '';
                $userProfiles[$username] = [
                    'profile' => $hu['profile'] ?? '',
                    'comment' => $hu['comment'] ?? '', // device name might be in comment
                ];
            }

            // Now get active sessions
            $hotspotActives = $client->query('/ip/hotspot/active/print')->read();
            foreach ($hotspotActives as $a) {
                $username = $a['user'] ?? '';
                $profile = $userProfiles[$username]['profile'] ?? '';
                $device = $a['host-name'] ?? $userProfiles[$username]['comment'] ?? '';
                
                $liveUsers[] = [
                    'router_id'    => $router['router_id'],
                    'router_name'  => $router['name'],
                    'username'     => $username,
                    'mac_address'  => $a['mac-address'] ?? $username,
                    'device'       => $device,
                    'ip_address'   => $a['address'] ?? '',
                    'uptime'       => $a['uptime'] ?? '',
                    'profile'      => $profile,
                ];
            }

            // PPPoE
            // Get PPP secrets for profiles
            $pppSecrets = $client->query('/ppp/secret/print')->read();
            $pppProfiles = [];
            foreach ($pppSecrets as $ps) {
                $name = $ps['name'] ?? '';
                $pppProfiles[$name] = [
                    'profile' => $ps['profile'] ?? '',
                    'comment' => $ps['comment'] ?? '',
                ];
            }

            // Get active PPPoE sessions
            $pppoeActives = $client->query('/ppp/active/print')->read();
            foreach ($pppoeActives as $p) {
                $name = $p['name'] ?? '';
                $profile = $pppProfiles[$name]['profile'] ?? '';
                $device = $pppProfiles[$name]['comment'] ?? '';
                
                $liveUsers[] = [
                    'router_id'    => $router['router_id'],
                    'router_name'  => $router['name'],
                    'username'     => $name,
                    'mac_address'  => $p['caller-id'] ?? $name,
                    'device'       => $device,
                    'ip_address'   => $p['address'] ?? '',
                    'uptime'       => $p['uptime'] ?? '',
                    'profile'      => $profile,
                ];
            }

        } catch (Exception $e) {
            error_log("Router {$router['name']} ({$router['ip']}) offline or failed: " . $e->getMessage());
            continue;
        }
    }

    return $liveUsers;
}