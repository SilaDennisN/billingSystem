<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/live_sessions.php";
require_once __DIR__ . "/auth.php";
$user_id = $_SESSION['user']['id'];

header('Content-Type: application/json');

try {
    $users = get_live_users($pdo, $user_id);
    echo json_encode($users);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

