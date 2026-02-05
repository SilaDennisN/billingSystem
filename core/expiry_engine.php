<?php

function run_expiry_engine(PDO $pdo)
{
    require_once __DIR__ . "/router.php";

    $now = date("Y-m-d H:i:s");

    /* ===============================
       EXPIRE ADMIN / PREPAID USERS
       =============================== */

    $users = $pdo->prepare("
        SELECT * FROM hotspot_users
        WHERE status='active' AND expires_at < ?
    ");
    $users->execute([$now]);

    foreach ($users->fetchAll() as $u) {
        try {
            $client = router_connect($u['router_id']);

            // Disable user
            $client->query(
                (new RouterOS\Query('/ip/hotspot/user/set'))
                    ->equal('.id', $u['username'])
                    ->equal('disabled', 'yes')
            )->read();

            // Remove active session
            $client->query(
                (new RouterOS\Query('/ip/hotspot/active/remove'))
                    ->equal('user', $u['username'])
            )->read();

        } catch (Exception $e) {
            // Router offline, skip
        }

        // Update DB
        $pdo->prepare("
            UPDATE hotspot_users SET status='expired'
            WHERE user_id=?
        ")->execute([$u['user_id']]);
    }

    /* ===============================
       EXPIRE CAPTIVE PORTAL USERS
       =============================== */

    $sessions = $pdo->prepare("
        SELECT * FROM hotspot_sessions
        WHERE paid=1 AND expires_at < ?
    ");
    $sessions->execute([$now]);

    foreach ($sessions->fetchAll() as $s) {
        try {
            $client = router_connect($s['router_id']);

            // Remove hotspot user
            $client->query(
                (new RouterOS\Query('/ip/hotspot/user/remove'))
                    ->equal('name', $s['mac_address'])
            )->read();

            // Kick active session
            $client->query(
                (new RouterOS\Query('/ip/hotspot/active/remove'))
                    ->equal('user', $s['mac_address'])
            )->read();

        } catch (Exception $e) {}

        // Mark expired
        $pdo->prepare("
            UPDATE hotspot_sessions SET paid=0
            WHERE session_id=?
        ")->execute([$s['session_id']]);
    }
}
