<?php
/**
 * expiry_cron.php
 *
 * Run daily via cron to send push notifications for expiring subscriptions.
 *
 * Add to crontab:
 *   0 8 * * * php /var/www/html/api/mobile/expiry_cron.php >> /var/log/inovatech_cron.log 2>&1
 *
 * Sends:
 *   - 7 days before expiry: "Renewal Reminder"
 *   - 3 days before expiry: "Expiring Soon!"
 *   - Day of expiry:        "Expires Today!"
 *   - Day after expiry:     "Subscription Expired"
 */

require_once '../db.php';
require_once __DIR__ . '/send_push.php';

$conn = $pdo;

echo date('Y-m-d H:i:s') . " — Running expiry cron\n";
$sent = 0;

// Fetch PPPoE users whose subscription is expiring or just expired
$stmt = $conn->prepare(
    "SELECT user_id, username, expires_at, status,
            DATEDIFF(expires_at, NOW()) AS days_left
     FROM hotspot_users
     WHERE user_type = 'pppoe'
       AND DATEDIFF(expires_at, NOW()) IN (-1, 0, 3, 7)"
);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    $days    = (int)$user['days_left'];
    $uid     = (int)$user['user_id'];
    $name    = $user['username'];
    $expDate = date('d M Y', strtotime($user['expires_at']));

    if ($days === 7) {
        sendPushToUser($conn, $uid,
            '📅 Renewal Reminder',
            "Hi $name, your subscription expires in 7 days ($expDate). Renew early to stay connected.",
            ['type' => 'expiry', 'days_left' => '7']
        );
        $sent++;
    } elseif ($days === 3) {
        sendPushToUser($conn, $uid,
            '⚠️ Expiring in 3 Days',
            "Hi $name, your subscription expires on $expDate. Renew now to avoid interruption.",
            ['type' => 'expiry', 'days_left' => '3']
        );
        $sent++;
    } elseif ($days === 0) {
        sendPushToUser($conn, $uid,
            '🚨 Expires Today!',
            "Hi $name, your subscription expires TODAY. Pay now to keep your internet running.",
            ['type' => 'expiry', 'days_left' => '0']
        );
        $sent++;
    } elseif ($days === -1) {
        sendPushToUser($conn, $uid,
            '❌ Subscription Expired',
            "Hi $name, your subscription expired on $expDate. Pay now to restore your connection.",
            ['type' => 'warning', 'days_left' => '-1']
        );
        $sent++;
    }
}

echo "Sent $sent push notification(s)\n";
