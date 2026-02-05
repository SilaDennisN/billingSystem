<?php
require_once "../core/db.php";
require_once "../core/auth.php";
if (!is_logged_in()) exit;

$plan_id = $_POST['plan_id'];
$router_id = $_POST['router_id'];
$count = (int)$_POST['count'];

for ($i=0; $i<$count; $i++) {
    $code = strtoupper(bin2hex(random_bytes(4)));

    $pdo->prepare("
        INSERT INTO vouchers (code, plan_id, router_id)
        VALUES (?, ?, ?)
    ")->execute([$code, $plan_id, $router_id]);
}

header("Location: index.php");
exit;

?>