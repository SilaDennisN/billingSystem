<?php
session_start();

function login($user) {
    $_SESSION['user'] = [
        'id' => $user['user_id'],
        'full_names' => $user['full_names'],
        'phone_number' => $user['phone_number'],
        'role' => $user['role'],
        'router_id' => $user['router_id']
    ];
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function logout() {
    session_destroy();
}



$user_id = $_SESSION['user']['id'];

$stmt = $pdo->prepare("SELECT router_id FROM user_router_access WHERE user_id = ?");
$stmt->execute([$user_id]);

$routerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($routerIds)) {
    $routerIds = [0]; // prevents SQL error if user has no routers
}

