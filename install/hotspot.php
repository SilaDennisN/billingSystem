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

$hotspot_name    = "Inovatech WiFi";
$hotspot_address = "192.168.51.1";
$hotspot_pool    = "192.168.51.10-192.168.51.254";
$captive_dns     = "hotspot.inovatech.co.ke";
$hotspot_network = preg_replace('/\.\d+$/', '.0', $hotspot_address);
$vps_ip          = "102.68.86.80";

echo ':log info "=== [3/5] Hotspot + Captive Portal START ==="

# ---- Hotspot bridge (wlan1 on its own bridge, isolated from LAN)
:if ([:len [/interface bridge find name="hotspot-bridge"]] = 0) do={
    /interface bridge add name=hotspot-bridge protocol-mode=none comment="inovatech-hotspot"
}
:if ([:len [/interface bridge port find interface=wlan1]] = 0) do={
    /interface bridge port add bridge=hotspot-bridge interface=wlan1 comment="inovatech-hotspot"
}
:if ([:len [/ip address find where address~"' . $hotspot_address . '"]] = 0) do={
    /ip address add address=' . $hotspot_address . '/24 interface=hotspot-bridge comment="inovatech-hotspot"
}

# ---- WiFi SSID
:if ([:len [/interface wireless find name=wlan1]] > 0) do={
    /interface wireless set wlan1 ssid="' . $hotspot_name . '" mode=ap-bridge disabled=no
}

# ---- Hotspot IP pool
:if ([:len [/ip pool find name="hotspot_pool"]] = 0) do={
    /ip pool add name=hotspot_pool ranges=' . $hotspot_pool . '
}

# ---- DHCP for hotspot clients
:if ([:len [/ip dhcp-server find name="dhcp-hotspot"]] = 0) do={
    /ip dhcp-server add name=dhcp-hotspot interface=hotspot-bridge address-pool=hotspot_pool disabled=no lease-time=1h comment="inovatech-hotspot"
}
:if ([:len [/ip dhcp-server network find address~"' . $hotspot_network . '"]] = 0) do={
    /ip dhcp-server network add address=' . $hotspot_network . '/24 gateway=' . $hotspot_address . ' dns-server=8.8.8.8,8.8.4.4 comment="inovatech-hotspot"
}

# ---- Hotspot profile
:if ([:len [/ip hotspot profile find name="inovatech-profile"]] = 0) do={
    /ip hotspot profile add \
        name=inovatech-profile \
        hotspot-address=' . $hotspot_address . ' \
        dns-name="' . $captive_dns . '" \
        html-directory=hotspot \
        login-by=http-chap,http-pap \
        http-cookie-lifetime=1d \
        use-radius=no \
        comment="inovatech-hotspot"
}

# ---- Hotspot instance
:if ([:len [/ip hotspot find name="hotspot1"]] = 0) do={
    /ip hotspot add \
        name=hotspot1 \
        interface=hotspot-bridge \
        profile=inovatech-profile \
        address-pool=hotspot_pool \
        disabled=no \
        comment="inovatech-hotspot"
}

# ---- Walled garden - allow billing portal without login
:if ([:len [/ip hotspot walled-garden find comment="inovatech-wg-wildcard"]] = 0) do={
    /ip hotspot walled-garden add dst-host="*.inovatech.co.ke" action=allow comment="inovatech-wg-wildcard"
}
:if ([:len [/ip hotspot walled-garden find comment="inovatech-wg-bare"]] = 0) do={
    /ip hotspot walled-garden add dst-host="inovatech.co.ke" action=allow comment="inovatech-wg-bare"
}
:if ([:len [/ip hotspot walled-garden ip find comment="inovatech-wg-vps"]] = 0) do={
    /ip hotspot walled-garden ip add dst-address=' . $vps_ip . ' action=accept comment="inovatech-wg-vps"
}

:log info "=== [3/5] Hotspot + Captive Portal DONE ==="
';