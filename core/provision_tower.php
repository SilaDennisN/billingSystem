<?php

use RouterOS\Query;

require_once "db.php";
require_once "router.php";

/**
 * Provision a tower (IDEMPOTENT)
 */
function provision_tower($tower_id)
{
    global $pdo;

    // Fetch tower + router
    $stmt = $pdo->prepare("
        SELECT t.*, r.host, r.trunk_port
        FROM towers t
        JOIN routers r ON t.router_id = r.router_id
        WHERE t.tower_id = :tower_id
        LIMIT 1
    ");
    $stmt->execute(['tower_id' => $tower_id]);
    $tower = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tower) {
        error_log("Tower {$tower_id} not found");
        return false;
    }

    $hotspot_vlan = is_numeric($tower['hotspot_vlan']) ? (int)$tower['hotspot_vlan'] : null;
    $pppoe_vlan   = is_numeric($tower['pppoe_vlan'])   ? (int)$tower['pppoe_vlan']   : null;
    $trunk_port   = !empty($tower['trunk_port']) ? $tower['trunk_port'] : 'ether5';

    if ($hotspot_vlan === null || $pppoe_vlan === null) {
        error_log("Missing VLAN IDs for {$tower['name']}");
        return false;
    }

    $client = router_connect($tower['router_id']);
    if (!$client) {
        error_log("Router connection failed");
        return false;
    }

    $hotspot_vlan_name = "vlan{$hotspot_vlan}-hotspot";
    $pppoe_vlan_name   = "vlan{$pppoe_vlan}-pppoe";
    $pool_name         = "pool-hotspot-{$hotspot_vlan}";

    /* ================= HELPER FUNCTIONS ================= */

    $exists = function ($path, $key, $value) use ($client) {
        $q = new Query($path);
        $q->where($key, '=', $value);
        return count($client->query($q)->read()) > 0;
    };

    /* ================= HOTSPOT VLAN ================= */

    // VLAN
    if (!$exists('/interface/vlan/print', 'name', $hotspot_vlan_name)) {
        $q = new Query('/interface/vlan/add');
        $q->equal('name', $hotspot_vlan_name);
        $q->equal('vlan-id', (string)$hotspot_vlan);
        $q->equal('interface', $trunk_port);
        $client->query($q)->read();
    }

    // IP Address
    if (!empty($tower['hotspot_subnet'])) {
        $hotspot_ip = trim($tower['hotspot_subnet']) . '/24';

        if (!$exists('/ip/address/print', 'address', $hotspot_ip)) {
            $q = new Query('/ip/address/add');
            $q->equal('interface', $hotspot_vlan_name);
            $q->equal('address', $hotspot_ip);
            $client->query($q)->read();
        }
    }

    // DHCP Pool
    if (!empty($tower['hotspot_subnet'])) {
        if (!$exists('/ip/pool/print', 'name', $pool_name)) {
            $q = new Query('/ip/pool/add');
            $q->equal('name', $pool_name);
            $q->equal('ranges', subnet_to_range($tower['hotspot_subnet']));
            $client->query($q)->read();
        }
    }

    // DHCP Server
    $dhcp_name = "dhcp-hotspot-{$hotspot_vlan}";
    if (!$exists('/ip/dhcp-server/print', 'name', $dhcp_name)) {
        $q = new Query('/ip/dhcp-server/add');
        $q->equal('name', $dhcp_name);
        $q->equal('interface', $hotspot_vlan_name);
        $q->equal('address-pool', $pool_name);
        $q->equal('lease-time', '12h');
        $client->query($q)->read();
    }

    /* ================= PPPoE VLAN ================= */

    // VLAN
    if (!$exists('/interface/vlan/print', 'name', $pppoe_vlan_name)) {
        $q = new Query('/interface/vlan/add');
        $q->equal('name', $pppoe_vlan_name);
        $q->equal('vlan-id', (string)$pppoe_vlan);
        $q->equal('interface', $trunk_port);
        $client->query($q)->read();
    }

    // IP Address
    if (!empty($tower['pppoe_subnet'])) {
        $pppoe_ip = trim($tower['pppoe_subnet']) . '/24';

        if (!$exists('/ip/address/print', 'address', $pppoe_ip)) {
            $q = new Query('/ip/address/add');
            $q->equal('interface', $pppoe_vlan_name);
            $q->equal('address', $pppoe_ip);
            $client->query($q)->read();
        }
    }

    // PPPoE Server
    $pppoe_service = "pppoe-{$pppoe_vlan}";
    if (!$exists('/interface/pppoe-server/server/print', 'service-name', $pppoe_service)) {
        $q = new Query('/interface/pppoe-server/server/add');
        $q->equal('interface', $pppoe_vlan_name);
        $q->equal('service-name', $pppoe_service);
        $q->equal('default-profile', 'default');
        $client->query($q)->read();
    }

    echo "✅ Tower {$tower['name']} provisioned (idempotent).";
    return true;
}

/**
 * Convert subnet to MikroTik DHCP pool range
 */
function subnet_to_range($subnet)
{
    $parts = explode('.', trim($subnet));
    if (count($parts) !== 4) return '';
    return "{$parts[0]}.{$parts[1]}.{$parts[2]}.10-{$parts[0]}.{$parts[1]}.{$parts[2]}.254";
}
