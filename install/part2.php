<?php
error_reporting(0);
ini_set('display_errors', '0');

ob_start();
require_once "../core/db.php";
ob_end_clean();

header("Content-Type: text/plain");

$router_id = $_GET['router_id'] ?? null;
if (!$router_id) die("# ERROR: Router ID missing");

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();
if (!$router) die("# ERROR: Router not found");

$lan_ip          = "192.168.50.1";
$pppoe_pool      = "192.168.50.201-192.168.50.254";
$pppoe_profile   = "inovatech-pppoe";
$hotspot_address = "192.168.51.1";
$hotspot_pool    = "192.168.51.10-192.168.51.254";
$hotspot_network = "192.168.51.0";
$hotspot_name    = "Inovatech WiFi";
$captive_dns     = "hotspot.inovatech.co.ke";
$vps_ip          = "102.68.86.80";
$router_name     = $router['name'];

echo '
:log info "========================================"
:log info "  Inovatech Part 2 - Hotspot + PPPoE + Firewall"
:log info "========================================"

# ================================================
# STEP 1/3 - Hotspot + Captive Portal
# ================================================

:log info "--- [1/3] Hotspot: creating hotspot-bridge ---"
:if ([:len [/interface bridge find name="hotspot-bridge"]] = 0) do={
    /interface bridge add name=hotspot-bridge protocol-mode=none comment="inovatech-hotspot"
}

:log info "--- [1/3] Hotspot: adding wlan1 to bridge ---"
:if ([:len [/interface bridge port find interface=wlan1]] = 0) do={
    /interface bridge port add bridge=hotspot-bridge interface=wlan1 comment="inovatech-hotspot"
}

:log info "--- [1/3] Hotspot: adding IP ---"
:if ([:len [/ip address find where address~"192.168.51.1"]] = 0) do={
    /ip address add address=192.168.51.1/24 interface=hotspot-bridge comment="inovatech-hotspot"
}

:log info "--- [1/3] Hotspot: setting SSID ---"
:if ([:len [/interface wireless find name=wlan1]] > 0) do={
    /interface wireless set wlan1 ssid="Inovatech WiFi" mode=ap-bridge disabled=no
}

:log info "--- [1/3] Hotspot: creating pool ---"
:if ([:len [/ip pool find name="hotspot_pool"]] = 0) do={
    /ip pool add name=hotspot_pool ranges=192.168.51.10-192.168.51.254
}

:log info "--- [1/3] Hotspot: DHCP server ---"
:if ([:len [/ip dhcp-server find name="dhcp-hotspot"]] = 0) do={
    /ip dhcp-server add name=dhcp-hotspot interface=hotspot-bridge address-pool=hotspot_pool disabled=no lease-time=1h comment="inovatech-hotspot"
}

:log info "--- [1/3] Hotspot: DHCP network ---"
:if ([:len [/ip dhcp-server network find address="192.168.51.0/24"]] = 0) do={
    /ip dhcp-server network add address=192.168.51.0/24 gateway=192.168.51.1 dns-server=8.8.8.8,8.8.4.4 comment="inovatech-hotspot"
}

:log info "--- [1/3] Hotspot: profile ---"
:if ([:len [/ip hotspot profile find name="inovatech-profile"]] = 0) do={
    /ip hotspot profile add name=inovatech-profile hotspot-address=192.168.51.1 dns-name=hotspot.inovatech.co.ke login-by=http-chap,http-pap http-cookie-lifetime=1d comment="inovatech-hotspot"
}

:log info "--- [1/3] Hotspot: instance ---"
:if ([:len [/ip hotspot find name="hotspot1"]] = 0) do={
    /ip hotspot add name=hotspot1 interface=hotspot-bridge profile=inovatech-profile address-pool=hotspot_pool disabled=no comment="inovatech-hotspot"
}

:log info "--- [1/3] Hotspot: walled garden ---"
:if ([:len [/ip hotspot walled-garden find comment="inovatech-wg-wildcard"]] = 0) do={
    /ip hotspot walled-garden add dst-host="*.inovatech.co.ke" action=allow comment="inovatech-wg-wildcard"
}

:if ([:len [/ip hotspot walled-garden find comment="inovatech-wg-bare"]] = 0) do={
    /ip hotspot walled-garden add dst-host="inovatech.co.ke" action=allow comment="inovatech-wg-bare"
}

:if ([:len [/ip hotspot walled-garden ip find comment="inovatech-wg-vps"]] = 0) do={
    /ip hotspot walled-garden ip add dst-address=102.68.86.80 action=accept comment="inovatech-wg-vps"
}

:log info "=== [1/3] Hotspot DONE ==="

# ================================================
# STEP 2/3 - PPPoE Server
# ================================================

:log info "--- [2/3] PPPoE: pool ---"
:if ([:len [/ip pool find name="pppoe_pool"]] = 0) do={
    /ip pool add name=pppoe_pool ranges=192.168.50.201-192.168.50.254
}

:log info "--- [2/3] PPPoE: profile ---"
:if ([:len [/ppp profile find name="inovatech-pppoe"]] = 0) do={
    /ppp profile add name=inovatech-pppoe local-address=192.168.50.1 remote-address=pppoe_pool dns-server=8.8.8.8,8.8.4.4 comment="inovatech-pppoe"
}

:log info "--- [2/3] PPPoE: server ---"
:if ([:len [/interface pppoe-server server find service-name="PPPoE_Inovatech"]] = 0) do={
    /interface pppoe-server server add service-name=PPPoE_Inovatech interface=bridge default-profile=inovatech-pppoe disabled=no comment="inovatech-pppoe"
}

:log info "=== [2/3] PPPoE DONE ==="

# ================================================
# STEP 3/3 - Firewall + DNS
# ================================================

:log info "--- [3/3] Firewall rules ---"

/ip firewall filter remove [find comment="inovatech-vpn"]
/ip firewall filter add chain=input in-interface=wg-billing action=accept comment="inovatech-vpn" place-before=0

:if ([:len [/ip firewall filter find comment="inovatech-established"]] = 0) do={
    /ip firewall filter add chain=input connection-state=established,related action=accept comment="inovatech-established"
}

:if ([:len [/ip firewall filter find comment="inovatech-invalid"]] = 0) do={
    /ip firewall filter add chain=input connection-state=invalid action=drop comment="inovatech-invalid"
}

:if ([:len [/ip firewall filter find comment="inovatech-winbox"]] = 0) do={
    /ip firewall filter add chain=input protocol=tcp dst-port=8291 action=accept comment="inovatech-winbox"
}

:if ([:len [/ip firewall filter find comment="inovatech-dns"]] = 0) do={
    /ip firewall filter add chain=input protocol=udp dst-port=53 action=accept comment="inovatech-dns"
    /ip firewall filter add chain=input protocol=tcp dst-port=53 action=accept comment="inovatech-dns"
}

:log info "--- [3/3] DNS ---"
/ip dns set allow-remote-requests=yes servers=8.8.8.8,8.8.4.4

:log info "--- [3/3] Identity ---"
/system identity set name="ROUTER_NAME"

:log info "========================================"
:log info "Bootstrap COMPLETE"
:log info "========================================"
';