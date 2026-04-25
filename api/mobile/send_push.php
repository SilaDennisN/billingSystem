<?php
/**
 * send_push.php
 *
 * Sends FCM push notifications via the FCM HTTP v1 API.
 *
 * SETUP:
 *   1. Go to Firebase Console → Project Settings → Service Accounts
 *   2. Click "Generate new private key" → save as firebase_service_account.json
 *      and place it in a PRIVATE folder OUTSIDE your web root, e.g.:
 *      /var/www/private/firebase_service_account.json
 *   3. Set FIREBASE_SERVICE_ACCOUNT_PATH below
 *   4. Set FIREBASE_PROJECT_ID to your Firebase project ID
 *
 * USAGE (from your PHP code):
 *   // Notify a single user:
 *   sendPushToUser($conn, $user_id, 'Payment Received', 'KES 1500 confirmed!', ['type' => 'payment']);
 *
 *   // Broadcast to all users:
 *   sendPushBroadcast($conn, 'Maintenance', 'Scheduled downtime at midnight', []);
 *
 * CALL AUTOMATICALLY FROM:
 *   - pay.php when payment is confirmed (poll_payment action)
 *   - A cron job for expiry reminders (run daily)
 *   - Your admin dashboard when posting an announcement
 */

define('FIREBASE_SERVICE_ACCOUNT_PATH', '/var/www/private/firebase_service_account.json');
define('FIREBASE_PROJECT_ID', 'inventory-7fab5'); // ← change this

// ── Get an OAuth2 access token from the service account ─────────────────────
function getFirebaseAccessToken(): string {
    $serviceAccount = json_decode(file_get_contents(FIREBASE_SERVICE_ACCOUNT_PATH), true);

    $now    = time();
    $expire = $now + 3600;

    $header  = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'iss'   => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $expire,
    ]));

    $signingInput = "$header.$payload";
    openssl_sign($signingInput, $signature, $serviceAccount['private_key'], 'SHA256');
    $jwt = "$signingInput." . base64_encode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response['access_token'] ?? '';
}

// ── Send one FCM message to a device token ───────────────────────────────────
function sendFcmMessage(string $token, string $title, string $body, array $data = []): bool {
    $accessToken = getFirebaseAccessToken();
    if (!$accessToken) return false;

    $payload = json_encode([
        'message' => [
            'token'        => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => array_map('strval', $data),  // FCM data must be strings
            'android'      => [
                'priority'     => 'high',
                'notification' => [
                    'channel_id' => 'inovatech_main',
                    'color'      => '#0D47A1',
                    'icon'       => 'ic_notification',
                ],
            ],
            'apns' => [
                'payload' => ['aps' => ['sound' => 'default', 'badge' => 1]],
            ],
        ],
    ]);

    $url = 'https://fcm.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/messages:send';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("FCM error ($httpCode): $response");
        return false;
    }
    return true;
}

// ── Helper: push to all tokens for a specific user ───────────────────────────
function sendPushToUser(PDO $conn, int $userId, string $title, string $body, array $data = []): void {
    $stmt = $conn->prepare("SELECT token FROM device_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $token) {
        sendFcmMessage($token, $title, $body, $data);
    }
}

// ── Helper: broadcast to ALL registered device tokens ────────────────────────
function sendPushBroadcast(PDO $conn, string $title, string $body, array $data = []): void {
    $tokens = $conn->query("SELECT token FROM device_tokens")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tokens as $token) {
        sendFcmMessage($token, $title, $body, $data);
    }
}
