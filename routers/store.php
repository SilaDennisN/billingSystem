<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../routers");
    exit;
}

$name     = trim($_POST['name'] ?? '');
$host     = trim($_POST['host'] ?? '');
$api_port = (int)($_POST['api_port'] ?? 8728);
$api_user = trim($_POST['api_user'] ?? '');
$api_pass = trim($_POST['api_pass'] ?? '');

if ($name === '' || $api_user === '' || $api_pass === '') {
    $_SESSION['error'] = 'All fields are required';
    header("Location: ../routers");
    exit;
}

try {

    $pdo->beginTransaction();

    /* -----------------------
   Generate Next VPN IP
----------------------- */

    // Get last octet
    $stmt = $pdo->query("SELECT vpn_ip FROM routers WHERE vpn_ip LIKE '10.50.0.%' ORDER BY router_id DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last && $last['vpn_ip']) {
        $parts = explode('.', $last['vpn_ip']);
        $next  = (int)$parts[3] + 1;

        if ($next > 254) {
            throw new Exception("VPN subnet exhausted");
        }
    } else {
        $next = 2;
    }

    $vpn_ip = "10.50.0." . $next;
    /* -----------------------
       Insert Router
    ----------------------- */

    $stmt = $pdo->prepare("
        INSERT INTO routers (name, host, vpn_ip, api_port, api_user, api_pass)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $name,
        $vpn_ip,     // host will be VPN IP
        $vpn_ip,
        $api_port,
        $api_user,
        $api_pass
    ]);

    $router_id = $pdo->lastInsertId();

    /* -----------------------
       Assign Router To User
    ----------------------- */

    $user_id = $_SESSION['user']['id'];

    $stmt = $pdo->prepare("
        INSERT INTO user_router_access (user_id, router_id)
        VALUES (?, ?)
    ");

    $stmt->execute([
        $user_id,
        $router_id
    ]);

    $pdo->commit();

    $_SESSION['success'] = "Router added successfully";
} catch (Exception $e) {

    $pdo->rollBack();
    $_SESSION['error'] = "Failed to add router";
}

header("Location: ../install/routerSetup?router_id=$router_id");
exit;
