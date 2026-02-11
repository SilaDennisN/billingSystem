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

    // 1. Remove active sessions
    $active = new Query('/ip/hotspot/active/print');
    $active->where('user', $username);
    $actives = routerQuery($client, $active);

    foreach ($actives as $a) {
        $remove = new Query('/ip/hotspot/active/remove');
        $remove->equal('.id', $a['.id']);
        routerQuery($client, $remove);
    }

    // 2. Clear host cache
    $macRouter = strtoupper(implode(":", str_split(substr($username, 4), 2)));
    $host = new Query('/ip/hotspot/host/print');
    $host->where('mac-address', $macRouter);
    $hosts = routerQuery($client, $host);

    foreach ($hosts as $h) {
        $remove = new Query('/ip/hotspot/host/remove');
        $remove->equal('.id', $h['.id']);
        routerQuery($client, $remove);
    }

    // 3. (Optional) Remove hotspot user entirely
    // $check = new Query('/ip/hotspot/user/print');
    // $check->where('name', $username);
    // $users = routerQuery($client, $check);
    // foreach ($users as $u) {
    //     $remove = new Query('/ip/hotspot/user/remove');
    //     $remove->equal('.id', $u['.id']);
    //     routerQuery($client, $remove);
    // }

    header("Location: ../live.php?message=User+$username+disconnected");
    exit;

} catch (Throwable $e) {
    header("Location: ../live.php?error=" . urlencode($e->getMessage()));
    exit;
}
