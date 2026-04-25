<?php

use RouterOS\Query;

function run_expiry_engine(PDO $pdo, array $routerIds = [])
{
    require_once __DIR__ . "/router.php";

    date_default_timezone_set('Africa/Nairobi');

    $now = date("Y-m-d H:i:s");

    /* GET ALL EXPIRED USERS — scoped to allowed router IDs */

    if (empty($routerIds)) {
        $routerIds = [0]; // prevents SQL error if no routers
    }

    $placeholders = implode(',', array_fill(0, count($routerIds), '?'));

    $stmt = $pdo->prepare("
        SELECT *
        FROM hotspot_users
        WHERE status = 'active'
        AND expires_at <= ?
        AND router_id IN ($placeholders)
    ");

    $stmt->execute(array_merge([$now], $routerIds));

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [
        'expired'  => [],
        'failed'   => [],
        'total'    => count($users),
    ];

    foreach ($users as $u) {

        $username = $u['username'];

        try {

            echo "Expiring: {$username}\n";

            $client = router_connect($u['router_id']);

            if (!$client) {
                throw new Exception("Router connection failed for router_id={$u['router_id']}");
            }

            /* =============================
               REMOVE ACTIVE SESSION
            ============================= */

            $active = $client->query(
                (new Query('/ip/hotspot/active/print'))
                    ->where('user', $username)
            )->read();

            foreach ($active as $a) {
                $client->query(
                    (new Query('/ip/hotspot/active/remove'))
                        ->equal('.id', $a['.id'])
                )->read();
            }

            /* =============================
               REMOVE COOKIE
            ============================= */

            $cookies = $client->query(
                (new Query('/ip/hotspot/cookie/print'))
                    ->where('user', $username)
            )->read();

            foreach ($cookies as $c) {
                $client->query(
                    (new Query('/ip/hotspot/cookie/remove'))
                        ->equal('.id', $c['.id'])
                )->read();
            }

            /* =============================
               REMOVE HOST
               Convert username MAC e.g. MAC704F57123456 → 70:4F:57:12:34:56
            ============================= */

            $macRaw = substr($username, 3); // strip "MAC" prefix
            $mac    = strtoupper(implode(':', str_split($macRaw, 2)));

            $hosts = $client->query(
                (new Query('/ip/hotspot/host/print'))
                    ->where('mac-address', $mac)
            )->read();

            foreach ($hosts as $h) {
                $client->query(
                    (new Query('/ip/hotspot/host/remove'))
                        ->equal('.id', $h['.id'])
                )->read();
            }

            /* =============================
               DISABLE THEN REMOVE USER
            ============================= */

            $routerUsers = $client->query(
                (new Query('/ip/hotspot/user/print'))
                    ->where('name', $username)
            )->read();

            foreach ($routerUsers as $ru) {
                $client->query(
                    (new Query('/ip/hotspot/user/set'))
                        ->equal('.id', $ru['.id'])
                        ->equal('disabled', 'yes')
                )->read();
            }

            foreach ($routerUsers as $ru) {
                $client->query(
                    (new Query('/ip/hotspot/user/remove'))
                        ->equal('.id', $ru['.id'])
                )->read();
            }

            /* =============================
               UPDATE DATABASE
            ============================= */

            $update = $pdo->prepare("
                UPDATE hotspot_users
                SET status = 'expired'
                WHERE user_id = ?
            ");
            $update->execute([$u['user_id']]);

            echo "Expired OK: {$username}\n";

            $results['expired'][] = $username;

        } catch (Throwable $e) {

            error_log("Expiry error [{$username}]: " . $e->getMessage());
            echo "Error expiring {$username}: " . $e->getMessage() . "\n";

            $results['failed'][] = [
                'username' => $username,
                'error'    => $e->getMessage(),
            ];
        }
    }

    return $results;
}