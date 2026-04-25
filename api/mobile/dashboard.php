<?php
require_once '../db.php';
$conn = $pdo;

$user_id = $_GET['user_id'];

$sql = "SELECT u.username, u.expires_at, u.status,
               p.profile_name, p.price
        FROM hotspot_users u
        JOIN hotspot_profiles p ON u.plan_id = p.id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($data);