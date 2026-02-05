<?php
session_start();

function login($user) {
    $_SESSION['user'] = [
        'id' => $user['user_id'],
        'username' => $user['username'],
        'role' => $user['role']
    ];
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function logout() {
    session_destroy();
}
