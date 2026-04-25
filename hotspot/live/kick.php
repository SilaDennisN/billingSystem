<?php
require_once "../../core/auth.php";
require_once "../../core/router.php";
require_once "../../core/db.php";

use RouterOS\Query;

function routerQuery($client, Query $query)
{
    $response = $client->query($query)->read();

    foreach ($response as $r) {
        if (isset($r['!trap'])) {
            throw new Exception("Router trap: " . json_encode($r));
        }
    }

    return $response;
}

// Ensure user is logged in
if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Invalid request method");
}

$router_id = $_POST['router_id'] ?? null;
$username  = $_POST['username'] ?? null;
$user_type = $_POST['user_type'] ?? 'hotspot';

if (!$router_id || !$username) {
    http_response_code(400);
    exit("Missing router_id or username");
}

try {
    // Connect to router
    $client = router_connect($router_id);
    if (!$client) {
        throw new Exception("Failed to connect to router");
    }

    if ($user_type === 'pppoe') {
        // Handle PPPoE disconnect
        $active = new Query('/ppp/active/print');
        $active->where('name', $username);
        $actives = routerQuery($client, $active);

        foreach ($actives as $a) {
            $remove = new Query('/ppp/active/remove');
            $remove->equal('.id', $a['.id']);
            routerQuery($client, $remove);
        }
        
        $message = "PPPoE user $username disconnected";
        
    } elseif ($user_type === 'captive') {
        // Handle captive portal disconnect (by MAC)
        $mac_router = strtoupper(str_replace(':', '', $username));
        
        // Remove from hotspot active
        $active = new Query('/ip/hotspot/active/print');
        $actives = routerQuery($client, $active);
        
        foreach ($actives as $a) {
            if (isset($a['mac-address']) && strtoupper(str_replace(':', '', $a['mac-address'])) === $mac_router) {
                $remove = new Query('/ip/hotspot/active/remove');
                $remove->equal('.id', $a['.id']);
                routerQuery($client, $remove);
                break;
            }
        }
        
        // Remove from host cache
        $host = new Query('/ip/hotspot/host/print');
        $hosts = routerQuery($client, $host);
        
        foreach ($hosts as $h) {
            if (isset($h['mac-address']) && strtoupper(str_replace(':', '', $h['mac-address'])) === $mac_router) {
                $remove = new Query('/ip/hotspot/host/remove');
                $remove->equal('.id', $h['.id']);
                routerQuery($client, $remove);
                break;
            }
        }
        
        $message = "Captive portal session for MAC $username disconnected";
        
    } else {
        // Handle Hotspot disconnect (by username)
        // 1. Remove active sessions
        $active = new Query('/ip/hotspot/active/print');
        $active->where('user', $username);
        $actives = routerQuery($client, $active);

        foreach ($actives as $a) {
            $remove = new Query('/ip/hotspot/active/remove');
            $remove->equal('.id', $a['.id']);
            routerQuery($client, $remove);
        }

        // 2. Clear host cache (optional - for completeness)
        // This requires MAC address which we don't have, so we skip
        
        $message = "Hotspot user $username disconnected";
    }

    header("Location: ../index.php?success=" . urlencode($message));
    exit;

} catch (Throwable $e) {
    header("Location: ../index.php?error=" . urlencode($e->getMessage()));
    exit;
}