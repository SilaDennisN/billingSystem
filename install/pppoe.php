<?php
error_reporting(0);
ini_set('display_errors', '0');

ob_start();
require_once "../core/db.php";
ob_end_clean();

header("Content-Type: text/plain");

$router_id = $_GET['router_id'] ?? null;
if (!$router_id) die("Router ID missing");

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();
if (!$router) die("Router not found");

$lan_ip        = "192.168.50.1";
$pppoe_profile = "inovatech-pppoe";

echo ':log info "=== [4/5] PPPoE Server START ==="

# ---- PPPoE profile
:if ([:len [/ppp profile find name="' . $pppoe_profile . '"]] = 0) do={
    /ppp profile add \
        name="' . $pppoe_profile . '" \
        local-address=' . $lan_ip . ' \
        remote-address=pppoe_pool \
        use-compression=no \
        use-encryption=no \
        dns-server=8.8.8.8,8.8.4.4 \
        comment="inovatech-pppoe"
}

# ---- PPPoE server on the LAN bridge
:if ([:len [/interface pppoe-server server find service-name="PPPoE_Inovatech"]] = 0) do={
    /interface pppoe-server server add \
        service-name=PPPoE_Inovatech \
        interface=bridge \
        default-profile="' . $pppoe_profile . '" \
        authentication=chap,mschap2 \
        disabled=no \
        comment="inovatech-pppoe"
}

:log info "=== [4/5] PPPoE Server DONE ==="
';