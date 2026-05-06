<?php
/**
 * preauth_login.php
 * Validates pre-authorized subscriber credentials against the hotspot_users table.
 * Called via AJAX from the preauth modal on index.php.
 * Returns JSON — never a redirect.
 */

session_start();
require_once "../core/db.php";

header('Content-Type: application/json');

/* ── Helpers ─────────────────────────────────── */
function ok() {
    echo json_encode(['ok' => true]);
    exit;
}

function fail(string $title, string $message, string $field = '') {
    echo json_encode([
        'ok'      => false,
        'title'   => $title,
        'message' => $message,
        'field'   => $field,   // 'username' | 'password' | '' — tells JS which input to highlight
    ]);
    exit;
}

/* ── Validate input ──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    fail('Invalid Request', 'This endpoint only accepts POST requests.');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password']       ?? '';

if ($username === '') fail('Missing Username', 'Please enter your username.',              'username');
if ($password === '') fail('Missing Password', 'Please enter your password.',              'password');

/* ── Session must have router context ───────── */
$router_id = $_SESSION['router_id'] ?? null;
if (!$router_id) {
    fail('Session Expired', 'Your hotspot session has expired. Please reconnect to the WiFi network.');
}

/* ── Look up the user ────────────────────────── */
try {
    // 1. Does the username exist at all?
    $stmt = $pdo->prepare("
        SELECT username, password, status, expires_at
        FROM hotspot_users
        WHERE username = ?
          AND router_id = ?
        LIMIT 1
    ");
    $stmt->execute([$username, $router_id]);
    $user = $stmt->fetch();

} catch (Throwable $e) {
    error_log("preauth_login DB error: " . $e->getMessage());
    fail('Server Error', 'A database error occurred. Please try again.');
}

if (!$user) {
    fail(
        'Username Not Found',
        'No account with that username exists on this network. Double-check your username or contact your administrator.',
        'username'
    );
}

// 2. Check password
if ($user['password'] !== $password) {
    fail(
        'Incorrect Password',
        'The password you entered is wrong. Try again or contact your administrator if you\'ve forgotten it.',
        'password'
    );
}

// 3. Check account status
if ($user['status'] === 'expired') {
    fail(
        'Subscription Expired',
        'Your subscription has expired. Please purchase a new package to continue using the network.'
    );
}

if ($user['status'] !== 'active') {
    fail(
        'Account Inactive',
        'Your account is currently inactive. Please contact your network administrator.'
    );
}

// 4. Check expiry date
if (!empty($user['expires_at']) && strtotime($user['expires_at']) < time()) {
    // Mark it expired while we're here
    try {
        $pdo->prepare("UPDATE hotspot_users SET status='expired' WHERE username=? AND router_id=?")
            ->execute([$username, $router_id]);
    } catch (Throwable $e) { /* non-fatal */ }

    $expiry = date('d M Y, H:i', strtotime($user['expires_at']));
    fail(
        'Subscription Expired',
        "Your subscription expired on {$expiry}. Please purchase a new package to continue."
    );
}

/* ── All good — credentials are valid ────────── */
ok();
