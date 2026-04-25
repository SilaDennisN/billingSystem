<?php
/**
 * announcements.php
 *
 * Returns announcements newer than ?after_id=N
 * Admins post announcements via the web dashboard (separate admin endpoint).
 *
 * Table (run once):
 * CREATE TABLE `announcements` (
 *   `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `title`      VARCHAR(200)  NOT NULL,
 *   `body`       TEXT          NOT NULL,
 *   `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   `expires_at` DATETIME      DEFAULT NULL
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

require_once '../db.php';
$conn = $pdo;

header('Content-Type: application/json');

$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

$stmt = $conn->prepare(
    "SELECT id, title, body, created_at
     FROM announcements
     WHERE id > ?
       AND (expires_at IS NULL OR expires_at > NOW())
     ORDER BY id DESC
     LIMIT 20"
);
$stmt->execute([$after_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);
