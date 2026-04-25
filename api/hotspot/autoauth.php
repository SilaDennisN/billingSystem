<?php
// =============================================================
//  billing.Inovatech.co.ke/api/hotspot/autoauth.php
//  TEST endpoint — no DB, no login required
//  Receives MAC from hotspot, returns username + password
// =============================================================

// Allow cross-origin requests from the MikroTik hotspot page
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Read incoming JSON ─────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (empty($data['mac'])) {
    http_response_code(400);
    echo json_encode(['error' => 'MAC address is required']);
    exit;
}

$mac = strtolower(trim($data['mac']));
$dst = $data['dst'] ?? '';

// ── Generate credentials from MAC ─────────────────────────────
//
//  username : "ht_" + first 8 chars of md5(mac)
//             e.g. mac = aa:bb:cc:dd:ee:ff  → ht_3f4a1b2c
//
//  password : md5(mac + SECRET_SALT)
//             Change SECRET_SALT to something only you know
//
$SECRET_SALT = 'Inovatech2024!change_this';

// $username = 'ht_' . substr(md5($mac), 0, 8);
// $password = substr(md5($mac . $SECRET_SALT), 0, 12);

$username = 'admin';
$password = 1234;

// ── (OPTIONAL) Log the request for debugging ──────────────────
$log_line = date('Y-m-d H:i:s') . " | MAC: $mac | USER: $username | DST: $dst\n";
file_put_contents(__DIR__ . '/autoauth.log', $log_line, FILE_APPEND | LOCK_EX);

// ── Return credentials ─────────────────────────────────────────
echo json_encode([
    'username' => $username,
    'password' => $password,
    'mac'      => $mac,
    'note'     => 'TEST MODE — no DB lookup'
]);