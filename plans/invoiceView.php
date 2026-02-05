<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) exit;

$id = $_GET['id'] ?? null;
if (!$id) exit("Invalid invoice");

$invoice = $pdo->prepare("
    SELECT i.*, 
           hu.username,
           hp.profile_name,
           r.name AS router_name
    FROM invoices i
    JOIN hotspot_users hu ON hu.user_id = i.user_id
    JOIN hotspot_profiles hp ON hp.id = i.plan_id
    JOIN routers r ON r.router_id = i.router_id
    WHERE i.invoice_id = ?
");
$invoice->execute([$id]);
$inv = $invoice->fetch();

if (!$inv) exit("Invoice not found");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Invoice <?= $inv['invoice_number'] ?></title>
    <style>
        body { font-family: Arial; }
        .invoice-box { max-width: 800px; margin: auto; }
        .total { font-size: 18px; font-weight: bold; }
    </style>
</head>
<body>

<div class="invoice-box">
    <h2>INVOICE</h2>
    <hr>

    <p><strong>Invoice No:</strong> <?= $inv['invoice_number'] ?></p>
    <p><strong>User:</strong> <?= htmlspecialchars($inv['username']) ?></p>
    <p><strong>Router:</strong> <?= $inv['router_name'] ?></p>

    <table width="100%" border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Plan</th>
            <th>Period</th>
            <th>Amount</th>
        </tr>
        <tr>
            <td><?= $inv['profile_name'] ?></td>
            <td>
                <?= $inv['period_start'] ?> →
                <?= $inv['period_end'] ?>
            </td>
            <td>KES <?= number_format($inv['amount'], 2) ?></td>
        </tr>
    </table>

    <p class="total">
        Total: KES <?= number_format($inv['amount'], 2) ?>
    </p>

    <p>Status: <strong><?= strtoupper($inv['status']) ?></strong></p>

    <button onclick="window.print()">Print Invoice</button>
</div>

</body>
</html>
