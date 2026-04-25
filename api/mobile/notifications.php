<?php
/**
 * notifications.php
 *
 * Returns account-level notifications for a specific user:
 *   - Expiry warnings (7 days and 3 days before)
 *   - Payment confirmations (status = 'used' in last 30 days)
 *   - Overdue invoice alerts
 *
 * No extra table needed — everything is derived from hotspot_users,
 * invoices, and payments tables you already have.
 */

require_once '../db.php';
$conn = $pdo;

header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) { echo json_encode([]); exit; }

$notifications = [];
$id = 1; // synthetic IDs (client deduplicates by type+date anyway)

// ── 1. Subscription expiry warnings ─────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT expires_at, status FROM hotspot_users WHERE user_id = ? LIMIT 1"
);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $daysLeft = (int) ceil((strtotime($user['expires_at']) - time()) / 86400);

    if ($user['status'] === 'expired' || $daysLeft < 0) {
        $notifications[] = [
            'id'         => $id++,
            'type'       => 'warning',
            'title'      => 'Subscription Expired',
            'body'       => 'Your internet subscription has expired. Pay now to restore your connection.',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    } elseif ($daysLeft <= 3) {
        $notifications[] = [
            'id'         => $id++,
            'type'       => 'expiry',
            'title'      => 'Expiring in ' . $daysLeft . ' day' . ($daysLeft == 1 ? '' : 's') . '!',
            'body'       => 'Your subscription expires on ' . date('d M Y', strtotime($user['expires_at'])) . '. Renew now to avoid interruption.',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    } elseif ($daysLeft <= 7) {
        $notifications[] = [
            'id'         => $id++,
            'type'       => 'expiry',
            'title'      => 'Renewal Reminder',
            'body'       => 'Your subscription expires in ' . $daysLeft . ' days (' . date('d M Y', strtotime($user['expires_at'])) . '). Consider renewing early.',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}

// ── 2. Recent successful payments ────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT payment_id, amount, transaction_request_id, confirmed_at
     FROM payments
     WHERE user_id = ?
       AND status = 'used'
       AND confirmed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY confirmed_at DESC
     LIMIT 5"
);
$stmt->execute([$user_id]);
$recentPaid = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($recentPaid as $pay) {
    $code = $pay['transaction_request_id'] ?? 'N/A';
    $notifications[] = [
        'id'         => $id++,
        'type'       => 'payment',
        'title'      => 'Payment Received – KES ' . number_format($pay['amount'], 2),
        'body'       => 'Your payment of KES ' . number_format($pay['amount'], 2) . ' was confirmed. Ref: ' . $code,
        'created_at' => $pay['confirmed_at'],
    ];
}

// ── 3. Overdue invoices ───────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT invoice_number, amount, period_end
     FROM invoices
     WHERE user_id = ?
       AND status IN ('overdue', 'unpaid')
       AND period_end < CURDATE()
     ORDER BY period_end DESC
     LIMIT 3"
);
$stmt->execute([$user_id]);
$overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($overdue as $inv) {
    $notifications[] = [
        'id'         => $id++,
        'type'       => 'warning',
        'title'      => 'Overdue Invoice – ' . $inv['invoice_number'],
        'body'       => 'Invoice of KES ' . number_format($inv['amount'], 2) . ' for period ending ' . $inv['period_end'] . ' is overdue.',
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

// Sort by created_at descending
usort($notifications, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

echo json_encode(array_values($notifications));
