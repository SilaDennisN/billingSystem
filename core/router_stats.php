<?php

require_once __DIR__ . '/router.php';
require_once __DIR__ . '/db.php';

use RouterOS\Query;

function formatBytes(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1)    . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 1)       . ' KB';
    return $bytes . ' B';
}

function getRouterStats(PDO $pdo, int $router_id): array
{
    $client = router_connect($router_id);

    if (!$client) {
        return ['status' => 'offline'];
    }

    try {

        /* ── System Resources ── */
        $res       = $client->query('/system/resource/print')->read();
        $res       = $res[0] ?? [];

        $totalMem  = (int)($res['total-memory']    ?? 0);
        $freeMem   = (int)($res['free-memory']     ?? 0);
        $usedMem   = $totalMem - $freeMem;
        $memPct    = $totalMem  > 0 ? round(($usedMem  / $totalMem)  * 100, 1) : 0;

        $totalDisk = (int)($res['total-hdd-space'] ?? 0);
        $freeDisk  = (int)($res['free-hdd-space']  ?? 0);
        $usedDisk  = $totalDisk - $freeDisk;
        $diskPct   = $totalDisk > 0 ? round(($usedDisk / $totalDisk) * 100, 1) : 0;

        $cpu      = (int)($res['cpu-load']  ?? 0);
        $uptime   = $res['uptime']          ?? 'Unknown';
        $board    = $res['board-name']      ?? 'Unknown';
        $version  = $res['version']         ?? 'Unknown';
        $platform = $res['platform']        ?? 'Unknown';

        /* ── Identity ── */
        $identity = $client->query('/system/identity/print')->read();
        $hostname = $identity[0]['name'] ?? 'MikroTik';

        /* ── Traffic on ether1 ── */
        $trafficQ = new Query('/interface/monitor-traffic');
        $trafficQ->equal('interface', 'ether1');
        $trafficQ->equal('once', '');
        $traffic  = $client->query($trafficQ)->read()[0] ?? [];

        $rx = isset($traffic['rx-bits-per-second'])
            ? round($traffic['rx-bits-per-second'] / 1_000_000, 2) : 0;
        $tx = isset($traffic['tx-bits-per-second'])
            ? round($traffic['tx-bits-per-second'] / 1_000_000, 2) : 0;

        /* ── All Interfaces ── */
        $ifaces    = $client->query('/interface/print')->read();
        $ifaceOut  = [];
        foreach ($ifaces as $iface) {
            $ifaceOut[] = [
                'name'     => $iface['name']        ?? '',
                'type'     => $iface['type']        ?? '',
                'running'  => ($iface['running']  ?? 'false') === 'true',
                'disabled' => ($iface['disabled'] ?? 'false') === 'true',
                'comment'  => $iface['comment']     ?? '',
                'mac'      => $iface['mac-address'] ?? '',
                'rx_byte'  => formatBytes((int)($iface['rx-byte'] ?? 0)),
                'tx_byte'  => formatBytes((int)($iface['tx-byte'] ?? 0)),
            ];
        }

        /* ── Active Users: Hotspot + PPPoE ── */
        $hotspotActive = $client->query('/ip/hotspot/active/print')->read();
        $pppActive     = $client->query('/ppp/active/print')->read();

        $hotspotCount  = is_array($hotspotActive) ? count($hotspotActive) : 0;
        $pppCount      = is_array($pppActive)     ? count($pppActive)     : 0;
        $totalUsers    = $hotspotCount + $pppCount;

        $userRows = [];

        foreach ($hotspotActive as $u) {
            $userRows[] = [
                'type'   => 'hotspot',
                'name'   => $u['user']         ?? '',
                'ip'     => $u['address']      ?? '',
                'mac'    => $u['mac-address']  ?? '',
                'uptime' => $u['uptime']       ?? '',
                'rx'     => formatBytes((int)($u['bytes-in']  ?? 0)),
                'tx'     => formatBytes((int)($u['bytes-out'] ?? 0)),
            ];
        }

        foreach ($pppActive as $u) {
            $userRows[] = [
                'type'   => 'pppoe',
                'name'   => $u['name']    ?? '',
                'ip'     => $u['address'] ?? '',
                'mac'    => '',
                'uptime' => $u['uptime']  ?? '',
                'rx'     => formatBytes((int)($u['bytes-in']  ?? 0)),
                'tx'     => formatBytes((int)($u['bytes-out'] ?? 0)),
            ];
        }

        /* ── DHCP Leases ── */
        $leases       = $client->query('/ip/dhcp-server/lease/print')->read();
        $activeLeases = array_filter($leases, fn($l) => ($l['status'] ?? '') === 'bound');
        $dhcpCount    = count($activeLeases);

        return [
            'status'        => 'online',

            /* System */
            'cpu'           => $cpu,
            'memory'        => $memPct,
            'memory_used'   => formatBytes($usedMem),
            'memory_total'  => formatBytes($totalMem),
            'disk'          => $diskPct,
            'disk_used'     => formatBytes($usedDisk),
            'disk_total'    => formatBytes($totalDisk),
            'uptime'        => $uptime,
            'board'         => $board,
            'version'       => $version,
            'platform'      => $platform,
            'hostname'      => $hostname,

            /* Traffic */
            'rx'            => $rx,
            'tx'            => $tx,

            /* Users */
            'users'         => $totalUsers,
            'hotspot_users' => $hotspotCount,
            'pppoe_users'   => $pppCount,
            'dhcp_leases'   => $dhcpCount,
            'user_rows'     => $userRows,

            /* Interfaces */
            'interfaces'    => $ifaceOut,
        ];

    } catch (\Throwable $e) {
        return [
            'status'  => 'error',
            'message' => $e->getMessage(),
            'cpu' => 0, 'memory' => 0, 'disk' => 0,
            'users' => 0, 'rx' => 0, 'tx' => 0,
            'uptime' => 'Error', 'board' => '', 'version' => '',
            'platform' => '', 'hostname' => '',
            'hotspot_users' => 0, 'pppoe_users' => 0, 'dhcp_leases' => 0,
            'user_rows' => [], 'interfaces' => [],
        ];
    }
}