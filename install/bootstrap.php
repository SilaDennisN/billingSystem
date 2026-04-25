<?php
error_reporting(0);
ini_set('display_errors', '0');

ob_start();
require_once "../core/db.php";
ob_end_clean();

header("Content-Type: text/plain");

$router_id = $_GET['router_id'] ?? null;
$part      = (int)($_GET['part'] ?? 1);

if (!$router_id) die("Router ID missing");
if (!in_array($part, [1, 2])) die("Invalid part");

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();
if (!$router) die("Router not found");

$base = "https://billing.inovatech.co.ke/install";

/*
 * Part 1 — runs on the default IP (192.168.88.1).
 *           network.rsc changes the LAN IP, which will drop the session.
 *           Keep ONLY steps that must happen before the IP change here.
 *
 * Part 2 — runs after reconnecting to the new IP (192.168.50.1).
 *           Everything that needs the VPN tunnel already up goes here.
 */
$parts = [
    1 => [
        'title' => 'Part 1 of 2 — VPN + Network',
        'note'  => 'Router will reconfigure LAN after this. Reconnect to 192.168.50.1 then run Part 2.',
        'steps' => [
            ['file' => '01-wireguard.rsc', 'url' => "$base/wireguard.php?router_id=$router_id", 'with_key' => true,  'label' => 'WireGuard VPN'],
        ],
    ],
    2 => [
        'title' => 'Part 2 of 2 — Hotspot + PPPoE + Firewall',
        'note'  => 'Completes provisioning. Runs over the VPN tunnel.',
        'steps' => [
            ['file' => '03-hotspot.rsc',  'url' => "$base/hotspot.php?router_id=$router_id",  'with_key' => false, 'label' => 'Hotspot + Captive Portal'],
            ['file' => '04-pppoe.rsc',    'url' => "$base/pppoe.php?router_id=$router_id",    'with_key' => false, 'label' => 'PPPoE Server'],
            ['file' => '05-firewall.rsc', 'url' => "$base/firewall.php?router_id=$router_id", 'with_key' => false, 'label' => 'Firewall + DNS'],
        ],
    ],
];

$current = $parts[$part];
$steps   = $current['steps'];
$total   = count($steps);

echo ':log info "========================================"
:log info "  Inovatech Bootstrap — ' . $current['title'] . '"
:log info "========================================"

';

// Part 1 needs to read the WireGuard public key at runtime
if ($part === 1) {
    echo '# Create WireGuard interface if not already there
:if ([:len [/interface wireguard find name="wg-billing"]] = 0) do={
    /interface wireguard add name=wg-billing listen-port=13232
    :log info "wg-billing interface created"
}

:local pubkey   [/interface wireguard get wg-billing public-key]
:local routerid "' . $router_id . '"
:log info ("Public key: " . $pubkey)

';
}

foreach ($steps as $i => $step) {
    $n   = $i + 1;
    $url = $step['with_key']
        ? '"' . $step['url'] . '&key=" . $pubkey'
        : '"' . $step['url'] . '"';

    echo '# ----------------------------------------
# Step ' . $n . ' / ' . $total . ' — ' . $step['label'] . '
# ----------------------------------------
:log info "[' . $n . '/' . $total . '] Fetching: ' . $step['label'] . '"

:local ok' . $n . ' false; :local try' . $n . ' 0
:while ($ok' . $n . ' = false && $try' . $n . ' < 5) do={
    :do {
        /tool fetch mode=https url=(' . $url . ') dst-path=' . $step['file'] . '
        :set ok' . $n . ' true
    } on-error={
        :set try' . $n . ' ($try' . $n . ' + 1)
        :log warning ("[' . $n . '/' . $total . '] Attempt " . $try' . $n . ' . "/5 failed, retrying in 5s...")
        :delay 5s
    }
}
:if ($ok' . $n . ' = false) do={
    :log error "[' . $n . '/' . $total . '] FAILED: ' . $step['label'] . ' — fix and re-run this part."
    /quit
}

/import ' . $step['file'] . '
:log info "[' . $n . '/' . $total . '] OK: ' . $step['label'] . '"

';
}

if ($part === 1) {
    echo ':log info "========================================"
:log info "  Part 1 DONE — reconnect to 192.168.50.1"
:log info "  then run Part 2 to finish provisioning"
:log info "========================================"
';
} else {
    echo ':log info "========================================"
:log info "  Bootstrap COMPLETE — all steps passed"
:log info "========================================"
';
}