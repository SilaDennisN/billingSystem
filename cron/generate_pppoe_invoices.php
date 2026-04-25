#!/usr/bin/env php
<?php

require_once __DIR__ . "/../core/db.php";

date_default_timezone_set('Africa/Nairobi');

/* ================================
   LOG SETUP
================================ */

$logDir = __DIR__ . '/../core/logs';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$logFile = $logDir . '/pppoe_invoices_' . date('Y-m-d') . '.log';

$log = function ($msg) use ($logFile) {
    $line = "[" . date('Y-m-d H:i:s') . "] $msg\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
};

$log("=== PPPoE Invoice Cron Started ===");

/* ================================
   LOCK (prevent double run)
================================ */

$lockFile = __DIR__ . '/pppoe.lock';

if (file_exists($lockFile)) {
    $log("Another instance is running. Exiting.");
    exit;
}

file_put_contents($lockFile, getmypid());

register_shutdown_function(function () use ($lockFile) {
    if (file_exists($lockFile)) unlink($lockFile);
});

/* ================================
   DATE RANGE
================================ */

$monthStart = new DateTime('first day of this month');
$monthEnd   = new DateTime('last day of this month');

/* ================================
   FETCH USERS
================================ */

$users = $pdo->query("
    SELECT hu.*, hp.price
    FROM hotspot_users hu
    JOIN hotspot_profiles hp ON hp.id = hu.plan_id
    WHERE hu.user_type = 'pppoe'
      AND hu.status = 'active'
")->fetchAll();

$log("Found " . count($users) . " PPPoE users");

$created = 0;
$skipped = 0;

/* ================================
   PROCESS USERS
================================ */

foreach ($users as $u) {

    try {

        // Prevent duplicate invoice
        $check = $pdo->prepare("
            SELECT 1 FROM invoices
            WHERE user_id = ? 
            AND period_start = ?
        ");

        $check->execute([
            $u['user_id'],
            $monthStart->format('Y-m-d')
        ]);

        if ($check->fetch()) {
            $skipped++;
            continue;
        }

        $invoiceNumber = 'INV-' . date('Ym') . '-' . $u['user_id'];

        $stmt = $pdo->prepare("
            INSERT INTO invoices
            (invoice_number, user_id, user_type, router_id, plan_id,
             period_start, period_end, amount)
            VALUES (?, ?, 'pppoe', ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $invoiceNumber,
            $u['user_id'],
            $u['router_id'],
            $u['plan_id'],
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d'),
            $u['price']
        ]);

        $created++;
        $log("Invoice created for user #{$u['user_id']} (KES {$u['price']})");

    } catch (Throwable $e) {
        $log("ERROR user #{$u['user_id']}: " . $e->getMessage());
    }
}

/* ================================
   SUMMARY
================================ */

$log("Done. Created: {$created}, Skipped: {$skipped}");
$log("=== PPPoE Invoice Cron Finished ===\n");