<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/router.php";

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../dashboard");
    exit;
}

$name = $_POST['name'];
$router_id = $_POST['router_id'];
$hotspot_vlan = $_POST['hotspot_vlan'];
$pppoe_vlan = $_POST['pppoe_vlan'];

// Auto-generate subnets
$hotspot_subnet = "10.$hotspot_vlan.0.1";
$pppoe_subnet = "100.$pppoe_vlan.0.1";

$stmt = $pdo->prepare("INSERT INTO towers
    (name, router_id, hotspot_vlan, pppoe_vlan, hotspot_subnet, pppoe_subnet)
    VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$name, $router_id, $hotspot_vlan, $pppoe_vlan, $hotspot_subnet, $pppoe_subnet]);

$tower_id = $pdo->lastInsertId();

// Call provisioning engine
require_once "../core/provision_tower.php";
provision_tower($tower_id);

header("Location: index.php?added=success");
