<?php
session_start();
require_once "../core/db.php";

$token = $_SESSION['payment_token'] ?? null;
if (!$token) exit("Invalid session");

$stmt = $pdo->prepare("
    UPDATE payments
    SET status='confirmed', confirmed_at=NOW()
    WHERE payment_token=? AND status='pending'
");
$stmt->execute([$token]);

if ($stmt->rowCount() !== 1) {
    exit("Payment already processed");
}

header("Location: create.php");
exit;
