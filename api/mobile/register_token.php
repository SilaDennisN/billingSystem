<?php
/**
 * register_token.php
 *
 * Stores FCM device tokens so the server can send push notifications.
 *
 * Table (run once):
 * CREATE TABLE `device_tokens` (
 *   `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `user_id`    INT              NOT NULL,
 *   `token`      VARCHAR(255)     NOT NULL,
 *   `platform`   ENUM('android','ios') NOT NULL DEFAULT 'android',
 *   `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   `updated_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *   UNIQUE KEY `uq_token` (`token`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

require_once '../db.php';
$conn = $pdo;

header('Content-Type: application/json');

$user_id  = (int)($_POST['user_id'] ?? 0);
$token    = trim($_POST['token']    ?? '');
$platform = trim($_POST['platform'] ?? 'android');
$action   = trim($_POST['action']   ?? 'register');

if (!$user_id || !$token) {
    echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
    exit;
}

if ($action === 'unregister') {
    $conn->prepare("DELETE FROM device_tokens WHERE token = ?")->execute([$token]);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Upsert token (one token can only belong to one user at a time)
$conn->prepare(
    "INSERT INTO device_tokens (user_id, token, platform)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), platform = VALUES(platform), updated_at = NOW()"
)->execute([$user_id, $token, $platform]);

echo json_encode(['status' => 'ok']);
