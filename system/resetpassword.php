<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../config/config.php";

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit("Access denied");
}

$targetUserId = (int)($_GET['id'] ?? 0);

/* Prevent self reset */
if ($targetUserId === (int)$_SESSION['user_id']) {
    exit("You cannot reset your own password");
}

/* Hash default password */
$newHash = password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("
    UPDATE users
    SET password_hash = ?
    WHERE user_id = ?
");
$stmt->execute([$newHash, $targetUserId]);

header("Location: users?reset=success");
exit;
