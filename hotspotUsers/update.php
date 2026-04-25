<?php

require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/router.php";


if (!is_logged_in()) {

    header("Location: /auth/login");
    exit;
}


/* =========================
   VALIDATE POST
========================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");
    exit;
}


$username   = $_POST['username'] ?? null;
$plan_id    = $_POST['plan_id'] ?? null;
$expires_at = $_POST['expires_at'] ?? null;


if (!$username || !$plan_id || !$expires_at) {

    die("Missing fields");
}



/* =========================
   CHECK USER TYPE
========================= */

$stmt = $pdo->prepare("
SELECT * FROM hotspot_users
WHERE username = ?
");

$stmt->execute([$username]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$user) {

    die("User not found");
}


if ($user['user_type'] === 'hotspot') {

    die("Hotspot users cannot be edited");
}



/* =========================
   GET PLAN DETAILS
========================= */

$stmt = $pdo->prepare("
SELECT * FROM hotspot_profiles
WHERE id = ?
");

$stmt->execute([$plan_id]);

$plan = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$plan) {

    die("Plan not found");
}



/* =========================
   UPDATE DATABASE
========================= */

$stmt = $pdo->prepare("
UPDATE hotspot_users

SET

plan_id = ?,
expires_at = ?

WHERE username = ?

");

$stmt->execute([

    $plan_id,
    $expires_at,
    $username

]);



/* =========================
   UPDATE ROUTER
========================= */

try {


    $client = router_connect($user['router_id']);


    if ($client) {


        /* FIND PPP SECRET */

        $secret = $client->query('/ppp/secret/print', [

            'name' => $username

        ])->read();


        if (!empty($secret)) {


            $client->query('/ppp/secret/set', [

                '.id' => $secret[0]['.id'],

                'profile' => $plan['profile_name']

            ])->read();
        }
    }
} catch (Exception $e) {
}


/* =========================
   REDIRECT BACK
========================= */

header("Location: index?success=updated");

exit;
