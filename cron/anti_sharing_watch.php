<?php
require_once __DIR__ . "/router.php";
use RouterOS\Query;

$clients = $client->query(
    new Query('/ip/firewall/connection/print')
)->read();

$mac_counts = [];

foreach ($clients as $c) {
    if (!empty($c['src-mac-address'])) {
        $mac_counts[$c['src-mac-address']][] = $c;
    }
}

foreach ($mac_counts as $mac => $connections) {
    if (count($connections) > 50) {
        // BLOCK
        $client->query(
            (new Query('/ip/hotspot/ip-binding/add'))
                ->equal('mac-address', $mac)
                ->equal('type', 'blocked')
                ->equal('comment', 'AUTO BLOCK SHARING')
        )->read();
    }
}

?>