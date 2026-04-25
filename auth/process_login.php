<?php
session_start();
require_once '../core/db.php';
require_once '../core/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Accepts either a username or email address
$login_input = trim($_POST['username'] ?? '');
$password    = $_POST['password'] ?? '';

if ($login_input === '' || $password === '') {
    $_SESSION['error'] = 'All fields are required.';
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
$stmt->execute([$login_input, $login_input]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['error'] = 'Invalid login credentials.';
    header('Location: login.php');
    exit;
}

/* -------------------------
   Email verification gate
--------------------------*/

if (empty($user['email_verified'])) {
    // Store the email so the resend page can pre-fill it
    $_SESSION['unverified_email'] = $user['email'];

    $_SESSION['error'] = "Your email address <strong>{$user['email']}</strong> has not been verified. "
                       . "Please check your inbox for the verification link, or "
                       . "<a href='resend_verification.php'>click here to resend it</a>.";

    header('Location: login.php');
    exit;
}

/* -------------------------
   All checks passed — log in
--------------------------*/

// Update last login timestamp
$pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
    ->execute([$user['user_id']]);

login($user);

header('Location: ../dashboard/index.php');
exit;