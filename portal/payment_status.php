<?php
session_start();
require_once "../core/db.php";

$token = $_SESSION['payment_token'] ?? null;
if (!$token) exit;

$stmt = $pdo->prepare("
    SELECT status FROM payments
    WHERE payment_token = ?
");
$stmt->execute([$token]);

$status = $stmt->fetchColumn();

if ($status === 'used') {
    echo "ACTIVE";
} elseif ($status === 'failed') {
    echo "FAILED";
} else {
    echo "PENDING";
}