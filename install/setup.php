<?php

require_once "../core/db.php";
require_once "../core/ssh.php";
require_once "../vendor/autoload.php";

$router_id = $_GET['router_id'] ?? null;
$key       = $_GET['key'] ?? null;

if(!$router_id || !$key){
    die("Missing parameters");
}

/* -------------------------
Get Router
--------------------------*/

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();

if(!$router){
    die("Router not found");
}

$vpn_ip   = $router['vpn_ip'];
$api_user = $router['api_user'];
$api_pass = $router['api_pass'];

/* -------------------------
Save Router Key
--------------------------*/

$stmt = $pdo->prepare("
UPDATE routers 
SET wg_public_key=? 
WHERE router_id=?
");

$stmt->execute([$key, $router_id]);

/* -------------------------
Add Peer via SSH
--------------------------*/

addWireguardPeer($key, $vpn_ip);

/* -------------------------
Return Router Config
--------------------------*/

$vps_public_key = "ZeMFS1veWadTZfzOVXVY6uC+3DSVAqATulLnQ0KNkBs=";
$vps_ip = "102.68.86.80";

header("Content-Type: text/plain");

echo "

:log info \"Applying VPN configuration\"

/ip address add address=$vpn_ip/24 interface=wg-billing

/interface wireguard peers add \
interface=wg-billing \
public-key=\"$vps_public_key\" \
endpoint-address=$vps_ip \
endpoint-port=51820 \
allowed-address=10.50.0.0/24 \
persistent-keepalive=25

/ip service enable api

/system identity set name=\"{$router['name']}\"

:log info \"VPN connected successfully\"

";