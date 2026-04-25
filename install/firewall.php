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

echo ':log info "=== [5/5] Firewall + DNS START ==="

# ---- VPN input rule - must sit above any drop rules (place-before=0)
/ip firewall filter remove [find comment="inovatech-vpn"]
/ip firewall filter add \
    chain=input \
    in-interface=wg-billing \
    action=accept \
    comment="inovatech-vpn" \
    place-before=0

# ---- Allow established/related (keeps active sessions alive)
:if ([:len [/ip firewall filter find comment="inovatech-established"]] = 0) do={
    /ip firewall filter add chain=input connection-state=established,related action=accept comment="inovatech-established"
}

# ---- Drop invalid packets
:if ([:len [/ip firewall filter find comment="inovatech-invalid"]] = 0) do={
    /ip firewall filter add chain=input connection-state=invalid action=drop comment="inovatech-invalid"
}

# ---- WinBox access (port 8291)
:if ([:len [/ip firewall filter find comment="inovatech-winbox"]] = 0) do={
    /ip firewall filter add chain=input protocol=tcp dst-port=8291 action=accept comment="inovatech-winbox"
}

# ---- Allow DNS queries from clients (UDP + TCP 53)
:if ([:len [/ip firewall filter find comment="inovatech-dns"]] = 0) do={
    /ip firewall filter add chain=input protocol=udp dst-port=53 action=accept comment="inovatech-dns"
    /ip firewall filter add chain=input protocol=tcp dst-port=53 action=accept comment="inovatech-dns"
}

# ---- DNS resolver
/ip dns set allow-remote-requests=yes servers=8.8.8.8,8.8.4.4

# ---- Router identity
/system identity set name="' . $router['name'] . '"

:log info "=== [5/5] Firewall + DNS DONE ==="
:log info "Router: ' . $router['name'] . '"
';