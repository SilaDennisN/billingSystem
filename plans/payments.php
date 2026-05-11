<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);


$stmt = $pdo->prepare("
    SELECT DISTINCT
        p.*,
        hu.username,
        hu.user_type,
        hp.profile_name,
        r.name AS router_name
    FROM payments p
    LEFT JOIN hotspot_users hu ON hu.username = p.username
    LEFT JOIN hotspot_profiles hp ON hp.id = p.plan_id
    LEFT JOIN routers r ON r.router_id = p.router_id
    JOIN user_router_access ur ON ur.router_id = p.router_id
    WHERE ur.user_id = ?
    ORDER BY p.created_at DESC
");

$stmt->execute([$user_id]);
$payments = $stmt->fetchAll();

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
    } elseif($p['status'] === 'pending') {
        $pendingPayments++;
    }else{

    }
}

// Build JS payment map for modal (no extra XHR needed)
$jsPaymentMap = [];
foreach ($payments as $p) {
    $jsPaymentMap[$p['payment_id']] = [
        'id'       => (int) $p['payment_id'],
        'username' => $p['username'],
        'userType' => strtoupper($p['user_type'] ?? ''),
        'router'   => $p['router_name'],
        'plan'     => $p['profile_name'],
        'amount'   => number_format((float) $p['amount'], 2),
        'method'   => strtoupper($p['payment_method']),
        'status'   => $p['status'],
        'ref'      => $p['mpesa_code'] ?? $p['transaction_ref'] ?? '',
        'date'     => date('M d, Y', strtotime($p['created_at'])),
        'time'     => date('H:i', strtotime($p['created_at'])),
    ];
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
                                <i class="fa fa-money-bill-wave me-2"></i>Payments
                            </h1>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success-soft text-success">
                                    <i class="fa fa-check-circle me-1"></i><?= $completedPayments ?> Completed
                                </span>
                                <span class="badge bg-warning-soft text-warning">
                                    <i class="fa fa-hourglass-half me-1"></i><?= $pendingPayments ?> Pending
                                </span>
                                <button class="btn btn-sm btn-light" onclick="window.print()">
                                    <i class="fa fa-print me-1"></i>
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
                                            <i class="fa fa-coins fa-2x text-success opacity-50"></i>
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
                                            <i class="fa fa-calendar-day fa-2x text-primary opacity-50"></i>
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
                                            <i class="fa fa-check-circle fa-2x text-info opacity-50"></i>
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
                                            <i class="fa fa-hourglass-half fa-2x text-warning opacity-50"></i>
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
                                    <i class="fa fa-list me-2"></i>Payment History
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
                                            <th><i class="fa fa-hashtag me-1"></i>Payment ID</th>
                                            <th><i class="fa fa-user me-1"></i>User</th>
                                            <th><i class="fa fa-tag me-1"></i>Type</th>
                                            <th><i class="fa fa-server me-1"></i>Router</th>
                                            <th><i class="fa fa-box me-1"></i>Plan</th>
                                            <th><i class="fa fa-money-bill me-1"></i>Amount</th>
                                            <th><i class="fa fa-credit-card me-1"></i>Method</th>
                                            <th><i class="fa fa-check-circle me-1"></i>Status</th>
                                            <th><i class="fa fa-clock me-1"></i>Date</th>
                                            <th class="text-end"><i class="fa fa-cog me-1"></i>Actions</th>
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
                                                            <div class="avatar-title bg-<?= $p['user_type'] == 'pppoe' ? 'info' : 'secondary' ?>-soft text-<?= $p['user_type'] == 'pppoe' ? 'info' : 'secondary' ?> rounded-circle">
                                                                <i class="fa fa-user"></i>
                                                            </div>
                                                        </div>
                                                        <strong><?= htmlspecialchars($p['username']) ?></strong>
                                                    </div>
                                                </td>

                                                <td>
                                                    <span class="badge bg-<?= $p['user_type'] == 'pppoe' ? 'info' : 'warning' ?>-soft text-<?= $p['user_type'] == 'pppoe' ? 'info' : 'warning' ?>">
                                                        <i class="fa fa-<?= $p['user_type'] == 'pppoe' ? 'network-wired' : 'rss' ?> me-1"></i>
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
                                                        <i class="fa fa-money-bill-wave me-1"></i>
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
                                                        <i class="fa fa-<?= $icon ?> me-1"></i>
                                                        <?= strtoupper($p['payment_method']) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?php if ($p['status'] === 'used'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fa fa-check-circle me-1"></i>Completed
                                                        </span>
                                                    <?php elseif ($p['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fa fa-hourglass-half me-1"></i>Pending
                                                        </span>
                                                    <?php elseif ($p['status'] === 'failed'): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fa fa-times-circle me-1"></i>Failed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <?= strtoupper($p['status']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <small class="text-muted">
                                                        <i class="fa fa-calendar me-1"></i>
                                                        <?= date('M d, Y', strtotime($p['created_at'])) ?>
                                                        <br>
                                                        <i class="fa fa-clock me-1"></i>
                                                        <?= date('H:i', strtotime($p['created_at'])) ?>
                                                    </small>
                                                </td>

                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary view-payment" data-id="<?= $p['payment_id'] ?>" title="View Receipt">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-secondary print-receipt" data-id="<?= $p['payment_id'] ?>" title="Print Receipt">
                                                            <i class="fa fa-print"></i>
                                                        </button>
                                                        <?php if ($p['status'] === 'pending'): ?>
                                                            <button class="btn btn-outline-success confirm-payment" data-id="<?= $p['payment_id'] ?>" title="Confirm Payment">
                                                                <i class="fa fa-check"></i>
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
                                    <i class="fa fa-money-bill-wave fa-3x text-muted mb-3"></i>
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

    <!-- ── Receipt Modal ──────────────────────────────────────── -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

                <div class="modal-header bg-light border-bottom">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="receiptModalLabel">
                        <i class="fa fa-receipt text-primary"></i> Payment Receipt
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4" id="receiptPrintArea">

                    <!-- Branding -->
                    <div class="d-flex align-items-center gap-3 pb-3 mb-2 border-bottom border-dashed">
                        <div class="rounded-circle bg-primary-soft text-primary d-flex align-items-center justify-content-center"
                             style="width:44px;height:44px;font-size:1.2rem;flex-shrink:0;">
                            <i class="fa fa-wifi"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Your Business Name</div>
                            <div class="small text-muted">Hotspot Billing</div>
                        </div>
                        <div class="ms-auto text-end">
                            <div class="fw-bold text-primary" id="r-id">#PAY-000000</div>
                            <div class="small text-muted" id="r-datetime">—</div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <span id="r-status" class="badge fs-6 px-3 py-2">—</span>
                    </div>

                    <!-- Detail rows -->
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted ps-0" style="width:42%">
                                    <i class="fa fa-user me-2"></i>User
                                </td>
                                <td class="fw-semibold text-end pe-0" id="r-username">—</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">
                                    <i class="fa fa-tag me-2"></i>Account Type
                                </td>
                                <td class="text-end pe-0" id="r-usertype">—</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">
                                    <i class="fa fa-server me-2"></i>Router
                                </td>
                                <td class="fw-semibold text-end pe-0" id="r-router">—</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">
                                    <i class="fa fa-box me-2"></i>Plan
                                </td>
                                <td class="fw-semibold text-end pe-0" id="r-plan">—</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">
                                    <i class="fa fa-credit-card me-2"></i>Method
                                </td>
                                <td class="text-end pe-0" id="r-method">—</td>
                            </tr>
                            <tr id="r-ref-row" class="d-none">
                                <td class="text-muted ps-0">
                                    <i class="fa fa-hashtag me-2"></i>Ref
                                </td>
                                <td class="text-end pe-0 font-monospace small" id="r-ref">—</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Amount -->
                    <div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-3 mt-3">
                        <span class="text-muted">Amount Paid</span>
                        <span class="h4 mb-0 text-success fw-bold" id="r-amount">KES 0.00</span>
                    </div>

                </div>

                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="modalPrintBtn">
                        <i class="fa fa-print me-1"></i>Print
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Done</button>
                </div>

            </div>
        </div>
    </div>
    <!-- ── End Receipt Modal ──────────────────────────────────── -->

    <!-- DataTables Filtering Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {

            // Payment data map — server-rendered, no extra XHR needed
            const PAYMENTS = <?= json_encode($jsPaymentMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

            const receiptModalEl = document.getElementById("receiptModal");
            const bsModal        = new bootstrap.Modal(receiptModalEl);

            // ── Status filter buttons (original logic preserved) ──
            const filterButtons = document.querySelectorAll("[data-status]");

            filterButtons.forEach(btn => {
                btn.addEventListener("click", () => {
                    const status = btn.dataset.status;

                    filterButtons.forEach(b => b.classList.remove("active"));
                    btn.classList.add("active");

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

            // ── Receipt helpers ────────────────────────────────
            const STATUS_CONFIG = {
                used:    { cls: "bg-success",           icon: "check-circle",   label: "Completed" },
                pending: { cls: "bg-warning text-dark", icon: "hourglass-half", label: "Pending"   },
                failed:  { cls: "bg-danger",            icon: "times-circle",   label: "Failed"    },
            };

            const METHOD_ICONS = {
                MPESA: "mobile-alt", CASH: "money-bill",
                CARD:  "credit-card", BANK: "university",
            };

            function populateReceipt(p) {
                document.getElementById("r-id").textContent       = "#PAY-" + String(p.id).padStart(6, "0");
                document.getElementById("r-datetime").textContent = p.date + " · " + p.time;
                document.getElementById("r-username").textContent = p.username || "—";
                document.getElementById("r-router").textContent   = p.router   || "—";
                document.getElementById("r-plan").textContent     = p.plan     || "—";
                document.getElementById("r-amount").textContent   = "KES " + p.amount;

                // Status badge
                const sc = STATUS_CONFIG[p.status] ?? { cls: "bg-secondary", icon: "circle", label: p.status };
                const sBadge = document.getElementById("r-status");
                sBadge.className = "badge fs-6 px-3 py-2 " + sc.cls;
                sBadge.innerHTML = `<i class="fa fa-${sc.icon} me-1"></i>${sc.label}`;

                // User type badge — matches table style exactly
                const utClass = p.userType === "PPPOE" ? "bg-info-soft text-info" : "bg-warning-soft text-warning";
                const utIcon  = p.userType === "PPPOE" ? "network-wired" : "rss";
                document.getElementById("r-usertype").innerHTML =
                    `<span class="badge ${utClass}"><i class="fa fa-${utIcon} me-1"></i>${p.userType || "—"}</span>`;

                // Method badge — matches table style exactly
                const mIcon = METHOD_ICONS[p.method] ?? "wallet";
                document.getElementById("r-method").innerHTML =
                    `<span class="badge bg-secondary-soft text-secondary"><i class="fa fa-${mIcon} me-1"></i>${p.method}</span>`;

                // Transaction ref row (hidden when empty)
                const refRow = document.getElementById("r-ref-row");
                if (p.ref) {
                    document.getElementById("r-ref").textContent = p.ref;
                    refRow.classList.remove("d-none");
                } else {
                    refRow.classList.add("d-none");
                }
            }

            function openReceipt(id) {
                const p = PAYMENTS[id];
                if (!p) return;
                populateReceipt(p);
                receiptModalEl.dataset.currentId = id;
                bsModal.show();
            }

            function printReceipt(id) {
                const p = PAYMENTS[id];
                if (!p) return;

                // Populate the modal area first so receiptPrintArea is up to date
                populateReceipt(p);

                // Small delay to let DOM update before reading innerHTML
                setTimeout(() => {
                    const content = document.getElementById("receiptPrintArea").innerHTML;
                    const win = window.open("", "_blank", "width=480,height=660");
                    if (!win) return;
                    win.document.write(`<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Receipt #PAY-${String(p.id).padStart(6, "0")}</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { padding: 2rem; font-size: 14px; max-width: 420px; margin: auto; }
    .border-dashed { border-style: dashed !important; }
    .bg-primary-soft  { background-color: #cfe2ff !important; }
    .text-primary     { color: #0d6efd !important; }
    .bg-info-soft     { background-color: #cff4fc !important; }
    .text-info        { color: #055160 !important; }
    .bg-warning-soft  { background-color: #fff3cd !important; }
    .text-warning     { color: #664d03 !important; }
    .bg-secondary-soft{ background-color: #e9ecef !important; }
    .text-secondary   { color: #495057 !important; }
    @media print { body { padding: 0; } }
  </style>
</head>
<body onload="window.print(); window.close();">${content}</body>
</html>`);
                    win.document.close();
                }, 80);
            }

            // ── Event delegation (replaces the original three listeners) ──
            document.addEventListener("click", (e) => {

                if (e.target.closest(".view-payment")) {
                    const id = e.target.closest(".view-payment").dataset.id;
                    openReceipt(id);
                }

                if (e.target.closest(".print-receipt")) {
                    const id = e.target.closest(".print-receipt").dataset.id;
                    printReceipt(id);
                }

                if (e.target.closest(".confirm-payment")) {
                    const id = e.target.closest(".confirm-payment").dataset.id;
                    if (confirm("Confirm this payment?")) {
                        window.location.href = `confirm.php?id=${encodeURIComponent(id)}`;
                    }
                }

                if (e.target.closest("#modalPrintBtn")) {
                    const id = receiptModalEl.dataset.currentId;
                    if (id) printReceipt(id);
                }
            });

        });
    </script>

</body>

</html>