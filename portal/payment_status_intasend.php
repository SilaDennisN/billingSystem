<?php
session_start();

require_once "../core/db.php";
require_once "../core/intasend_verify.php";

$token = $_SESSION['payment_token'] ?? null;

if (!$token) {
    exit("INVALID");
}

$stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_token=?");
$stmt->execute([$token]);
$payment = $stmt->fetch();

if (!$payment) {
    exit("INVALID");
}

if ($payment['provider'] !== 'intasend') {
    exit("NOT_INTASEND");
}

$status = verifyAndActivateIntasend($payment);

echo $status;