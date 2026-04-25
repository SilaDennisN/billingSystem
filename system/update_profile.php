<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    exit("Unauthorized");
}

$user_id = $_SESSION['user']['id']; // ✅ Correct session format

$username     = trim($_POST['username']     ?? '');
$full_names   = trim($_POST['full_names']   ?? '');
$email        = trim($_POST['email']        ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');

if ($username === '') {
    header("Location: profile?error=Username required");
    exit;
}

/* Prevent duplicate usernames */
$stmt = $pdo->prepare("
    SELECT user_id FROM users WHERE username = ? AND user_id != ?
");
$stmt->execute([$username, $user_id]);
if ($stmt->fetch()) {
    header("Location: profile?error=Username already taken");
    exit;
}

/* Update all profile fields */
$stmt = $pdo->prepare("
    UPDATE users
    SET username = ?, full_names = ?, email = ?, phone_number = ?
    WHERE user_id = ?
");
$stmt->execute([$username, $full_names ?: null, $email ?: null, $phone_number ?: null, $user_id]);

/* Keep session in sync — update only the fields stored in session */
$_SESSION['user']['full_names']   = $full_names   ?: null;
$_SESSION['user']['phone_number'] = $phone_number ?: null;

header("Location: profile?success=1");
exit;