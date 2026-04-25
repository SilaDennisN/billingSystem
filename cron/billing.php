#!/usr/bin/env php
<?php
/**
 * cron/billing_cron.php
 *
 * Handles ALL subscription lifecycle tasks. Run this via cron:
 *
 *   ┌─ Minute   (0)
 *   │  ┌─ Hour    (1 = 1am EAT)
 *   │  │  ┌─ Day of month (* = every day)
 *   │  │  │  ┌─ Month (* = every month)
 *   │  │  │  │  ┌─ Day of week (* = every day)
 *   0  1  *  *  *  php /var/www/html/cron/billing_cron.php >> /var/log/inovatech_cron.log 2>&1
 *
 * What it does each day at 1am:
 *   [1] Mark trials as trial_expired when trial_ends_at has passed
 *   [2] Send a 3-day warning email to users whose trial ends in 3 days
 *   [3] On the 1st of the month: generate invoices for the previous month
 *   [4] Auto-suspend users with invoices unpaid after SUSPENSION_GRACE_DAYS
 */

date_default_timezone_set('Africa/Nairobi');
define('SUSPENSION_GRACE_DAYS', 7);  // days after invoice before suspension

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/mailer.php';

$today    = new DateTime();
$logDir = __DIR__ . '/../core/logs';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/billing_' . date('Y-m-d') . '.log';

$log = function (string $msg) use ($logFile) {

    $time = date('Y-m-d H:i:s');
    $line = "[{$time}] {$msg}\n";

    // Print (for cron redirect)
    echo $line;

    // Save to file
    file_put_contents($logFile, $line, FILE_APPEND);
};

$log("=== Inovatech Billing Cron Started ===");

/* ══════════════════════════════════════════════════════════════
   TASK 1 — Mark expired trials as trial_expired
   ══════════════════════════════════════════════════════════════ */
$log("TASK 1: Checking for expired trials...");

$stmt = $pdo->prepare("
    SELECT s.id, s.user_id, u.email, u.full_names
    FROM subscriptions s
    JOIN users u ON u.user_id = s.user_id
    WHERE s.status = 'trial'
      AND s.trial_ends_at < NOW()
");
$stmt->execute();
$expiredTrials = $stmt->fetchAll();

foreach ($expiredTrials as $sub) {
    $pdo->prepare("
        UPDATE subscriptions SET status = 'trial_expired', updated_at = NOW()
        WHERE id = ?
    ")->execute([$sub['id']]);

    $sent = send_trial_expired_email($sub['email'], $sub['full_names']);
    $log("  Trial expired: user #{$sub['user_id']} ({$sub['email']}) — email " . ($sent ? 'sent' : 'FAILED'));
}
$log("  Done. " . count($expiredTrials) . " trial(s) expired.");


/* ══════════════════════════════════════════════════════════════
   TASK 2 — Send 3-day trial expiry warning
   ══════════════════════════════════════════════════════════════ */
$log("TASK 2: Sending 3-day trial expiry warnings...");

$stmt = $pdo->prepare("
    SELECT s.id, s.user_id, s.trial_ends_at, u.email, u.full_names
    FROM subscriptions s
    JOIN users u ON u.user_id = s.user_id
    WHERE s.status = 'trial'
      AND DATE(s.trial_ends_at) = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
");
$stmt->execute();
$warningSubs = $stmt->fetchAll();

foreach ($warningSubs as $sub) {
    $expiryDate = date('M d, Y', strtotime($sub['trial_ends_at']));
    $sent = send_trial_expiry_email($sub['email'], $sub['full_names'], $expiryDate);
    $log("  Warning sent: user #{$sub['user_id']} ({$sub['email']}) — email " . ($sent ? 'sent' : 'FAILED'));
}
$log("  Done. " . count($warningSubs) . " warning(s) sent.");


/* ══════════════════════════════════════════════════════════════
   TASK 3 — Generate anniversary-based invoices (runs daily)
   
   Each user is billed on the same day-of-month they subscribed.
   e.g. subscribed March 12 → billed every 12th:
        period = March 12 → April 11 (day before next anniversary)
   
   Handles month-end edge cases: if anniversary is the 31st and
   the current month only has 28 days, it fires on the last day.
   ══════════════════════════════════════════════════════════════ */
$log("TASK 3: Checking for anniversary billing due today...");

// Fetch all active/trial subscribers with their subscription start date
$stmt = $pdo->prepare("
    SELECT s.id, s.user_id, s.gateway_enabled, s.created_at AS subscribed_at,
           u.email, u.full_names
    FROM subscriptions s
    JOIN users u ON u.user_id = s.user_id
    WHERE s.status IN ('active', 'trial')
");
$stmt->execute();
$subscribers = $stmt->fetchAll();

$todayDay      = (int) $today->format('j');   // day of month, no leading zero e.g. 12
$daysThisMonth = (int) $today->format('t');   // total days in current month e.g. 28/29/30/31

foreach ($subscribers as $sub) {
    $subscribedAt  = new DateTime($sub['subscribed_at']);
    $anniversaryDay = (int) $subscribedAt->format('j'); // e.g. 12

    // Handle month-end edge case:
    // If anniversary is 31 but this month only has 30 days, fire on the 30th.
    $effectiveDay = min($anniversaryDay, $daysThisMonth);

    // Only bill users whose anniversary falls today
    if ($todayDay !== $effectiveDay) {
        continue;
    }

    // ── Calculate the billing period ──────────────────────────
    // period_end   = yesterday (day before today's anniversary)
    // period_start = one month before period_end + 1 day
    //   e.g. today = April 12
    //        period_end   = April 11
    //        period_start = March 12
    $periodEnd   = (clone $today)->modify('-1 day');
    $periodStart = (clone $periodEnd)->modify('-1 month')->modify('+1 day');

    $periodStartStr = $periodStart->format('Y-m-d');
    $periodEndStr   = $periodEnd->format('Y-m-d');

    $log("  User #{$sub['user_id']}: anniversary day {$anniversaryDay}, period {$periodStartStr} → {$periodEndStr}");

    // ── Duplicate guard ───────────────────────────────────────
    $dupCheck = $pdo->prepare("
        SELECT COUNT(*) FROM subscription_invoices
        WHERE user_id = ? AND period_start = ?
    ");
    $dupCheck->execute([$sub['user_id'], $periodStartStr]);
    if ($dupCheck->fetchColumn() > 0) {
        $log("  Skipped user #{$sub['user_id']} — invoice already exists for this period.");
        continue;
    }

    // ── Sum payments for this user's routers in the period ────
    $routerStmt = $pdo->prepare("
        SELECT router_id FROM user_router_access WHERE user_id = ?
    ");
    $routerStmt->execute([$sub['user_id']]);
    $routerIds = $routerStmt->fetchAll(PDO::FETCH_COLUMN);

    $billedIncome = 0;
    if (!empty($routerIds)) {
        $placeholders = implode(',', array_fill(0, count($routerIds), '?'));
        $params       = array_merge($routerIds, [$periodStartStr, $periodEndStr]);

        $incomeStmt = $pdo->prepare("
            SELECT IFNULL(SUM(amount), 0)
            FROM payments
            WHERE status    = 'used'
              AND router_id IN ({$placeholders})
              AND DATE(created_at) BETWEEN ? AND ?
        ");
        $incomeStmt->execute($params);
        $billedIncome = (float) $incomeStmt->fetchColumn();
    }

    $platformFee = round($billedIncome * 0.05, 2);
    $gatewayFee  = $sub['gateway_enabled'] ? 400.00 : 0.00;
    $totalAmount = $platformFee + $gatewayFee;

    // Skip if nothing to bill
    if ($totalAmount <= 0) {
        $log("  Skipped user #{$sub['user_id']} — nothing to bill (zero income, no gateway).");
        continue;
    }

    // ── Insert invoice ────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO subscription_invoices
            (user_id, period_start, period_end, billed_income, platform_fee, gateway_fee, total_amount, status, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ")->execute([
        $sub['user_id'],
        $periodStartStr,
        $periodEndStr,
        $billedIncome,
        $platformFee,
        $gatewayFee,
        $totalAmount,
    ]);

    $invoiceId = (int) $pdo->lastInsertId();
    $log("  Invoice #{$invoiceId} created for user #{$sub['user_id']}: KES " . number_format($totalAmount, 2));

    // ── Send invoice email ────────────────────────────────────
    $invoice = [
        'id'            => $invoiceId,
        'period_start'  => $periodStartStr,
        'period_end'    => $periodEndStr,
        'billed_income' => $billedIncome,
        'platform_fee'  => $platformFee,
        'gateway_fee'   => $gatewayFee,
        'total_amount'  => $totalAmount,
    ];
    $sent = send_invoice_email($sub['email'], $sub['full_names'], $invoice);
    $log("  Email to {$sub['email']}: " . ($sent ? 'sent' : 'FAILED'));
}

$log("  Done. Anniversary invoice check complete.");


/* ══════════════════════════════════════════════════════════════
   TASK 4 — Auto-suspend users with overdue invoices
   ══════════════════════════════════════════════════════════════ */
$log("TASK 4: Checking for overdue invoices (grace = " . SUSPENSION_GRACE_DAYS . " days)...");

$graceCutoff = (new DateTime())->modify('-' . SUSPENSION_GRACE_DAYS . ' days')->format('Y-m-d');

$stmt = $pdo->prepare("
    SELECT i.id, i.user_id, i.total_amount, u.email, u.full_names
    FROM subscription_invoices i
    JOIN users u ON u.user_id = i.user_id
    WHERE i.status    = 'pending'
      AND i.period_end < ?
    GROUP BY i.user_id
    HAVING MAX(i.period_end) < ?
");
$stmt->execute([$graceCutoff, $graceCutoff]);
$overdue = $stmt->fetchAll();

foreach ($overdue as $inv) {
    // Mark invoice as overdue
    $pdo->prepare("
        UPDATE subscription_invoices SET status = 'overdue', updated_at = NOW()
        WHERE user_id = ? AND status = 'pending'
    ")->execute([$inv['user_id']]);

    // Suspend the subscription
    $pdo->prepare("
        UPDATE subscriptions SET status = 'suspended', updated_at = NOW()
        WHERE user_id = ?
    ")->execute([$inv['user_id']]);

    $invoiceNo = str_pad($inv['id'], 5, '0', STR_PAD_LEFT);
    $totalDue  = 'KES ' . number_format($inv['total_amount'], 2);

    $sent = send_suspension_email($inv['email'], $inv['full_names'], $invoiceNo, $totalDue);
    $log("  Suspended user #{$inv['user_id']} ({$inv['email']}) — email " . ($sent ? 'sent' : 'FAILED'));
}
$log("  Done. " . count($overdue) . " account(s) suspended.");


$log("=== Cron Finished ===\n");