<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/live_sessions.php";

header('Content-Type: application/json');

try {
    $users = get_live_users($pdo);
    echo json_encode($users);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
