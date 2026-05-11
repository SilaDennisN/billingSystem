<?php
date_default_timezone_set('Africa/Nairobi');
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/router.php";

use RouterOS\Query;

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index");
    exit;
}

$username  = trim($_POST['username'] ?? '');
$userType  = $_POST['user_type'] ?? '';
$extendMin = (int) ($_POST['extend_minutes'] ?? 0);
$extendDay = (int) ($_POST['extend_days'] ?? 0);

if (!$username || !$userType || ($extendMin === 0 && $extendDay === 0)) {
    die("Missing or invalid extend parameters.");
}

/* ── Fetch user ──────────────────────────────────────────── */
$stmt = $pdo->prepare("SELECT * FROM hotspot_users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

/* ── Calculate new expiry ────────────────────────────────── */
$now       = new DateTime();
$curExpiry = new DateTime($user['expires_at']);

$base = ($curExpiry < $now)
    ? clone $now
    : clone $curExpiry;

if ($userType === 'hotspot' && $extendMin > 0) {

    $base->modify("+{$extendMin} minutes");

} elseif ($userType === 'pppoe' && $extendDay > 0) {

    $base->modify("+{$extendDay} days");

} else {

    die("Invalid extend type / amount combination.");
}

$newExpiry = $base->format('Y-m-d H:i:s');

/* ── Update DB ───────────────────────────────────────────── */
$upd = $pdo->prepare("
    UPDATE hotspot_users
    SET expires_at = ?, status = 'active'
    WHERE username = ?
");

$upd->execute([$newExpiry, $username]);

/* ── Push to Router (best-effort) ───────────────────────── */
try {

    $client = router_connect($user['router_id']);

    if ($client) {

        if ($userType === 'hotspot') {

            /* Find hotspot user */
            $hs = $client->query(
                (new Query('/ip/hotspot/user/print'))
                    ->where('name', $username)
            )->read();

            if (!empty($hs)) {

                /* Remaining minutes from now */
                $totalMin = max(
                    1,
                    (int) round(($base->getTimestamp() - time()) / 60)
                );

                $client->query(
                    (new Query('/ip/hotspot/user/set'))
                        ->equal('.id', $hs[0]['.id'])
                        ->equal('limit-uptime', "{$totalMin}m")
                )->read();
            }
        }

        /* PPPoE handled by DB expiry only */
    }

} catch (Exception $e) {

    // Optional debug
    // die($e->getMessage());

}

header("Location: index?success=extended&user=" . urlencode($username));
exit;