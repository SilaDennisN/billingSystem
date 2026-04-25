<?php
require_once "../core/db.php";
require_once "../core/router.php";
use RouterOS\Query;

/* Get expired active sessions */
$sessions = $pdo->query("
    SELECT hs.*, r.router_id
    FROM hotspot_sessions hs
    JOIN routers r ON r.router_id = hs.router_id
    WHERE hs.status = 'active'
      AND hs.expires_at <= NOW()
")->fetchAll();

foreach ($sessions as $s) {

    $client = router_connect($s['router_id']);
    if (!$client) continue;

    $mac = $s['mac_address'];
    $ip  = $s['ip_address'];

    /* Remove IP binding */
    $bindings = $client->query(
        (new Query('/ip/hotspot/ip-binding/print'))
            ->where('mac-address', $mac)
    )->read();

    foreach ($bindings as $b) {
        $client->query(
            (new Query('/ip/hotspot/ip-binding/remove'))
                ->equal('.id', $b['.id'])
        )->read();
    }

    /* Remove ARP */
    $arps = $client->query(
        (new Query('/ip/arp/print'))
            ->where('mac-address', $mac)
    )->read();

    foreach ($arps as $a) {
        $client->query(
            (new Query('/ip/arp/remove'))
                ->equal('.id', $a['.id'])
        )->read();
    }

    /* Remove firewall rules */
    foreach (['mangle','filter'] as $table) {
        $rules = $client->query(
            (new Query("/ip/firewall/$table/print"))
                ->where('src-mac-address', $mac)
        )->read();

        foreach ($rules as $r) {
            $client->query(
                (new Query("/ip/firewall/$table/remove"))
                    ->equal('.id', $r['.id'])
            )->read();
        }
    }

    /* Update DB */
    $pdo->prepare("
        UPDATE hotspot_sessions
        SET status='expired'
        WHERE session_id=?
    ")->execute([$s['session_id']]);
}
