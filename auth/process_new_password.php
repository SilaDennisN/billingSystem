<?php
session_start();
date_default_timezone_set('Africa/Nairobi');
require_once '../core/db.php';

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if($password !== $confirm){
    $_SESSION['error'] = "Passwords do not match";
    header("Location: new_password?token=".$token);
    exit;
}

$stmt = $pdo->prepare("
SELECT * FROM password_resets
WHERE token = ? AND expires_at > NOW()
");

$stmt->execute([$token]);
$reset = $stmt->fetch();

if(!$reset){
    // die("Invalid or expired token.");
    $_SESSION['error'] = "Invalid Reset Token, Please Try Again or request a new one.";
    header("Location: new_password?token=".$token);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->prepare("
UPDATE users SET password_hash = ?
WHERE email = ?
")->execute([$password_hash, $reset['email']]);

$pdo->prepare("DELETE FROM password_resets WHERE email = ?")
->execute([$reset['email']]);

$_SESSION['success'] = "Password updated successfully.";
header("Location: login.php");
exit;