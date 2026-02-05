<?php
require_once __DIR__ . "/../core/db.php";

/*
 Run on 1st day of month:
 0 0 1 * * php /path/cron/generate_pppoe_invoices.php
*/

$monthStart = new DateTime('first day of this month');
$monthEnd   = new DateTime('last day of this month');

$users = $pdo->query("
    SELECT hu.*, hp.price
    FROM hotspot_users hu
    JOIN hotspot_profiles hp ON hp.id = hu.plan_id
    WHERE hu.user_type = 'pppoe'
      AND hu.status = 'active'
")->fetchAll();

foreach ($users as $u) {

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

    if ($check->fetch()) continue;

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
}

echo "PPPoE invoices generated\n";
