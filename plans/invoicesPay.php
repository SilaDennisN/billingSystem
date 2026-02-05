<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) exit;

$invoice_id = $_POST['invoice_id'];
$amount = $_POST['amount'];
$method = $_POST['payment_method'];

$inv = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id=?");
$inv->execute([$invoice_id]);
$invoice = $inv->fetch();

if (!$invoice) exit("Invoice not found");

/* Record payment */
$stmt = $pdo->prepare("
    INSERT INTO payments
    (user_type, user_id, router_id, plan_id, amount, payment_method, status)
    VALUES ('admin', ?, ?, ?, ?, ?, 'completed')
");
$stmt->execute([
    $invoice['user_id'],
    $invoice['router_id'],
    $invoice['plan_id'],
    $amount,
    $method
]);

/* Mark invoice paid */
$pdo->prepare("
    UPDATE invoices 
    SET status='paid', paid_at=NOW()
    WHERE invoice_id=?
")->execute([$invoice_id]);

header("Location: invoices");
exit;
