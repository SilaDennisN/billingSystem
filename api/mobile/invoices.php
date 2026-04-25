<?php
require_once '../db.php';
$conn = $pdo;

header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) { echo json_encode([]); exit; }

$sql = "SELECT invoice_id, invoice_number, amount, status, period_start, period_end, created_at, paid_at
        FROM invoices
        WHERE user_id = ?
        ORDER BY period_start DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);