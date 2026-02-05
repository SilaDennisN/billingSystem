<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) exit;

$stmt = $pdo->prepare("
    INSERT INTO billing_plans 
    (router_id, name, hotspot_profile, price, validity_days, validity_hours)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $_POST['router_id'],
    $_POST['name'],
    $_POST['hotspot_profile'],
    $_POST['price'],
    $_POST['validity_days'] ?: null,
    $_POST['validity_hours'] ?: null,
]);

header("Location: ../plans/index");
exit;
