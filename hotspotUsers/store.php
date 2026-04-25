<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";
require_once "../core/mailer.php";

if (!is_logged_in()) exit;

$router_id = (int) $_POST['router_id'];
$plan_id   = (int) $_POST['plan_id'];
$username  = trim($_POST['username']);
$password  = trim($_POST['password']);

// ── Fetch plan ───────────────────────────────────────────────────────────────
$planStmt = $pdo->prepare("SELECT * FROM hotspot_profiles WHERE id = ?");
$planStmt->execute([$plan_id]);
$plan = $planStmt->fetch();

if (!$plan) die("Invalid plan selected.");

// ── Fetch router name ────────────────────────────────────────────────────────
$rStmt = $pdo->prepare("SELECT name FROM routers WHERE router_id = ?");
$rStmt->execute([$router_id]);
$routerName = $rStmt->fetchColumn() ?: 'N/A';

// ── Calculate expiry (always end of current month) ───────────────────────────
$start  = new DateTime();
$expire = new DateTime(date('Y-m-t 23:59:59'));

// ── Prorate plan amount for remaining days this month ────────────────────────
$planPrice      = (float) ($plan['price'] ?? 0);
$daysInMonth    = (int) date('t');
$dayOfMonth     = (int) date('j');
$daysRemaining  = $daysInMonth - $dayOfMonth + 1;
$proratedAmount = round(($daysRemaining / $daysInMonth) * $planPrice, 2);

// ── Installation fee ─────────────────────────────────────────────────────────
$installationFee = 0.00;
$feePaid = !empty($_POST['installation_fee_paid']) && $_POST['installation_fee_paid'] == '1';

if ($feePaid && isset($_POST['installation_fee']) && $_POST['installation_fee'] !== '') {
    $installationFee = (float) $_POST['installation_fee'];
}

// First invoice total = prorated subscription + installation fee
$firstInvoiceAmount = $proratedAmount + $installationFee;

// ── Push PPPoE secret to Router ──────────────────────────────────────────────
$client = router_connect($router_id);

$client->query(
    (new RouterOS\Query('/ppp/secret/add'))
        ->equal('name',     $username)
        ->equal('password', $password)
        ->equal('service',  'pppoe')
        ->equal('profile',  $plan['profile_name'])
)->read();

// ── Save PPPoE user to hotspot_users ─────────────────────────────────────────
$stmt = $pdo->prepare("
    INSERT INTO hotspot_users
        (router_id, plan_id, username, password, starts_at, expires_at)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $router_id,
    $plan_id,
    $username,
    $password,
    $start->format('Y-m-d H:i:s'),
    $expire->format('Y-m-d H:i:s'),
]);

// ── Create first invoice (unpaid) ────────────────────────────────────────────
$today         = date('Y-m-d');
$monthEnd      = date('Y-m-t');
$invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr($username, 0, 4)) . rand(100, 999);

$invStmt = $pdo->prepare("
    INSERT INTO invoices
        (user_id, user_type, router_id, plan_id,
         invoice_number, period_start, period_end,
         amount, status)
    VALUES (?, 'pppoe', ?, ?, ?, ?, ?, ?, 'unpaid')
");
$invStmt->execute([
    $_SESSION['user']['id'],
    $router_id,
    $plan_id,
    $invoiceNumber,
    $today,
    $monthEnd,
    $firstInvoiceAmount,
]);

$invoiceId = $pdo->lastInsertId();

// ── Record in payments as pending ────────────────────────────────────────────
$payStmt = $pdo->prepare("
    INSERT INTO payments
        (router_id, plan_id, username, amount, user_id,
         status, payment_method, user_type, invoice_ids)
    VALUES (?, ?, ?, ?, ?, 'pending', 'Cash', 'pppoe', ?)
");
$payStmt->execute([
    $router_id,
    $plan_id,
    $username,
    $firstInvoiceAmount,
    $_SESSION['user']['id'],
    $invoiceId,
]);

// ── Send credentials email (optional) ────────────────────────────────────────
$sendEmail     = !empty($_POST['send_email']) && $_POST['send_email'] == '1';
$customerEmail = trim($_POST['customer_email'] ?? '');

if ($sendEmail && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    send_pppoe_credentials_email(
        email:        $customerEmail,
        username:     $username,
        password:     $password,
        plan_name:    $plan['profile_name'],
        router_name:  $routerName,
        expires_at:   $expire->format('Y-m-d H:i:s'),
        install_fee:  $installationFee > 0 ? $installationFee : null,
        invoice_no:   $invoiceNumber,
        prorated:     $proratedAmount,
        plan_price:   $planPrice,
        days_remaining: $daysRemaining,
        days_in_month:  $daysInMonth
    );
}

header("Location: ../hotspotUsers/index");
exit;