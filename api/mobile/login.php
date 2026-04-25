<?php
require_once '../db.php';
$conn = $pdo;

$username = $_POST['username'];
$password = $_POST['password'];

$sql = "SELECT user_id, username, expires_at, status 
        FROM hotspot_users
        WHERE username = ? 
        AND password = ?
        AND user_type = 'pppoe'";

$stmt = $conn->prepare($sql);
$stmt->execute([$username, $password]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode([
        "status" => "success",
        "data" => $user
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid login"
    ]);
}