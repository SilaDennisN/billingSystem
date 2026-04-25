<?php

function provision_hotspot(RouterAPI $api)
{
    // =========================
    // 1. IP POOL
    // =========================
    try {
        $api->run('/ip/pool/add', [
            'name' => 'hotspot_pool',
            'ranges' => '192.168.50.10-192.168.50.150'
        ]);
    } catch (Exception $e) {}

    // =========================
    // 2. DHCP SERVER (safe add)
    // =========================
    try {
        $api->run('/ip/dhcp-server/add', [
            'name' => 'dhcp-hotspot',
            'interface' => 'bridge',
            'address-pool' => 'hotspot_pool',
            'disabled' => 'no'
        ]);
    } catch (Exception $e) {}

    // =========================
    // 3. DHCP NETWORK (gateway + DNS)
    // =========================
    try {
        $api->run('/ip/dhcp-server/network/add', [
            'address' => '192.168.50.0/24',
            'gateway' => '192.168.50.1',
            'dns-server' => '8.8.8.8,8.8.4.4'
        ]);
    } catch (Exception $e) {}

    // =========================
    // 4. HOTSPOT PROFILE (CAPTIVE CORE)
    // =========================
    try {
        $api->run('/ip/hotspot/profile/add', [
            'name' => 'hs-profile',
            'hotspot-address' => '192.168.50.1',
            'login-by' => 'http-chap,cookie',
            'dns-name' => 'hotspot.local'
        ]);
    } catch (Exception $e) {}

    // =========================
    // 5. HOTSPOT SERVER (THIS ENABLES CAPTIVE PORTAL)
    // =========================
    try {
        $api->run('/ip/hotspot/add', [
            'name' => 'hotspot1',
            'interface' => 'bridge',
            'address-pool' => 'hotspot_pool',
            'profile' => 'hs-profile',
            'disabled' => 'no'
        ]);
    } catch (Exception $e) {}

    // =========================
    // 6. Walled Garden (allow your VPS + domain)
    // =========================
    try {
        $api->run('/ip/hotspot/walled-garden/add', [
            'dst-host' => 'billing.inovatech.co.ke',
            'action' => 'accept'
        ]);
    } catch (Exception $e) {}

    return "Hotspot + Captive Portal configured";
}