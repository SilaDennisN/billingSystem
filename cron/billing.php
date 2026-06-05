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
 *       Every day: catch-up check for any months that were missed
 *   [4] Auto-suspend users with invoices unpaid after SUSPENSION_GRACE_DAYS
 *
 * Gateway fee rules (mirrors subscription/index.php):
 *   - user_api_keys.provider = 'mpesa'     → KES 0   (own Daraja keys)
 *   - user_api_keys.provider = 'intasend'  → KES 0   (own IntaSend keys)
 *   - user_api_keys.provider = 'pesaflux'  → KES 400 (platform-managed)
 *   - no row in user_api_keys              → KES 400 (platform-managed)
 *   - gateway_enabled = 0                  → KES 0   (no gateway)
 */

date_default_timezone_set('Africa/Nairobi');
define('SUSPENSION_GRACE_DAYS', 7);

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/mailer.php';

$today = new DateTime();
$today->setTime(0, 0, 0);

// ── Logger setup ──────────────────────────────────────────────────────────────
$logDir = __DIR__ . '/../core/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/billing_' . date('Y-m-d') . '.log';

$log = function (string $msg) use ($logFile) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
};

// ── Helper: resolve gateway fee for a subscriber ──────────────────────────────
// Mirrors the logic in subscription/index.php:
//   M-Pesa own keys  → free
//   IntaSend own keys → free
//   PesaFlux / no keys → KES 400
$resolveGatewayFee = function (array $sub) use ($pdo): float {
    if (!$sub['gateway_enabled']) {
        return 0.00;
    }

    $keyStmt = $pdo->prepare("
        SELECT provider FROM user_api_keys
        WHERE user_id = ? AND provider IN ('mpesa', 'pesaflux', 'intasend')
        LIMIT 1
    ");
    $keyStmt->execute([$sub['user_id']]);
    $ownProvider = $keyStmt->fetchColumn(); // 'mpesa' | 'intasend' | 'pesaflux' | false

    // Own Daraja or IntaSend keys → no charge
    if ($ownProvider === 'mpesa' || $ownProvider === 'intasend') {
        return 0.00;
    }

    // Platform-managed (PesaFlux) or gateway_enabled but no key row → KES 400
    return 400.00;
};

// ── Helper: compute & insert one invoice, return invoiceId or false ───────────
//
// Idempotent: duplicate guard on (user_id, period_start) means this is
// safe to call multiple times for the same period — it will simply return
// false on subsequent calls without touching the DB.
$createInvoice = function (array $sub, string $periodStartStr, string $periodEndStr) use ($pdo, $log, $resolveGatewayFee): int|false {

    // ── Duplicate guard ───────────────────────────────────────
    $dupCheck = $pdo->prepare("
        SELECT COUNT(*) FROM subscription_invoices
        WHERE user_id = ? AND period_start = ?
    ");
    $dupCheck->execute([$sub['user_id'], $periodStartStr]);
    if ($dupCheck->fetchColumn() > 0) {
        return false;
    }

    // ── Gateway fee ───────────────────────────────────────────
    $gatewayFee = $resolveGatewayFee($sub);

    // ── Sum payments for this user's routers in the period ────
    $routerStmt = $pdo->prepare("
        SELECT router_id FROM user_router_access WHERE user_id = ?
    ");
    $routerStmt->execute([$sub['user_id']]);
    $routerIds = $routerStmt->fetchAll(PDO::FETCH_COLUMN);

    $billedIncome = 0.0;
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
    $totalAmount = $platformFee + $gatewayFee;

    // Nothing to bill — skip silently
    if ($totalAmount <= 0) {
        $log("  Skipped user #{$sub['user_id']} [{$periodStartStr}] — zero income and no gateway fee.");
        return false;
    }

    // ── Insert invoice ────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO subscription_invoices
            (user_id, period_start, period_end, billed_income, platform_fee,
             gateway_fee, total_amount, status, created_at, updated_at)
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

    $gatewayNote = $gatewayFee > 0
        ? "gateway KES {$gatewayFee} (platform)"
        : ($sub['gateway_enabled'] ? "gateway free (own keys)" : "no gateway");

    $log("  Invoice #{$invoiceId} — user #{$sub['user_id']} | income KES " . number_format($billedIncome, 2)
        . " | platform KES " . number_format($platformFee, 2)
        . " | {$gatewayNote}"
        . " | total KES " . number_format($totalAmount, 2));

    return $invoiceId;
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
        UPDATE subscriptions
        SET status = 'trial_expired', updated_at = NOW()
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
    $sent       = send_trial_expiry_email($sub['email'], $sub['full_names'], $expiryDate);
    $log("  Warning sent: user #{$sub['user_id']} ({$sub['email']}) — email " . ($sent ? 'sent' : 'FAILED'));
}
$log("  Done. " . count($warningSubs) . " warning(s) sent.");


/* ══════════════════════════════════════════════════════════════
   TASK 3 — Monthly invoicing (1st → last day of month)

   Billing cycle: calendar month (1st to last day).
   Every user is billed on the same cycle regardless of when
   they subscribed.

   Two passes run every day:

   Pass A — Normal billing (runs on the 1st only):
     Generates invoices for ALL active/trial subscribers for
     the previous calendar month.

   Pass B — Catch-up (runs every day):
     Walks each subscriber's history from their start month to
     the current month. Any elapsed month missing an invoice
     gets one created immediately.
     Self-heals missed cron days automatically.

   Both passes share $createInvoice() which has a duplicate
   guard, making the whole task fully idempotent.
   ══════════════════════════════════════════════════════════════ */
$log("TASK 3: Monthly invoice generation...");

// Fetch all active/trial subscribers once — used by both passes
$stmt = $pdo->prepare("
    SELECT s.id, s.user_id, s.gateway_enabled,
           s.created_at AS subscribed_at,
           u.email, u.full_names
    FROM subscriptions s
    JOIN users u ON u.user_id = s.user_id
    WHERE s.status IN ('active', 'trial')
");
$stmt->execute();
$subscribers = $stmt->fetchAll();

// ── Pass A: Normal billing on the 1st ────────────────────────
if ((int) $today->format('j') === 1) {
    $log("  Pass A: It's the 1st — generating invoices for last month...");

    $periodStart    = (new DateTime('first day of last month'))->setTime(0, 0, 0);
    $periodEnd      = (new DateTime('last day of last month'))->setTime(23, 59, 59);
    $periodStartStr = $periodStart->format('Y-m-d');
    $periodEndStr   = $periodEnd->format('Y-m-d');

    $log("  Billing period: {$periodStartStr} → {$periodEndStr}");

    $passACount = 0;
    foreach ($subscribers as $sub) {
        $invoiceId = $createInvoice($sub, $periodStartStr, $periodEndStr);
        if ($invoiceId === false) {
            continue;
        }

        $passACount++;

        $invoiceRow = $pdo->prepare("
            SELECT billed_income, platform_fee, gateway_fee, total_amount
            FROM subscription_invoices WHERE id = ?
        ");
        $invoiceRow->execute([$invoiceId]);
        $invoiceData = $invoiceRow->fetch(PDO::FETCH_ASSOC);

        $invoice = array_merge(['id' => $invoiceId, 'period_start' => $periodStartStr, 'period_end' => $periodEndStr], $invoiceData);
        $sent    = send_invoice_email($sub['email'], $sub['full_names'], $invoice);
        $log("  Email to {$sub['email']}: " . ($sent ? 'sent' : 'FAILED'));
    }
    $log("  Pass A done. {$passACount} invoice(s) created.");
} else {
    $log("  Pass A: Not the 1st — skipping.");
}

// ── Pass B: Catch-up for any missed months ────────────────────
$log("  Pass B: Running catch-up check for missed invoices...");

$passBCount = 0;
foreach ($subscribers as $sub) {
    // Start from the 1st of the calendar month the user subscribed
    $cursor = new DateTime($sub['subscribed_at']);
    $cursor->modify('first day of this month')->setTime(0, 0, 0);

    while (true) {
        $missedPeriodEnd = (clone $cursor)->modify('last day of this month')->setTime(23, 59, 59);

        // Only back-fill fully elapsed months (period_end must be before today)
        if ($missedPeriodEnd >= $today) {
            break;
        }

        $missedStartStr = $cursor->format('Y-m-d');
        $missedEndStr   = $missedPeriodEnd->format('Y-m-d');

        $invoiceId = $createInvoice($sub, $missedStartStr, $missedEndStr);

        if ($invoiceId !== false) {
            $passBCount++;

            $invoiceRow = $pdo->prepare("
                SELECT billed_income, platform_fee, gateway_fee, total_amount
                FROM subscription_invoices WHERE id = ?
            ");
            $invoiceRow->execute([$invoiceId]);
            $invoiceData = $invoiceRow->fetch(PDO::FETCH_ASSOC);

            $invoice = array_merge(['id' => $invoiceId, 'period_start' => $missedStartStr, 'period_end' => $missedEndStr], $invoiceData);
            $sent    = send_invoice_email($sub['email'], $sub['full_names'], $invoice);
            $log("  Catch-up email to {$sub['email']}: " . ($sent ? 'sent' : 'FAILED'));
        }

        $cursor->modify('+1 month');
    }
}
$log("  Pass B done. {$passBCount} catch-up invoice(s) created.");
$log("  TASK 3 complete.");


/* ══════════════════════════════════════════════════════════════
   TASK 4 — Auto-suspend users with overdue invoices

   An invoice is considered overdue when:
     - status is still 'pending', AND
     - period_end was more than SUSPENSION_GRACE_DAYS ago

   All pending invoices for the user are marked 'overdue' and
   the subscription is suspended in the same operation.
   ══════════════════════════════════════════════════════════════ */
$log("TASK 4: Checking for overdue invoices (grace = " . SUSPENSION_GRACE_DAYS . " days)...");

$graceCutoff = (new DateTime())->modify('-' . SUSPENSION_GRACE_DAYS . ' days')->format('Y-m-d');

$stmt = $pdo->prepare("
    SELECT i.id, i.user_id, i.total_amount, u.email, u.full_names
    FROM subscription_invoices i
    JOIN users u ON u.user_id = i.user_id
    WHERE i.status   = 'pending'
      AND i.period_end < ?
    GROUP BY i.user_id
    HAVING MAX(i.period_end) < ?
");
$stmt->execute([$graceCutoff, $graceCutoff]);
$overdue = $stmt->fetchAll();

foreach ($overdue as $inv) {
    // Mark all pending invoices for this user as overdue
    $pdo->prepare("
        UPDATE subscription_invoices
        SET status = 'overdue', updated_at = NOW()
        WHERE user_id = ? AND status = 'pending'
    ")->execute([$inv['user_id']]);

    // Suspend the subscription
    $pdo->prepare("
        UPDATE subscriptions
        SET status = 'suspended', updated_at = NOW()
        WHERE user_id = ?
    ")->execute([$inv['user_id']]);

    $invoiceNo = str_pad($inv['id'], 5, '0', STR_PAD_LEFT);
    $totalDue  = 'KES ' . number_format($inv['total_amount'], 2);

    $sent = send_suspension_email($inv['email'], $inv['full_names'], $invoiceNo, $totalDue);
    $log("  Suspended user #{$inv['user_id']} ({$inv['email']}) — email " . ($sent ? 'sent' : 'FAILED'));
}
$log("  Done. " . count($overdue) . " account(s) suspended.");


$log("=== Cron Finished ===\n");