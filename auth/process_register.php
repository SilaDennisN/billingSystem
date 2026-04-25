<?php
session_start();
require_once '../core/db.php';
require_once '../core/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit;
}

$full_names   = trim($_POST['full_names'] ?? '');
$username     = trim($_POST['username'] ?? '');
$email        = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$password     = $_POST['password'] ?? '';
$confirm      = $_POST['confirm_password'] ?? '';

/* -------------------------
   Basic validation
--------------------------*/

if ($full_names === '' || $username === '' || $password === '') {
    $_SESSION['error'] = "Please fill all required fields.";
    header('Location: register.php');
    exit;
}

if ($email === '') {
    $_SESSION['error'] = "An email address is required to verify your account.";
    header('Location: register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Please enter a valid email address.";
    header('Location: register.php');
    exit;
}

if ($password !== $confirm) {
    $_SESSION['error'] = "Passwords do not match.";
    header('Location: register.php');
    exit;
}

/* -------------------------
   Password strength check
--------------------------*/

if (strlen($password) < 8) {
    $_SESSION['error'] = "Password must be at least 8 characters long.";
    header('Location: register.php');
    exit;
}

if (!preg_match('/[A-Z]/', $password)) {
    $_SESSION['error'] = "Password must contain at least one uppercase letter.";
    header('Location: register.php');
    exit;
}

if (!preg_match('/[0-9]/', $password)) {
    $_SESSION['error'] = "Password must contain at least one number.";
    header('Location: register.php');
    exit;
}

/* -------------------------
   Check username uniqueness
--------------------------*/

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    $_SESSION['error'] = "Username already exists.";
    header('Location: register.php');
    exit;
}

/* -------------------------
   Check email uniqueness
--------------------------*/

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $_SESSION['error'] = "Email already registered.";
    header('Location: register.php');
    exit;
}

/* -------------------------
   Create user (unverified)
--------------------------*/

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Generate a secure random token (64 hex chars) with a 24-hour expiry
$verify_token      = bin2hex(random_bytes(32));
$verify_token_hash = hash('sha256', $verify_token);   // store hash, send raw token
$token_expires_at  = date('Y-m-d H:i:s', strtotime('+24 hours'));

/*
   ── DB MIGRATION REQUIRED ────────────────────────────────────────────
   Run this once on your database before deploying:

   ALTER TABLE users
       ADD COLUMN email_verified     TINYINT(1)   NOT NULL DEFAULT 0        AFTER status,
       ADD COLUMN verify_token       VARCHAR(64)  DEFAULT NULL               AFTER email_verified,
       ADD COLUMN verify_token_expires DATETIME   DEFAULT NULL               AFTER verify_token;
   ─────────────────────────────────────────────────────────────────────
*/

$stmt = $pdo->prepare("
    INSERT INTO users
        (full_names, username, email, phone_number, password_hash,
         role, status, email_verified, verify_token, verify_token_expires)
    VALUES
        (?, ?, ?, ?, ?, 'admin', 'active', 0, ?, ?)
");

$stmt->execute([
    $full_names,
    $username,
    $email,
    $phone_number,
    $password_hash,
    $verify_token_hash,
    $token_expires_at,
]);

/* -------------------------
   Send verification email
--------------------------*/

$sent = send_verification_email($email, $full_names, $verify_token);

if ($sent) {
    $_SESSION['success'] = "Account created! We've sent a verification link to <strong>{$email}</strong>. "
                         . "Please check your inbox (and spam folder) and click the link to activate your account before logging in.";
} else {
    // Account was created but email failed — let the user request a resend
    $_SESSION['warning'] = "Account created, but we couldn't send the verification email to <strong>{$email}</strong>. "
                         . "Please <a href='resend_verification.php'>click here to resend</a> the link.";
}

header('Location: login.php');
exit;