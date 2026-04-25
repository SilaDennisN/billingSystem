<?php
require_once '../db.php';
$conn = $pdo;

header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) { echo json_encode([]); exit; }

$sql = "SELECT payment_id, amount, status, phone,
               transaction_request_id, payment_method,
               created_at, confirmed_at
        FROM payments
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);