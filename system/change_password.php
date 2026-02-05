<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($new !== $confirm) {
    header("Location: profile?error=Passwords do not match");
    exit;
}

/* Load current hash */
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !password_verify($current, $user['password_hash'])) {
    header("Location: profile?error=Current password incorrect");
    exit;
}

/* Update password */
$newHash = password_hash($new, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("
    UPDATE users SET password_hash=? WHERE user_id=?
");
$stmt->execute([$newHash, $user_id]);

header("Location: profile?success=1");
exit;
