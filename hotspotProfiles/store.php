<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";

use RouterOS\Query;

if (!is_logged_in()) exit;


/* ========= INPUT ========= */

$action       = $_POST['action'] ?? null;
$router_id    = $_POST['router_id'] ?? null;
$profile_name = trim($_POST['profile_name'] ?? '');
$profile_type = $_POST['profile_type'] ?? null;

if (!$action || !$router_id || !$profile_name) {
    die("Invalid request");
}


/* ========= CONNECT ROUTER ========= */

$client = router_connect($router_id);

if (!$client) {
    die("Router connection failed");
}


/* =====================================================
   HELPER — GET PROFILE ID
===================================================== */

function getProfileId($client, $path, $name)
{
    $print = new Query($path . '/print');
    $print->where('name', $name);

    $result = $client->query($print)->read();

    return $result[0]['.id'] ?? null;
}


/* =====================================================
   CREATE PROFILE
===================================================== */

if ($action === 'create') {

    $rate_limit   = $_POST['rate_limit'] ?? '';
    $shared_users = $_POST['shared_users'] ?? 1;
    $price        = $_POST['price'] ?? 0;
    $validity_d   = $_POST['validity_days'] ?? null;
    $validity_h   = $_POST['validity_hours'] ?? null;

    try {

        /* ---------- HOTSPOT ---------- */
        if ($profile_type === 'hotspot') {

            // prevent duplicate on router
            if (getProfileId($client, '/ip/hotspot/user/profile', $profile_name)) {
                throw new Exception("Profile already exists on router");
            }

            $q = new Query('/ip/hotspot/user/profile/add');
            $q->equal('name', $profile_name);

            if ($rate_limit) {
                $q->equal('rate-limit', $rate_limit);
            }

            // 🔥 Recommended anti-cheat defaults
            $q->equal('shared-users', $shared_users ?: 1);
            $q->equal('idle-timeout', '5m');
            $q->equal('keepalive-timeout', '2m');
            $q->equal('status-autorefresh', '1m');

            $client->query($q)->read();
        }


        /* ---------- PPPOE ---------- */
        elseif ($profile_type === 'pppoe') {

            if (getProfileId($client, '/ppp/profile', $profile_name)) {
                throw new Exception("PPP profile already exists");
            }

            $q = new Query('/ppp/profile/add');
            $q->equal('name', $profile_name);

            if ($rate_limit) {
                $q->equal('rate-limit', $rate_limit);
            }

            $client->query($q)->read();
        }


        /* ---------- SAVE TO DB ---------- */

        $stmt = $pdo->prepare("
            INSERT INTO hotspot_profiles
            (router_id, profile_name, plan_type, price, validity_days, validity_hours)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                plan_type=VALUES(plan_type),
                price=VALUES(price),
                validity_days=VALUES(validity_days),
                validity_hours=VALUES(validity_hours)
        ");

        $stmt->execute([
            $router_id,
            $profile_name,
            $profile_type,
            $price,
            $validity_d,
            $validity_h
        ]);

        $_SESSION['success'] = "Profile created successfully";

    } catch (Exception $e) {

        $_SESSION['error'] = $e->getMessage();
    }
}



/* =====================================================
   UPDATE PROFILE
===================================================== */

elseif ($action === 'update') {

    $rate_limit   = $_POST['rate_limit'] ?? '';
    $shared_users = $_POST['shared_users'] ?? null;
    $price        = $_POST['price'] ?? 0;
    $validity_d   = $_POST['validity_days'] ?? null;
    $validity_h   = $_POST['validity_hours'] ?? null;

    try {

        if ($profile_type === 'hotspot') {

            $id = getProfileId($client, '/ip/hotspot/user/profile', $profile_name);

            if (!$id) {
                throw new Exception("Router profile not found");
            }

            $q = new Query('/ip/hotspot/user/profile/set');
            $q->equal('.id', $id);

            if ($rate_limit) {
                $q->equal('rate-limit', $rate_limit);
            }

            if ($shared_users !== null) {
                $q->equal('shared-users', $shared_users);
            }

            $client->query($q)->read();
        }


        elseif ($profile_type === 'pppoe') {

            $id = getProfileId($client, '/ppp/profile', $profile_name);

            if (!$id) {
                throw new Exception("PPP profile not found");
            }

            $q = new Query('/ppp/profile/set');
            $q->equal('.id', $id);

            if ($rate_limit) {
                $q->equal('rate-limit', $rate_limit);
            }

            $client->query($q)->read();
        }


        /* ---------- UPDATE DB ---------- */

        $stmt = $pdo->prepare("
            UPDATE hotspot_profiles
            SET price=?, validity_days=?, validity_hours=?
            WHERE router_id=? AND profile_name=?
        ");

        $stmt->execute([
            $price,
            $validity_d,
            $validity_h,
            $router_id,
            $profile_name
        ]);

        $_SESSION['success'] = "Profile updated successfully";

    } catch (Exception $e) {

        $_SESSION['error'] = $e->getMessage();
    }
}



/* =====================================================
   DELETE PROFILE
===================================================== */

elseif ($action === 'delete') {

    try {

        if ($profile_type === 'hotspot') {

            $id = getProfileId($client, '/ip/hotspot/user/profile', $profile_name);

            if ($id) {
                $client->query(
                    (new Query('/ip/hotspot/user/profile/remove'))
                        ->equal('.id', $id)
                )->read();
            }
        }


        elseif ($profile_type === 'pppoe') {

            $id = getProfileId($client, '/ppp/profile', $profile_name);

            if ($id) {
                $client->query(
                    (new Query('/ppp/profile/remove'))
                        ->equal('.id', $id)
                )->read();
            }
        }


        /* ---------- DELETE FROM DB ---------- */

        $stmt = $pdo->prepare("
            DELETE FROM hotspot_profiles
            WHERE router_id=? AND profile_name=?
        ");

        $stmt->execute([$router_id, $profile_name]);

        $_SESSION['success'] = "Profile deleted successfully";

    } catch (Exception $e) {

        $_SESSION['error'] = $e->getMessage();
    }
}


header("Location: profiles.php?router_id=" . $router_id);
exit;
