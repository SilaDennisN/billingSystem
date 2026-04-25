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
<<?php require_once "../partials/head.php"; ?>
<body class="nav-fixed bg-light">

<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php"; ?>

<div id="layoutDrawer_content">
<main>

<!-- Invoice Header -->
<header class="bg-primary py-3 mb-4">
    <div class="container-xl px-4">
        <div class="d-flex justify-content-between align-items-center text-white">
            <h1 class="display-6 mb-0">
                <i class="fa fa-file-invoice-dollar me-2"></i>Invoice
            </h1>
            <div class="text-end">
                <p class="mb-1">Invoice #: <strong><?= $inv['invoice_number'] ?></strong></p>
                <p class="mb-0">Date: <strong><?= date("d M Y", strtotime($inv['created_at'])) ?></strong></p>
            </div>
        </div>
    </div>
</header>

<div class="container-xl px-4">

<div class="invoice-box p-4 bg-white border shadow-sm">
    <!-- Sender & Receiver -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h5>Bill To:</h5>
            <p>
                <strong><?= htmlspecialchars($inv['username']) ?></strong><br>
                Router: <?= $inv['router_name'] ?><br>
                Plan: <?= $inv['profile_name'] ?>
            </p>
        </div>
        <div class="col-md-6 text-end">
            <h5>From:</h5>
            <p>
                <strong>InovaTech Billing System</strong><br>
                Main Street, Malili Town<br>
                Address line 2<br>
                Phone: 0740 770 212<br>
                Email: info@inovatech.co.ke
            </p>
        </div>
    </div>

    <!-- Invoice Table -->
    <table class="table table-bordered mb-4">
        <thead class="table-light">
            <tr>
                <th>Description</th>
                <th>Period</th>
                <th class="text-end">Amount (KES)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= $inv['profile_name'] ?> Plan</td>
                <td><?= date("d M Y", strtotime($inv['period_start'])) ?> → <?= date("d M Y", strtotime($inv['period_end'])) ?></td>
                <td class="text-end"><?= number_format($inv['amount'], 2) ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" class="text-end">Total</th>
                <th class="text-end"><?= number_format($inv['amount'], 2) ?></th>
            </tr>
        </tfoot>
    </table>

    <!-- Status -->
    <div class="mb-4">
        <p>Status: 
            <?php if($inv['status'] === 'paid'): ?>
                <span class="badge bg-success"><?= strtoupper($inv['status']) ?></span>
            <?php elseif($inv['status'] === 'unpaid'): ?>
                <span class="badge bg-warning text-dark"><?= strtoupper($inv['status']) ?></span>
            <?php else: ?>
                <span class="badge bg-danger"><?= strtoupper($inv['status']) ?></span>
            <?php endif; ?>
        </p>
    </div>

    <!-- Footer / Notes -->
    <div class="mb-4">
        <p><strong>Notes:</strong></p>
        <p>Please make payment within 7 days of invoice date. Late payments may incur a penalty.</p>
    </div>

    <div class="text-end">
        <a href="invoice_pdf.php?id=<?= $inv['invoice_id'] ?>" 
   target="_blank" 
   class="btn btn-primary">
   <i class="fa fa-file-pdf"></i> Download PDF
</a>
    </div>
</div>

</div>

</main>
<?php require_once "../partials/footer.php"; ?>
</div>
</div>

<?php require_once "../partials/scripts.php"; ?>
</body>
</html>