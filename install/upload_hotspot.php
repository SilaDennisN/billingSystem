<?php
error_reporting(0);
ini_set('display_errors', '0');

ob_start();
require_once "../core/db.php";
require_once "../core/auth.php";
ob_end_clean();

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$router_id = $_POST['router_id'] ?? null;
if (!$router_id) {
    echo json_encode(['success' => false, 'message' => 'Missing router_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();
if (!$router) {
    echo json_encode(['success' => false, 'message' => 'Router not found']);
    exit;
}

$vpn_ip   = $router['vpn_ip'];
$api_user = $router['api_user'];
$api_pass = $router['api_pass'];

/* -------------------------------------------------------
 Build login.html — matches the agreed format.
 RouterOS replaces $(…) server-side before the browser
 sees the page. We forward them to the billing portal
 as GET params so the portal can show plans, take
 payment, create the hotspot user, and log the session.
-------------------------------------------------------- */
$billing_base = "https://billing.inovatech.co.ke/portal";

$login_html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="expires" content="-1" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connecting…</title>
    <style>
        body { margin:0; background:#0a0a14; display:flex; align-items:center; justify-content:center; min-height:100vh; font-family:sans-serif; color:#fff; }
        .msg { text-align:center; opacity:.6; font-size:14px; }
    </style>
</head>
<body>
    <p class="msg">Redirecting…</p>

    <!--
        Served by MikroTik RouterOS.
        RouterOS replaces \$(…) variables server-side before the browser sees this page.
        We forward them to the billing portal via a GET redirect so it can:
          1. Show the user their available packages
          2. Take payment
          3. Create the hotspot user on the router via the API
          4. Redirect back to MikroTik's login URL to authenticate
    -->
    <script>
        var billingBase = '{$billing_base}';

        var mac           = encodeURIComponent('\$(mac)');
        var linkLoginOnly = encodeURIComponent('\$(link-login-only)');
        var linkOrig      = encodeURIComponent('\$(link-orig)');
        var chapId        = encodeURIComponent('\$(chap-id)');
        var chapChallenge = encodeURIComponent('\$(chap-challenge)');
        var ip            = encodeURIComponent('\$(ip)');

        window.location.replace(
            billingBase + '/index.php'
            + '?mac='             + mac
            + '&link-login-only=' + linkLoginOnly
            + '&link-orig='       + linkOrig
            + '&chap-id='         + chapId
            + '&chap-challenge='  + chapChallenge
            + '&ip='              + ip
            + '&router_id={$router['router_id']}'
        );
    </script>
</body>
</html>
HTML;

/* -------------------------------------------------------
 Upload via MikroTik's built-in FTP server (port 21)
-------------------------------------------------------- */
function uploadViaFTP(string $host, string $user, string $pass, string $remotePath, string $content): array {
    $conn = @ftp_connect($host, 21, 10);
    if (!$conn) {
        return ['success' => false, 'message' => "FTP: Cannot connect to {$host}:21 — is the router reachable over VPN?"];
    }

    if (!@ftp_login($conn, $user, $pass)) {
        ftp_close($conn);
        return ['success' => false, 'message' => "FTP: Login failed. Check api_user / api_pass for this router."];
    }

    ftp_pasv($conn, true);

    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, $content);
    rewind($tmp);

    @ftp_delete($conn, $remotePath); // remove old file if exists

    $ok = ftp_fput($conn, $remotePath, $tmp, FTP_BINARY);
    fclose($tmp);
    ftp_close($conn);

    if (!$ok) {
        return ['success' => false, 'message' => "FTP: Upload failed. Make sure the hotspot folder exists on the router."];
    }

    return ['success' => true, 'message' => "login.html uploaded to {$remotePath}"];
}

$result = uploadViaFTP($vpn_ip, $api_user, $api_pass, 'hotspot/login.html', $login_html);

echo json_encode($result);