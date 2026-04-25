<?php
error_reporting(0);
ini_set('display_errors', '0');

ob_start();
require_once "../core/db.php";
require_once "../core/ssh.php";
ob_end_clean();

header("Content-Type: text/plain");

$router_id = $_GET['router_id'] ?? null;
$key       = $_GET['key']       ?? null;
if (!$router_id || !$key) die("Missing parameters");

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();
if (!$router) die("Router not found");

// Save the router's WireGuard public key
$pdo->prepare("UPDATE routers SET wg_public_key=? WHERE router_id=?")
    ->execute([$key, $router_id]);

// Register the peer on the VPS side
addWireguardPeer($key, $router['vpn_ip']);

$vpn_ip         = $router['vpn_ip'];
$vps_public_key = "ZeMFS1veWadTZfzOVXVY6uC+3DSVAqATulLnQ0KNkBs=";
$vps_ip         = "102.68.86.80";

echo ':log info "=== [1/5] WireGuard VPN START ==="

# VPN IP on the WireGuard interface
:if ([:len [/ip address find where address~"' . $vpn_ip . '"]] = 0) do={
    /ip address add address=' . $vpn_ip . '/24 interface=wg-billing comment="inovatech-vpn"
}

# WireGuard peer pointing to the VPS
:if ([:len [/interface wireguard peers find interface=wg-billing]] = 0) do={
    /interface wireguard peers add \
        interface=wg-billing \
        public-key="' . $vps_public_key . '" \
        endpoint-address=' . $vps_ip . ' \
        endpoint-port=51820 \
        allowed-address=10.50.0.0/24 \
        persistent-keepalive=25 \
        comment="inovatech-vpn"
}



/ip service enable api

:log info "=== [1/5] WireGuard VPN DONE ==="
';