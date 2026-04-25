<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/router.php";

use RouterOS\Query;

function provision_ap($ap_id)
{
    global $pdo;

    // Fetch AP + router
    $stmt = $pdo->prepare("
        SELECT ap.*, r.router_id, r.trunk_port
        FROM access_points ap
        JOIN routers r ON r.router_id = ap.router_id
        WHERE ap.ap_id = ?
    ");

    $stmt->execute([$ap_id]);
    $ap = $stmt->fetch();

    if (!$ap) return false;

    try {

        $client = router_connect($ap['router_id']);
        if (!$client) throw new Exception("Router offline");

        $vlanName = "vlan_" . $ap['vlan_id'];
        $bridgeName = "bridge_" . $ap['vlan_id'];

        $check = (new Query('/interface/vlan/print'))
            ->where('name', $vlanName);

        $exists = $client->query($check)->read();

        if (!$exists) {

            $add = (new Query('/interface/vlan/add'))
                ->equal('name', $vlanName)
                ->equal('vlan-id', (int)$ap['vlan_id'])
                ->equal('interface', $ap['trunk_port']);

            $client->query($add)->read();
        }


        /**
         * STEP 1 — Create VLAN
         */


        $query = (new Query('/interface/vlan/add'))
            ->equal('name', $vlanName)
            ->equal('vlan-id', (int)$ap['vlan_id'])
           ->equal('interface', $ap['trunk_port']);


        $client->query($query)->read();


        /**
         * STEP 2 — Create Bridge
         */
        // $query = (new Query('/interface/bridge/add'))
        //     ->equal('name', $bridgeName);

        // $client->query($query)->read();



        /**
         * STEP 3 — Add VLAN to Bridge
         */
        // $query = (new Query('/interface/bridge/port/add'))
        //     ->equal('bridge', $bridgeName)
        //     ->equal('interface', $vlanName);

        // $client->query($query)->read();


        /**
         * OPTIONAL FUTURE:
         * DHCP
         * IP pool
         * Hotspot
         * Queue
         */

        // SUCCESS
        $pdo->prepare("
            UPDATE access_points
            SET provision_status='provisioned'
            WHERE ap_id=?
        ")->execute([$ap_id]);

        return true;
    } catch (Exception $e) {

        error_log("AP Provision Failed: " . $e->getMessage());

        $pdo->prepare("
            UPDATE access_points
            SET provision_status='failed'
            WHERE ap_id=?
        ")->execute([$ap_id]);

        return false;
    }
}
