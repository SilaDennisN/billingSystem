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

if ($name === '' || $host === '' || $api_user === '' || $api_pass === '') {
    $_SESSION['error'] = 'All fields are required';
    header("Location: ../routers/add");
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO routers (name, ip, api_port, api_user, password)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $name,
    $host,
    $api_port,
    $api_user,
    $api_pass
]);

$router_id = $pdo->lastInsertId();

/**
 * Redirect immediately to connection test
 */
header("Location: ../routers/index");
exit;
