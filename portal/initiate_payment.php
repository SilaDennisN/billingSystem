<?php
session_start();
require_once "../core/db.php";

$router_id = $_POST['router_id'];
$plan_id   = $_POST['plan_id'];
$mac       = $_SESSION['mac'];

if (!$router_id || !$plan_id || !$mac) {
    exit("Invalid request");
}

/* Load plan */
$stmt = $pdo->prepare("SELECT * FROM hotspot_profiles WHERE id=? AND plan_type='hotspot'");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) exit("Invalid plan");

$username = 'mac_' . str_replace(':','',$mac);
$token = bin2hex(random_bytes(32));

$stmt = $pdo->prepare("
    INSERT INTO payments
    (router_id, plan_id, username, amount, payment_token)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $router_id,
    $plan_id,
    $username,
    $plan['price'],
    $token
]);

$_SESSION['payment_token'] = $token;

/* DEMO PAYMENT PAGE */
header("Location: pay.php");
exit;
