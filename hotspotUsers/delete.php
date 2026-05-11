<?php
date_default_timezone_set('Africa/Nairobi');
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";

use RouterOS\Query;

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index");
    exit;
}

$username = trim($_POST['username'] ?? '');

if (!$username) {
    die("Username is required.");
}

/* ── Fetch user ───────────────────────────────────────────── */
$stmt = $pdo->prepare("SELECT * FROM hotspot_users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

/* ── Remove from Router (best-effort) ────────────────────── */
try {

    $client = router_connect($user['router_id']);

    if ($client) {

        if ($user['user_type'] === 'pppoe') {

            /* Remove PPPoE secret */
            $secrets = $client->query(
                (new Query('/ppp/secret/print'))
                    ->where('name', $username)
            )->read();

            if (!empty($secrets)) {

                $client->query(
                    (new Query('/ppp/secret/remove'))
                        ->equal('.id', $secrets[0]['.id'])
                )->read();
            }

            /* Remove active PPPoE session */
            $active = $client->query(
                (new Query('/ppp/active/print'))
                    ->where('name', $username)
            )->read();

            if (!empty($active)) {

                $client->query(
                    (new Query('/ppp/active/remove'))
                        ->equal('.id', $active[0]['.id'])
                )->read();
            }

        } else {

            /* Remove hotspot user */
            $hs = $client->query(
                (new Query('/ip/hotspot/user/print'))
                    ->where('name', $username)
            )->read();

            if (!empty($hs)) {

                $client->query(
                    (new Query('/ip/hotspot/user/remove'))
                        ->equal('.id', $hs[0]['.id'])
                )->read();
            }

            /* Remove active hotspot session */
            $hsActive = $client->query(
                (new Query('/ip/hotspot/active/print'))
                    ->where('user', $username)
            )->read();

            if (!empty($hsActive)) {

                $client->query(
                    (new Query('/ip/hotspot/active/remove'))
                        ->equal('.id', $hsActive[0]['.id'])
                )->read();
            }
        }
    }

} catch (Exception $e) {

    // Optional debug
    // die($e->getMessage());

}

/* ── Delete from DB ──────────────────────────────────────── */
$del = $pdo->prepare("DELETE FROM hotspot_users WHERE username = ?");
$del->execute([$username]);

header("Location: index?success=deleted&user=" . urlencode($username));
exit;