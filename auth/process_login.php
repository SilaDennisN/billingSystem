<?php
require_once '../core/db.php';
require_once '../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['error'] = 'All fields are required';
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['error'] = 'Invalid login credentials';
    header('Location: login.php');
    exit;
}

// update last login
$pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
    ->execute([$user['user_id']]);

login($user);

header('Location: ../dashboard/index.php');
exit;
