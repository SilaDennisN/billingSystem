<?php
date_default_timezone_set('Africa/Nairobi');
session_start();
require_once "../core/db.php";

/*
Browser-side ONLY
No router calls
No payment logic
*/

$token = $_SESSION['payment_token'] ?? null;
if (!$token) {
    http_response_code(403);
    exit("Session expired");
}


/* Fetch activated payment */
$stmt = $pdo->prepare("
    SELECT * FROM payments
    WHERE payment_token=? AND status='used'
");
$stmt->execute([$token]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(403);
    exit("Payment not activated yet");
}


/* Prevent reuse */
$pdo->prepare("
    UPDATE payments SET status='used'
    WHERE payment_id=?
")->execute([$payment['payment_id']]);


/* Auto-login data */
$username = $payment['username'];
$password = '123456';

$link_login_only = $_SESSION['link-login-only'] ?? null;
if (!$link_login_only) {
    exit("Login link missing");
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Connecting…</title>
</head>

<body onload="document.login.submit()">

<form name="login" method="post" action="<?= htmlspecialchars($link_login_only) ?>">
    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
    <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
    <input type="hidden" name="dst" value="https://www.google.com">
</form>

<h3 style="text-align:center">Connecting to internet…</h3>

</body>
</html>
