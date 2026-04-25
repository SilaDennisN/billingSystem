<?php
date_default_timezone_set('Africa/Nairobi');
session_start();
require_once "../core/db.php";
require_once "../core/router.php";
use RouterOS\Query;

$token = $_SESSION['payment_token'] ?? null;
$password = '123456';

$stmt = $pdo->prepare("
    SELECT * FROM payments
    WHERE payment_token=? AND status='confirmed'
");
$stmt->execute([$token]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(403);
    exit("Payment not confirmed");
}

/* Mark token used (ONE TIME) */
$pdo->prepare("
    UPDATE payments SET status='used'
    WHERE payment_id=?
")->execute([$payment['payment_id']]);

/* Continue with your EXISTING logic */
$mac             = $_SESSION['mac'] ?? null;
$link_login_only = $_SESSION['link-login-only'] ?? null;

if (!$mac || !$link_login_only) {
    die("Session expired");
}

$router_id = $payment['router_id'];
$plan_id   = $payment['plan_id'];
$username  = $payment['username'];


/* Load plan */
$stmt = $pdo->prepare("SELECT * FROM hotspot_profiles WHERE id=? AND plan_type='hotspot'");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) die("Invalid plan");

$username = 'mac_' . str_replace(':', '', $mac);
$password = '123456';

/* Connect router */
$client = router_connect($router_id);
if (!$client) die("Router connection failed");


$start = new DateTime();

$expire = clone $start;
if ($plan['validity_days']) {
    $expire->modify("+{$plan['validity_days']} days");
}
if ($plan['validity_hours']) {
    $expire->modify("+{$plan['validity_hours']} hours");
}

$stmt = $pdo->prepare("
    INSERT INTO hotspot_users
    (router_id, plan_id, username, user_type, password, starts_at, expires_at, status)
    VALUES (?, ?, ?, 'hotspot', ?, ?, ?, 'active')
    ON DUPLICATE KEY UPDATE
        plan_id    = VALUES(plan_id),
        password   = VALUES(password),
        starts_at  = VALUES(starts_at),
        expires_at = VALUES(expires_at),
        status     = 'active'
");

$stmt->execute([
    $router_id,
    $plan_id,
    $username,
    $password,
    $start->format('Y-m-d H:i:s'),
    $expire->format('Y-m-d H:i:s')
]);


/* Create user */
$query = new Query('/ip/hotspot/user/add');
$query->equal('name', $username);
$query->equal('password', $password);
$query->equal('profile', $plan['profile_name']);
$query->equal('comment', 'Paid user');

if ($plan['validity_hours']) {
    $query->equal('limit-uptime', $plan['validity_hours'].'h');
}
if ($plan['validity_days']) {
    $query->equal('limit-uptime', $plan['validity_days'].'d');
}

$client->query($query)->read();
?>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Connecting...</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<body onload="document.login.submit()">

<form name="login" method="post" action="<?= htmlspecialchars($link_login_only) ?>">
    <form name="login" method="post" action="<?= htmlspecialchars($link_login_only) ?>">
    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
    <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
    <input type="hidden" name="dst" value="https://www.google.com">
</form>
</form>
<style>
    body {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
    }

    .card {
        background: #fff;
        padding: 30px 20px;
        border-radius: 16px;
        text-align: center;
        max-width: 380px;
        width: 100%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }

    .loader {
        width: 40px;
        height: 40px;
        border: 4px solid #eee;
        border-top: 4px solid #2c5364;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 20px auto;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    h2 {
        color: #0f2027;
        margin-bottom: 8px;
    }

    p {
        color: #666;
        font-size: 14px;
    }
</style>

<div class="card">
    <h2>Payment Successful 🎉</h2>
    <p>Connecting you to the internet…</p>
    <div class="loader"></div>
    <p style="font-size:12px;color:#999;">Please wait a moment</p>
</div>



</body>
</html>

<?php

?>
