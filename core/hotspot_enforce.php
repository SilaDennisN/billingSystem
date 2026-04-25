<?php
use RouterOS\Query;

function enforce_hotspot_access($client, $router_id, $mac, $ip, $profile, $expires_at) {

    /* 1️⃣ Remove old bindings (safety) */
    $client->query(
        (new Query('/ip/hotspot/ip-binding/remove'))
            ->equal('.id', '*')
    )->read();

    /* 2️⃣ IP Binding (Bypass hotspot login) */
    $client->query(
        (new Query('/ip/hotspot/ip-binding/add'))
            ->equal('mac-address', $mac)
            ->equal('address', $ip)
            ->equal('type', 'bypassed')
            ->equal('comment', 'Paid access')
    )->read();

    /* 3️⃣ Static ARP Lock */
    $client->query(
        (new Query('/ip/arp/add'))
            ->equal('address', $ip)
            ->equal('mac-address', $mac)
            ->equal('interface', 'bridge-hotspot')
            ->equal('comment', 'LOCKED PAID DEVICE')
    )->read();

    /* 4️⃣ TTL = 1 (kills NAT routers & hotspots) */
    $client->query(
        (new Query('/ip/firewall/mangle/add'))
            ->equal('chain', 'forward')
            ->equal('src-mac-address', $mac)
            ->equal('action', 'change-ttl')
            ->equal('new-ttl', '1')
            ->equal('comment', 'ANTI-SHARING TTL')
    )->read();

    /* 5️⃣ Connection limit protection */
    $client->query(
        (new Query('/ip/firewall/filter/add'))
            ->equal('chain', 'forward')
            ->equal('src-mac-address', $mac)
            ->equal('connection-limit', '40,32')
            ->equal('action', 'drop')
            ->equal('comment', 'ANTI-SHARING CONN LIMIT')
    )->read();

    return true;
}
