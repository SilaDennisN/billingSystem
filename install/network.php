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

$lan_ip      = "192.168.50.1";
$lan_pool    = "192.168.50.10-192.168.50.200";
$pppoe_pool  = "192.168.50.201-192.168.50.254";
$lan_network = preg_replace('/\.\d+$/', '.0', $lan_ip);

echo ':log info "=== [2/5] LAN + DHCP + NAT START ==="

# ---- Disable STP while we change things
/interface bridge set bridge protocol-mode=none

# ---- Add new LAN IP first - stay reachable during transition
:if ([:len [/ip address find where address~"' . $lan_ip . '"]] = 0) do={
    /ip address add address=' . $lan_ip . '/24 interface=bridge comment="inovatech-lan"
}
:log info "New LAN ' . $lan_ip . ' is live - reconnect here if you lose WinBox"
:delay 3s

# ---- Strip default config (old IPs, DHCP client, server, pools, NAT)
# NOTE: Do NOT remove hotspot or PPPoE here - Phase 2 handles those cleanly
:foreach a in=[/ip address find where comment!="inovatech-lan" && comment!="inovatech-vpn" && interface!="lo"] do={
    /ip address remove $a
}
/ip dhcp-client remove [find]
/ip dhcp-server remove [find]
/ip pool remove [find]
/ip firewall nat remove [find]

:log info "Default config stripped"

# ---- Re-enable STP
/interface bridge set bridge protocol-mode=rstp

# ---- LAN ports: ether2-ether5 into existing bridge
:foreach iface in={"ether2";"ether3";"ether4";"ether5"} do={
    :if ([:len [/interface bridge port find interface=$iface]] = 0) do={
        /interface bridge port add bridge=bridge interface=$iface comment="inovatech-lan"
    }
}

# ---- Restore WAN DHCP on ether1
:if ([:len [/ip dhcp-client find interface=ether1]] = 0) do={
    /ip dhcp-client add interface=ether1 disabled=no add-default-route=yes use-peer-dns=yes comment="inovatech-wan"
}
:delay 5s
:log info "WAN DHCP restored on ether1"

# ---- IP Pools (LAN + PPPoE - hotspot pool created in Phase 2)
:if ([:len [/ip pool find name="lan_pool"]] = 0) do={
    /ip pool add name=lan_pool ranges=' . $lan_pool . '
}
:if ([:len [/ip pool find name="pppoe_pool"]] = 0) do={
    /ip pool add name=pppoe_pool ranges=' . $pppoe_pool . '
}

# ---- LAN DHCP server
:if ([:len [/ip dhcp-server find name="dhcp-lan"]] = 0) do={
    /ip dhcp-server add name=dhcp-lan interface=bridge address-pool=lan_pool disabled=no lease-time=1h comment="inovatech-lan"
}
:if ([:len [/ip dhcp-server network find address~"' . $lan_network . '"]] = 0) do={
    /ip dhcp-server network add address=' . $lan_network . '/24 gateway=' . $lan_ip . ' dns-server=8.8.8.8,8.8.4.4 comment="inovatech-lan"
}

# ---- NAT masquerade
:if ([:len [/ip firewall nat find comment="inovatech-masquerade"]] = 0) do={
    /ip firewall nat add chain=srcnat action=masquerade comment="inovatech-masquerade"
}

:log info "=== [2/5] LAN + DHCP + NAT DONE ==="
:log info "Session will drop here - reconnect to 192.168.50.1 then run Part 2"
';