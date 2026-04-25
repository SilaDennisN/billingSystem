<?php

use RouterOS\Query;

function cron_log(string $message): void
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $file = $logDir . '/expiry_' . date('Y-m-d') . '.log';
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

/**
 * Run the expiry engine.
 *
 * @param PDO   $pdo
 * @param array $routerIds  Limit to these router IDs. Empty = ALL active routers.
 * @return array{ expired: string[], failed: array[], total: int }
 */
function run_expiry_engine(PDO $pdo, array $routerIds = []): array
{
    require_once __DIR__ . '/router.php';

    date_default_timezone_set('Africa/Nairobi');

    $now = date('Y-m-d H:i:s');

    cron_log('===== Expiry job started =====');

    /* ── Scope: fetch expired users, optionally filtered by router ── */
    if (empty($routerIds)) {
        /* Called from cron with no filter — fetch all active routers */
        $stmt = $pdo->query("SELECT router_id FROM routers WHERE status = 'active'");
        $routerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (empty($routerIds)) {
        cron_log('No active routers — nothing to do');
        return ['expired' => [], 'failed' => [], 'total' => 0];
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

    cron_log('Found ' . count($users) . ' user(s) to expire');

    $results = [
        'expired' => [],
        'failed'  => [],
        'total'   => count($users),
    ];

    foreach ($users as $u) {

        $username = $u['username'];

        try {

            cron_log("Expiring: {$username}");

            $client = router_connect($u['router_id']);
            if (!$client) {
                throw new Exception("Router connection failed for router_id={$u['router_id']}");
            }

            /* ── Remove active session ── */
            $active = $client->query(
                (new Query('/ip/hotspot/active/print'))->where('user', $username)
            )->read();
            foreach ($active as $a) {
                $client->query(
                    (new Query('/ip/hotspot/active/remove'))->equal('.id', $a['.id'])
                )->read();
            }

            /* ── Remove cookie ── */
            $cookies = $client->query(
                (new Query('/ip/hotspot/cookie/print'))->where('user', $username)
            )->read();
            foreach ($cookies as $c) {
                $client->query(
                    (new Query('/ip/hotspot/cookie/remove'))->equal('.id', $c['.id'])
                )->read();
            }

            /* ── Remove host ──
               Supports both MAC-prefixed usernames (MAC704F57…) and plain MACs.
               expire_engine.php strips 3 chars ("MAC"), expiry_engine.php strips 4.
               We detect the prefix length automatically.
            ── */
            $macRaw = $username;
            if (stripos($username, 'MAC') === 0) {
                $macRaw = substr($username, 3); // strip "MAC"
            }
            if (strlen($macRaw) === 12) {
                $mac = strtoupper(implode(':', str_split($macRaw, 2)));
                $hosts = $client->query(
                    (new Query('/ip/hotspot/host/print'))->where('mac-address', $mac)
                )->read();
                foreach ($hosts as $h) {
                    $client->query(
                        (new Query('/ip/hotspot/host/remove'))->equal('.id', $h['.id'])
                    )->read();
                }
            }

            /* ── Disable then remove hotspot user ── */
            $routerUsers = $client->query(
                (new Query('/ip/hotspot/user/print'))->where('name', $username)
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
                    (new Query('/ip/hotspot/user/remove'))->equal('.id', $ru['.id'])
                )->read();
            }

            /* ── Mark expired in DB ── */
            $pdo->prepare("UPDATE hotspot_users SET status = 'expired' WHERE user_id = ?")
                ->execute([$u['user_id']]);

            cron_log("Expired OK: {$username}");
            $results['expired'][] = $username;

        } catch (\Throwable $e) {
            cron_log("ERROR for {$username}: " . $e->getMessage());
            $results['failed'][] = [
                'username' => $username,
                'error'    => $e->getMessage(),
            ];
        }
    }

    cron_log('===== Expiry job finished — expired: ' . count($results['expired']) . ', failed: ' . count($results['failed']) . ' =====');

    return $results;
}
