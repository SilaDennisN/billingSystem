<?php
require_once '../core/db.php';

$type  = $_GET['type'] ?? '';
$value = trim($_GET['value'] ?? '');

if ($value === '') {
    exit;
}

if ($type === 'username') {

    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$value]);

    echo $stmt->fetch() ? "taken" : "available";
}

if ($type === 'email') {

    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$value]);

    echo $stmt->fetch() ? "taken" : "available";
}