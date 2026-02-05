<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";

if (!is_logged_in()) exit;

$router_id = $_POST['router_id'];
$plan_id = $_POST['plan_id'];

$plan = $pdo->prepare("SELECT * FROM hotspot_profiles WHERE id=?");
$plan->execute([$plan_id]);
$plan = $plan->fetch();

$start = new DateTime();

$expire = clone $start;
if ($plan['validity_days']) {
    $expire->modify("+{$plan['validity_days']} days");
}
if ($plan['validity_hours']) {
    $expire->modify("+{$plan['validity_hours']} hours");
}

/* Push to Router */
$client = router_connect($router_id);

$client->query(
    (new RouterOS\Query('/ppp/secret/add'))
        ->equal('name', $_POST['username'])
        ->equal('password', $_POST['password'])
        ->equal('service', 'pppoe')
        ->equal('profile', $plan['profile_name'])
)->read();


/* Save to DB */
$stmt = $pdo->prepare("
    INSERT INTO hotspot_users
    (router_id, plan_id, username, password, starts_at, expires_at)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $router_id,
    $plan_id,
    $_POST['username'],
    $_POST['password'],
    $start->format('Y-m-d H:i:s'),
    $expire->format('Y-m-d H:i:s')
]);

header("Location: ../hotspotUsers/index");
exit;
