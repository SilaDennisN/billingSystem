<?php
error_reporting(0);
ini_set('display_errors', '0');

ob_start();
require_once "../core/db.php";
ob_end_clean();

header("Content-Type: text/plain");

$router_id = $_GET['router_id'] ?? null;
if (!$router_id) {
    die("Router ID missing");
}

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();

if (!$router) {
    die("Router not found");
}

/* -----------------------
 Customize — edit these values as needed
----------------------- */
$hotspot_name    = "Inovatech WiFi";
$hotspot_address = "192.168.51.1";
$hotspot_pool    = "192.168.51.10-192.168.51.254";
$captive_dns     = "hotspot.inovatech.co.ke";

$lan_ip          = "192.168.50.1";
$lan_pool        = "192.168.50.10-192.168.50.200";
$pppoe_pool      = "192.168.50.201-192.168.50.254";
$pppoe_profile   = "inovatech-pppoe";

$lan_network     = preg_replace('/\.\d+$/', '.0', $lan_ip);
$hotspot_network = preg_replace('/\.\d+$/', '.0', $hotspot_address);

echo ':log info "=== Inovatech Install START ==="

# =========================================================
# STEP 1 — Add new LAN IP FIRST so you stay connected
# =========================================================

/interface bridge set bridge protocol-mode=none

:if ([:len [/ip address find where address~"' . $lan_ip . '"]] = 0) do={
    /ip address add address=' . $lan_ip . '/24 interface=bridge comment="inovatech-lan"
}

:log info "New LAN ' . $lan_ip . ' is UP — reconnect here if disconnected"
:delay 3s

# =========================================================
# STEP 2 — Remove old default config
#           New LAN IP already live — no lockout possible
# =========================================================

# Remove all IPs except our new LAN and loopback
:foreach addr in=[/ip address find where comment!="inovatech-lan" && interface!="lo"] do={
    /ip address remove $addr
}

/ip dhcp-client remove [find]
/ip dhcp-server remove [find]
/ip pool remove [find]
/ip firewall nat remove [find]
/ip hotspot remove [find]
/ip hotspot profile remove [find where name!="default"]

:foreach bp in=[/interface bridge port find interface=wlan1] do={
    /interface bridge port remove $bp
}

:foreach bp in=[/interface bridge port find bridge=bridge] do={
    /interface bridge port remove $bp
}

:log info "Default config stripped"

# =========================================================
# STEP 3 — Restore WAN DHCP client on ether1
# =========================================================

:if ([:len [/ip dhcp-client find interface=ether1]] = 0) do={
    /ip dhcp-client add interface=ether1 disabled=no add-default-route=yes use-peer-dns=yes
}

:delay 5s
:log info "WAN restored"

# =========================================================
# STEP 4 — LAN Bridge (ether2-ether5)
# =========================================================

/interface bridge set bridge protocol-mode=rstp

:foreach iface in={"ether2";"ether3";"ether4";"ether5"} do={
    :if ([:len [/interface bridge port find interface=$iface]] = 0) do={
        /interface bridge port add bridge=bridge interface=$iface
    }
}

:log info "LAN bridge ready"

# =========================================================
# STEP 5 — Hotspot Bridge (wlan1)
# =========================================================

:if ([:len [/interface bridge find name="hotspot-bridge"]] = 0) do={
    /interface bridge add name=hotspot-bridge protocol-mode=none comment="inovatech-hotspot"
}

:if ([:len [/interface bridge port find interface=wlan1]] = 0) do={
    /interface bridge port add bridge=hotspot-bridge interface=wlan1
}

:if ([:len [/ip address find where address~"' . $hotspot_address . '"]] = 0) do={
    /ip address add address=' . $hotspot_address . '/24 interface=hotspot-bridge comment="inovatech-hotspot"
}

:log info "Hotspot bridge ready"

# =========================================================
# STEP 6 — WiFi SSID
# =========================================================

:if ([:len [/interface wireless find name=wlan1]] > 0) do={
    /interface wireless set wlan1 ssid="' . $hotspot_name . '" mode=ap-bridge disabled=no
    :log info "WiFi SSID set"
}

# =========================================================
# STEP 7 — IP Pools
# =========================================================

:if ([:len [/ip pool find name="lan_pool"]] = 0) do={
    /ip pool add name=lan_pool ranges=' . $lan_pool . '
}

:if ([:len [/ip pool find name="pppoe_pool"]] = 0) do={
    /ip pool add name=pppoe_pool ranges=' . $pppoe_pool . '
}

:if ([:len [/ip pool find name="hotspot_pool"]] = 0) do={
    /ip pool add name=hotspot_pool ranges=' . $hotspot_pool . '
}

:log info "Pools configured"

# =========================================================
# STEP 8 — DHCP Servers
#           dns-server points to 8.8.8.8 directly so clients
#           get internet DNS even before local resolver is ready
# =========================================================

:if ([:len [/ip dhcp-server find name="dhcp-lan"]] = 0) do={
    /ip dhcp-server add name=dhcp-lan interface=bridge address-pool=lan_pool disabled=no lease-time=1h
}
:if ([:len [/ip dhcp-server network find address~"' . $lan_network . '"]] = 0) do={
    /ip dhcp-server network add address=' . $lan_network . '/24 gateway=' . $lan_ip . ' dns-server=8.8.8.8,8.8.4.4 comment="inovatech-lan"
}

:if ([:len [/ip dhcp-server find name="dhcp-hotspot"]] = 0) do={
    /ip dhcp-server add name=dhcp-hotspot interface=hotspot-bridge address-pool=hotspot_pool disabled=no lease-time=1h
}
:if ([:len [/ip dhcp-server network find address~"' . $hotspot_network . '"]] = 0) do={
    /ip dhcp-server network add address=' . $hotspot_network . '/24 gateway=' . $hotspot_address . ' dns-server=8.8.8.8,8.8.4.4 comment="inovatech-hotspot"
}

:log info "DHCP servers configured"

# =========================================================
# STEP 9 — NAT
#           Blanket masquerade — covers LAN, hotspot and PPPoE
# =========================================================

:if ([:len [/ip firewall nat find comment="inovatech-masquerade"]] = 0) do={
    /ip firewall nat add chain=srcnat action=masquerade comment="inovatech-masquerade"
}

:log info "NAT configured"

# =========================================================
# STEP 10 — Firewall
# =========================================================

# VPN rule at position 0 — must be above any drop rules
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

:log info "Firewall configured"

# =========================================================
# STEP 11 — DNS
# =========================================================

/ip dns set allow-remote-requests=yes servers=8.8.8.8,8.8.4.4

:log info "DNS configured"

# =========================================================
# STEP 12 — Hotspot + captive portal
# =========================================================

:if ([:len [/ip hotspot profile find name="inovatech-profile"]] = 0) do={
    /ip hotspot profile add \
        name=inovatech-profile \
        hotspot-address=' . $hotspot_address . ' \
        dns-name="' . $captive_dns . '" \
        html-directory=hotspot \
        login-by=http-chap,http-pap \
        http-cookie-lifetime=1d \
        use-radius=no
}

:if ([:len [/ip hotspot find name="hotspot1"]] = 0) do={
    /ip hotspot add \
        name=hotspot1 \
        interface=hotspot-bridge \
        profile=inovatech-profile \
        address-pool=hotspot_pool \
        disabled=no
}

:log info "Hotspot configured"

# =========================================================
# STEP 13 — Walled Garden
# =========================================================

:if ([:len [/ip hotspot walled-garden find comment="inovatech-wg"]] = 0) do={
    /ip hotspot walled-garden add dst-host="*.inovatech.co.ke" action=allow comment="inovatech-wg"
}

:if ([:len [/ip hotspot walled-garden find comment="inovatech-wg-bare"]] = 0) do={
    /ip hotspot walled-garden add dst-host="inovatech.co.ke" action=allow comment="inovatech-wg-bare"
}

:if ([:len [/ip hotspot walled-garden ip find comment="inovatech-wg-ip"]] = 0) do={
    /ip hotspot walled-garden ip add dst-address=102.68.86.80 action=accept comment="inovatech-wg-ip"
}

:log info "Walled garden configured"

# =========================================================
# STEP 14 — PPPoE Server
# =========================================================

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

:if ([:len [/interface pppoe-server server find service-name="PPPoE_Inovatech"]] = 0) do={
    /interface pppoe-server server add \
        service-name=PPPoE_Inovatech \
        interface=bridge \
        default-profile="' . $pppoe_profile . '" \
        authentication=chap,mschap2 \
        disabled=no
}

:log info "PPPoE configured"

# =========================================================
# STEP 15 — Router Identity
# =========================================================

/system identity set name="' . $router['name'] . '"

:log info "=== Inovatech Setup COMPLETE: ' . $router['name'] . ' ==="
';