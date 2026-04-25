<?php
/**
 * auth/verify_email.php
 *
 * Handles the one-click link sent in the verification email.
 * URL format:  https://yourdomain.com/auth/verify_email.php?token=RAW_TOKEN
 */

session_start();
require_once '../core/db.php';

$raw_token = trim($_GET['token'] ?? '');

/* ── No token supplied ── */
if ($raw_token === '') {
    $_SESSION['error'] = "Invalid verification link. Please request a new one.";
    header('Location: login.php');
    exit;
}

$token_hash = hash('sha256', $raw_token);

/* ── Look up the token ── */
$stmt = $pdo->prepare("
    SELECT user_id, full_names, email, email_verified, verify_token_expires
    FROM   users
    WHERE  verify_token = ?
    LIMIT  1
");
$stmt->execute([$token_hash]);
$user = $stmt->fetch();

/* ── Token not found ── */
if (!$user) {
    $_SESSION['error'] = "This verification link is invalid or has already been used.";
    header('Location: login.php');
    exit;
}

/* ── Already verified ── */
if (!empty($user['email_verified'])) {
    $_SESSION['success'] = "Your email is already verified. You can log in.";
    header('Location: login.php');
    exit;
}

/* ── Token expired ── */
if (strtotime($user['verify_token_expires']) < time()) {
    // Store email so resend page can pre-fill it
    $_SESSION['unverified_email'] = $user['email'];

    $_SESSION['error'] = "This verification link has expired (links are valid for 24 hours). "
                       . "<a href='resend_verification.php'>Click here to get a new one</a>.";
    header('Location: login.php');
    exit;
}

/* ── Mark email as verified and clear the token ── */
$pdo->prepare("
    UPDATE users
    SET    email_verified       = 1,
           verify_token         = NULL,
           verify_token_expires = NULL
    WHERE  user_id = ?
")->execute([$user['user_id']]);

$_SESSION['success'] = "✅ Email verified successfully! Welcome, <strong>{$user['full_names']}</strong>. You can now log in.";
header('Location: login.php');
exit;