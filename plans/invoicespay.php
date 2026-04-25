<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) exit;

// Enable exceptions for PDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$invoice_id = $_POST['invoice_id'];
$amount = $_POST['amount'];
$method = $_POST['payment_method'];
$token = bin2hex(random_bytes(32));

// Fetch invoice
$inv = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id=?");
$inv->execute([$invoice_id]);
$invoice = $inv->fetch();

if (!$invoice) exit("Invoice not found");

// Fetch username from users table using user_id
$userStmt = $pdo->prepare("SELECT username FROM hotspot_users WHERE user_id=?");
$userStmt->execute([$invoice['user_id']]);
$user = $userStmt->fetch();

if (!$user) exit("User not found");

// Record payment
try {
    $stmt = $pdo->prepare("
        INSERT INTO payments
        (user_type, user_id, router_id, plan_id, amount, payment_method, status, username, payment_token)
        VALUES (?, ?, ?, ?, ?, ?, 'used', ?, ?)
    ");
    $stmt->execute([
        'admin',                    // user_type
        $invoice['user_id'],        // user_id
        $invoice['router_id'],      // router_id
        $invoice['plan_id'],        // plan_id
        $amount,                    // amount
        $method,                    // payment_method
        $user['username'],           // username from users table
        $token
    ]);
} catch (PDOException $e) {
    exit("Payment insert failed: " . $e->getMessage());
}

// Mark invoice as paid
$pdo->prepare("
    UPDATE invoices 
    SET status='paid', paid_at=NOW()
    WHERE invoice_id=?
")->execute([$invoice_id]);

header("Location: invoices");
exit;