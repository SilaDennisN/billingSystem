<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    exit("Unauthorized");
}

$user_id = $_SESSION['user']['id']; // ✅ Correct session format

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password']     ?? '';
$confirm = $_POST['confirm_password'] ?? '';

/* Validate new password length */
if (strlen($new) < 8) {
    header("Location: profile?error=New password must be at least 8 characters");
    exit;
}

/* Validate passwords match */
if ($new !== $confirm) {
    header("Location: profile?error=Passwords do not match");
    exit;
}

/* Load current hash */
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !password_verify($current, $user['password_hash'])) {
    header("Location: profile?error=Current password is incorrect");
    exit;
}

/* Prevent reusing the same password */
if (password_verify($new, $user['password_hash'])) {
    header("Location: profile?error=New password must be different from current password");
    exit;
}

/* Update password */
$newHash = password_hash($new, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
$stmt->execute([$newHash, $user_id]);

header("Location: profile?success=1");
exit;