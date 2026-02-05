<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO routers (name, host, api_port, username, password)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $_POST['name'],
    $_POST['host'],
    $_POST['api_port'],
    $_POST['username'],
    password_hash($_POST['password'], PASSWORD_DEFAULT)
]);

header("Location: index.php");
exit;
