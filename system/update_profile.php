<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');

if ($username === '') {
    header("Location: profile?error=Username required");
    exit;
}

/* Prevent duplicate usernames */
$stmt = $pdo->prepare("
    SELECT user_id FROM users WHERE username=? AND user_id!=?
");
$stmt->execute([$username, $user_id]);
if ($stmt->fetch()) {
    header("Location: profile?error=Username already exists");
    exit;
}

$stmt = $pdo->prepare("
    UPDATE users SET username=?, email=? WHERE user_id=?
");
$stmt->execute([$username, $email, $user_id]);

$_SESSION['username'] = $username;

header("Location: profile?success=1");
exit;
