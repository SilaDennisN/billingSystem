<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

$payments = $pdo->query("
    SELECT 
        p.*,
        hu.username,
        hu.user_type,
        hp.profile_name,
        r.name AS router_name
    FROM payments p
    JOIN hotspot_users hu ON hu.username = p.username
    JOIN hotspot_profiles hp ON hp.id = p.plan_id
    JOIN routers r ON r.router_id = p.router_id
    ORDER BY p.created_at DESC
")->fetchAll();

// Calculate stats
$totalRevenue = 0;
$completedPayments = 0;
$pendingPayments = 0;
$todayRevenue = 0;

foreach ($payments as $p) {
    if ($p['status'] === 'used') {
        $totalRevenue += $p['amount'];
        $completedPayments++;
        
        if (date('Y-m-d', strtotime($p['created_at'])) === date('Y-m-d')) {
            $todayRevenue += $p['amount'];
        }
    } else {
        $pendingPayments++;
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
                <i class="fas fa-money-bill-wave me-2"></i>Payments
            </h1>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success-soft text-success">
                    <i class="fa fa-check-circle me-1"></i><?= $completedPayments ?> Completed
                </span>
                <span class="badge bg-warning-soft text-warning">
                    <i class="fa fa-hourglass-half me-1"></i><?= $pendingPayments ?> Pending
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
            <div class="card card-raised border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="small text-muted">Today's Revenue</div>
                            <div class="h3 mb-0">KES <?= number_format($todayRevenue, 2) ?></div>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-calendar-day fa-2x text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card card-raised border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="small text-muted">Completed</div>
                            <div class="h3 mb-0"><?= $completedPayments ?></div>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-check-circle fa-2x text-info opacity-50"></i>
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
                            <div class="small text-muted">Pending</div>
                            <div class="h3 mb-0"><?= $pendingPayments ?></div>
                        </div>
                        <div class="ms-3">
                            <i class="fas fa-hourglass-half fa-2x text-warning opacity-50"></i>
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
                    <i class="fas fa-list me-2"></i>Payment History
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-light btn-sm active" data-status="all">
                        All Payments
                    </button>
                    <button class="btn btn-light btn-sm" data-status="completed">
                        Completed
                    </button>
                    <button class="btn btn-light btn-sm" data-status="pending">
                        Pending
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-hashtag me-1"></i>Payment ID</th>
                            <th><i class="fas fa-user me-1"></i>User</th>
                            <th><i class="fas fa-tag me-1"></i>Type</th>
                            <th><i class="fas fa-server me-1"></i>Router</th>
                            <th><i class="fas fa-box me-1"></i>Plan</th>
                            <th><i class="fas fa-money-bill me-1"></i>Amount</th>
                            <th><i class="fas fa-credit-card me-1"></i>Method</th>
                            <th><i class="fas fa-check-circle me-1"></i>Status</th>
                            <th><i class="fas fa-clock me-1"></i>Date</th>
                            <th class="text-end"><i class="fas fa-cog me-1"></i>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($payments as $p): ?>
                            <tr data-payment-status="<?= $p['status'] ?>">
                                <td>
                                    <strong class="text-primary">
                                        #PAY-<?= str_pad($p['payment_id'], 6, '0', STR_PAD_LEFT) ?>
                                    </strong>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-2">
                                            <div class="avatar-title bg-<?= $p['user_type']=='pppoe'?'info':'secondary' ?>-soft text-<?= $p['user_type']=='pppoe'?'info':'secondary' ?> rounded-circle">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <strong><?= htmlspecialchars($p['username']) ?></strong>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge bg-<?= $p['user_type']=='pppoe'?'info':'warning' ?>-soft text-<?= $p['user_type']=='pppoe'?'info':'warning' ?>">
                                        <i class="fas fa-<?= $p['user_type']=='pppoe'?'network-wired':'rss' ?> me-1"></i>
                                        <?= strtoupper($p['user_type']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge bg-primary-soft text-primary">
                                        <?= htmlspecialchars($p['router_name']) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($p['profile_name']) ?></td>

                                <td>
                                    <strong class="text-success">
                                        <i class="fas fa-money-bill-wave me-1"></i>
                                        KES <?= number_format($p['amount'], 2) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php
                                    $methodIcons = [
                                        'mpesa' => 'mobile-alt',
                                        'cash' => 'money-bill',
                                        'card' => 'credit-card',
                                        'bank' => 'university'
                                    ];
                                    $icon = $methodIcons[strtolower($p['payment_method'])] ?? 'wallet';
                                    ?>
                                    <span class="badge bg-secondary-soft text-secondary">
                                        <i class="fas fa-<?= $icon ?> me-1"></i>
                                        <?= strtoupper($p['payment_method']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($p['status'] === 'used'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Completed
                                        </span>
                                    <?php elseif ($p['status'] === 'pending'): ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-hourglass-half me-1"></i>Pending
                                        </span>
                                    <?php elseif ($p['status'] === 'failed'): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times-circle me-1"></i>Failed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <?= strtoupper($p['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('M d, Y', strtotime($p['created_at'])) ?>
                                        <br>
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('H:i', strtotime($p['created_at'])) ?>
                                    </small>
                                </td>

                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary view-payment" data-id="<?= $p['payment_id'] ?>" title="View Receipt">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary print-receipt" data-id="<?= $p['payment_id'] ?>" title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <?php if ($p['status'] === 'pending'): ?>
                                            <button class="btn btn-outline-success confirm-payment" data-id="<?= $p['payment_id'] ?>" title="Confirm Payment">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <?php if (empty($payments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No payments recorded yet</p>
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

<!-- DataTables Filtering Script -->
<script>
    // document.addEventListener("DOMContentLoaded", () => {
    //     const filterButtons = document.querySelectorAll("[data-status]");
        
    //     filterButtons.forEach(btn => {
    //         btn.addEventListener("click", () => {
    //             const status = btn.dataset.status;
                
    //             // Update active button
    //             filterButtons.forEach(b => b.classList.remove("active"));
    //             btn.classList.add("active");
                
    //             // Filter using Simple-DataTables
    //             const table = document.getElementById("datatablesSimple");
    //             if (table && table.datatable) {
    //                 if (status === "all") {
    //                     table.datatable.search("");
    //                 } else {
    //                     table.datatable.search(status);
    //                 }
    //             }
    //         });
    //     });

    //     // View payment receipt
    //     document.addEventListener("click", (e) => {
    //         if (e.target.closest(".view-payment")) {
    //             const paymentId = e.target.closest(".view-payment").dataset.id;
    //             // Add your view logic here
    //             alert("View payment #" + paymentId);
    //         }

    //         if (e.target.closest(".print-receipt")) {
    //             const paymentId = e.target.closest(".print-receipt").dataset.id;
    //             // Add your print logic here
    //             window.print();
    //         }

    //         if (e.target.closest(".confirm-payment")) {
    //             const paymentId = e.target.closest(".confirm-payment").dataset.id;
    //             if (confirm("Confirm this payment?")) {
    //                 // Add your confirmation logic here
    //                 window.location.href = `confirm.php?id=${paymentId}`;
    //             }
    //         }
    //     });
    // });
</script>

</body>
</html>