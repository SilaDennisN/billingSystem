<?php
require_once "../../core/db.php";
require_once "../../core/auth.php";
require_once "../../core/router.php";

if (!is_logged_in() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /auth/login");
    exit;
}

$router_id = $_POST['router_id'];
$username = $_POST['username'];

try {
    $client = router_connect($router_id);

    $client->query(
        (new RouterOS\Query('/ip/hotspot/active/remove'))
            ->equal('user', $username)
    )->read();

    // Optional: remove temporary user from router if needed
    // $client->query((new RouterOS\Query('/ip/hotspot/user/remove'))->equal('name',$username))->read();

} catch (Exception $e) {
    // Log router offline
}

// Back to live page
header("Location: /hotspot/live");
exit;
