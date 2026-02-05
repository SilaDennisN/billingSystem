<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

/*
 REAL INVOICES
 - Source: invoices table ONLY
 - Hotspot + PPPoE included
*/

$invoices = $pdo->query("
    SELECT 
        i.*,
        hu.username,
        hu.user_type,
        hp.profile_name,
        r.name AS router_name
    FROM invoices i
    JOIN hotspot_users hu ON hu.user_id = i.user_id
    JOIN hotspot_profiles hp ON hp.id = i.plan_id
    JOIN routers r ON r.router_id = i.router_id
    ORDER BY i.created_at DESC
")->fetchAll();

// Calculate stats
$totalInvoices = count($invoices);
$paidInvoices = 0;
$unpaidInvoices = 0;
$overdueInvoices = 0;
$totalRevenue = 0;
$pendingRevenue = 0;

foreach ($invoices as $i) {
    if ($i['status'] === 'paid') {
        $paidInvoices++;
        $totalRevenue += $i['amount'];
    } elseif ($i['status'] === 'overdue') {
        $overdueInvoices++;
        $pendingRevenue += $i['amount'];
    } else {
        $unpaidInvoices++;
        $pendingRevenue += $i['amount'];
    }
}
?>

<?php require_once "../partials/head.php"; ?>
<body class="nav-fixed bg-light">

<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php"; ?>

<div id="layoutDrawer_content">
<main>

<!-- Enhanced Header -->
<header class="bg-dark">
    <div class="container-xl px-1">
        <div class="d-flex justify-content-between align-items-center py-3">
            <h1 class="text-white mb-0 display-6">
                <i class="fas fa-file-invoice-dollar me-2"></i>Invoices
            </h1>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success-soft text-success">
                    <i class="fa fa-check-circle me-1"></i><?= $paidInvoices ?> Paid
                </span>
                <span class="badge bg-danger-soft text-danger">
                    <i class="fa fa-exclamation-circle me-1"></i><?= $overdueInvoices ?> Overdue
                </span>
                <button class="btn btn-sm btn-light" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Export
                </button>
            </div>
        </div>
    </div>
</header>

<div class="container-xl px-1 mt-4">

    <!-- Stats Cards Row -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-raised border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="small text-muted">Total Invoices</div>
                            <div class="h3 mb-0"><?= $totalInvoices ?></div>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-file-invoice fa-2x text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-raised border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="small text-muted">Total Revenue</div>
                            <div class="h3 mb-0">KES <?= number_format($totalRevenue, 2) ?></div>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-coins fa-2x text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-raised border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="small text-muted">Pending Revenue</div>
                            <div class="h3 mb-0">KES <?= number_format($pendingRevenue, 2) ?></div>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-hourglass-half fa-2x text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-raised border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="small text-muted">Overdue</div>
                            <div class="h3 mb-0"><?= $overdueInvoices ?></div>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card card-raised shadow-sm">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-list me-2"></i>Invoice List
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-light btn-sm active" data-status="all">
                        All Invoices
                    </button>
                    <button class="btn btn-light btn-sm" data-status="paid">
                        Paid
                    </button>
                    <button class="btn btn-light btn-sm" data-status="unpaid">
                        Unpaid
                    </button>
                    <button class="btn btn-light btn-sm" data-status="overdue">
                        Overdue
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-hashtag me-1"></i>Invoice #</th>
                            <th><i class="fas fa-user me-1"></i>User</th>
                            <th><i class="fas fa-tag me-1"></i>Type</th>
                            <th><i class="fas fa-server me-1"></i>Router</th>
                            <th><i class="fas fa-box me-1"></i>Plan</th>
                            <th><i class="fas fa-money-bill me-1"></i>Amount</th>
                            <th><i class="fas fa-calendar-alt me-1"></i>Period</th>
                            <th><i class="fas fa-check-circle me-1"></i>Status</th>
                            <th class="text-end"><i class="fas fa-cog me-1"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($invoices as $i): 
                            $isOverdue = $i['status'] === 'overdue';
                            $isPaid = $i['status'] === 'paid';
                        ?>
                            <tr data-invoice-status="<?= $i['status'] ?>">

                                <td>
                                    <strong class="text-primary">
                                        <i class="fas fa-file-invoice me-1"></i>
                                        <?= htmlspecialchars($i['invoice_number']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-2">
                                            <div class="avatar-title bg-<?= $i['user_type']=='pppoe'?'info':'secondary' ?>-soft text-<?= $i['user_type']=='pppoe'?'info':'secondary' ?> rounded-circle">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <strong><?= htmlspecialchars($i['username']) ?></strong>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge bg-<?= $i['user_type']=='pppoe' ? 'info' : 'warning' ?>-soft text-<?= $i['user_type']=='pppoe' ? 'info' : 'warning' ?>">
                                        <i class="fas fa-<?= $i['user_type']=='pppoe'?'network-wired':'rss' ?> me-1"></i>
                                        <?= strtoupper($i['user_type']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge bg-primary-soft text-primary">
                                        <?= htmlspecialchars($i['router_name']) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($i['profile_name']) ?></td>

                                <td>
                                    <strong class="<?= $isPaid ? 'text-success' : 'text-primary' ?>">
                                        <i class="fas fa-money-bill-wave me-1"></i>
                                        KES <?= number_format($i['amount'], 2) ?>
                                    </strong>
                                </td>

                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-day me-1"></i>
                                        <?= date('M d, Y', strtotime($i['period_start'])) ?>
                                        <br>
                                        <i class="fas fa-arrow-right me-1"></i>
                                        <?= date('M d, Y', strtotime($i['period_end'])) ?>
                                    </small>
                                </td>

                                <td>
                                    <?php if ($i['status'] === 'paid'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Paid
                                        </span>
                                    <?php elseif ($i['status'] === 'overdue'): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-circle me-1"></i>Overdue
                                        </span>
                                    <?php elseif ($i['status'] === 'unpaid'): ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-hourglass-half me-1"></i>Unpaid
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <?= strtoupper($i['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="invoiceview?id=<?= $i['invoice_id'] ?>" 
                                           class="btn btn-outline-primary" 
                                           title="View Invoice">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <button class="btn btn-outline-secondary print-invoice" 
                                                data-id="<?= $i['invoice_id'] ?>" 
                                                title="Print Invoice">
                                            <i class="fas fa-print"></i>
                                        </button>

                                        <?php if ($i['status'] !== 'paid'): ?>
                                            <button class="btn btn-outline-success"
                                                data-bs-toggle="modal"
                                                data-bs-target="#payInvoiceModal"
                                                data-id="<?= $i['invoice_id'] ?>"
                                                data-invoice="<?= htmlspecialchars($i['invoice_number']) ?>"
                                                data-amount="<?= $i['amount'] ?>"
                                                title="Pay Invoice">
                                                <i class="fas fa-credit-card"></i>
                                            </button>
                                        <?php endif; ?>

                                        <button class="btn btn-outline-info send-invoice" 
                                                data-id="<?= $i['invoice_id'] ?>" 
                                                title="Send via Email">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <?php if (empty($invoices)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No invoices found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

</main>
<?php require_once "../partials/footer.php"; ?>
</div>
</div>

<?php require_once "../partials/scripts.php"; ?>

<!-- Pay Invoice Modal -->
<div class="modal fade" id="payInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="invoicespay.php">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-credit-card me-2"></i>Pay Invoice
                    </h5>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="invoice_id" id="pay-invoice-id">

                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Invoice: <strong id="pay-invoice-number"></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-money-bill-wave me-1"></i>Amount
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input name="amount" id="pay-amount" class="form-control form-control-lg" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-credit-card me-1"></i>Payment Method
                        </label>
                        <select name="payment_method" class="form-select">
                            <option value="mpesa">M-Pesa</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-sticky-note me-1"></i>Notes (Optional)
                        </label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Add payment notes..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>Confirm Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DataTables Filtering and Actions Script -->
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const filterButtons = document.querySelectorAll("[data-status]");
        
        // Filter functionality
        filterButtons.forEach(btn => {
            btn.addEventListener("click", () => {
                const status = btn.dataset.status;
                
                // Update active button
                filterButtons.forEach(b => b.classList.remove("active"));
                btn.classList.add("active");
                
                // Filter using Simple-DataTables
                const table = document.getElementById("datatablesSimple");
                if (table && table.datatable) {
                    if (status === "all") {
                        table.datatable.search("");
                    } else {
                        table.datatable.search(status);
                    }
                }
            });
        });

        // Pay Invoice Modal
        const payModal = document.getElementById('payInvoiceModal');
        if (payModal) {
            payModal.addEventListener('show.bs.modal', function (e) {
                const btn = e.relatedTarget;
                document.getElementById('pay-invoice-id').value = btn.dataset.id;
                document.getElementById('pay-amount').value = parseFloat(btn.dataset.amount).toFixed(2);
                document.getElementById('pay-invoice-number').textContent = btn.dataset.invoice;
            });
        }

        // Print Invoice
        document.addEventListener("click", (e) => {
            if (e.target.closest(".print-invoice")) {
                const invoiceId = e.target.closest(".print-invoice").dataset.id;
                window.open(`invoiceview?id=${invoiceId}&print=1`, '_blank');
            }

            if (e.target.closest(".send-invoice")) {
                const invoiceId = e.target.closest(".send-invoice").dataset.id;
                if (confirm("Send this invoice via email?")) {
                    // Add your email sending logic here
                    alert("Invoice #" + invoiceId + " will be sent via email");
                }
            }
        });
    });
</script>

</body>
</html>