<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);

$user_id = $_SESSION['user']['id'];

/* ── Load routers the user can access ──────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT r.router_id, r.name
    FROM routers r
    JOIN user_router_access ur ON ur.router_id = r.router_id
    WHERE ur.user_id = ?
    AND r.status = 'active'
    ORDER BY r.name
");
$stmt->execute([$user_id]);
$routers = $stmt->fetchAll();

/* ── Load expenses for accessible routers (+ router-less ones) ─────────── */
$stmt = $pdo->prepare("
    SELECT e.*, r.name AS router_name, u.username
    FROM expenses e
    LEFT JOIN routers r ON r.router_id = e.router_id
    JOIN users u ON u.user_id = e.created_by
    WHERE (
        e.router_id IN (
            SELECT ur.router_id FROM user_router_access ur WHERE ur.user_id = ?
        )
        OR e.router_id IS NULL
    )
    ORDER BY e.expense_date DESC
");
$stmt->execute([$user_id]);
$expenses = $stmt->fetchAll();

/* ── Summary stats ──────────────────────────────────────────────────────── */
$totalAll   = 0;
$totalMonth = 0;
$thisMonth  = date('Y-m');
$catTotals  = [];

foreach ($expenses as $e) {
    $totalAll += $e['amount'];
    if (substr($e['expense_date'], 0, 7) === $thisMonth) {
        $totalMonth += $e['amount'];
    }
    $catTotals[$e['category']] = ($catTotals[$e['category']] ?? 0) + $e['amount'];
}
arsort($catTotals);
$topCat = array_key_first($catTotals);

/* Category meta (icon + colour) */
$catMeta = [
    'equipment'    => ['icon' => 'fa-tools',               'color' => 'primary'],
    'installation' => ['icon' => 'fa-screwdriver',         'color' => 'info'],
    'service'      => ['icon' => 'fa-concierge-bell',      'color' => 'success'],
    'maintenance'  => ['icon' => 'fa-wrench',              'color' => 'warning'],
    'loss'         => ['icon' => 'fa-exclamation-triangle','color' => 'danger'],
    'power'        => ['icon' => 'fa-bolt',                'color' => 'warning'],
    'rent'         => ['icon' => 'fa-building',            'color' => 'secondary'],
    'other'        => ['icon' => 'fa-tag',                 'color' => 'secondary'],
];

function catIcon(string $cat, array $map): string {
    return $map[$cat]['icon']  ?? 'fa-tag';
}
function catColor(string $cat, array $map): string {
    return $map[$cat]['color'] ?? 'secondary';
}
function catLabel(string $cat): string {
    return ucfirst($cat === 'loss' ? 'Loss / Damage' : $cat);
}
?>
<?php require_once "../partials/head.php"; ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Header -->
                <header class="bg-primary">
                    <div class="container-xl px-1">
                        <div class="d-flex align-items-center justify-content-between py-3">
                            <div>
                                <h1 class="text-white mb-0 display-6">
                                    <i class="fa fa-receipt me-2"></i>Expenses
                                </h1>
                                <p class="text-white-50 mb-0 small">Track operational costs across your network</p>
                            </div>
                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                <i class="fa fa-plus me-1"></i>Add Expense
                            </button>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-4">

                    <!-- Flash -->
                    <?php if (($_GET['success'] ?? '') === '1'): ?>
                        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4">
                            <i class="fa fa-check-circle"></i>
                            <div>Expense recorded successfully.</div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Stats row -->
                    <div class="row g-3 mb-4">

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-raised h-100 border-start border-danger border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Expenses</div>
                                            <div class="h4 mb-0 fw-bold text-danger">
                                                KES <?= number_format($totalAll, 2) ?>
                                            </div>
                                        </div>
                                        <i class="fa fa-coins fa-2x text-danger opacity-25 ms-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-raised h-100 border-start border-warning border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">This Month</div>
                                            <div class="h4 mb-0 fw-bold text-warning">
                                                KES <?= number_format($totalMonth, 2) ?>
                                            </div>
                                        </div>
                                        <i class="fa fa-calendar-alt fa-2x text-warning opacity-25 ms-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-raised h-100 border-start border-primary border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Records</div>
                                            <div class="h4 mb-0 fw-bold"><?= count($expenses) ?></div>
                                        </div>
                                        <i class="fa fa-list fa-2x text-primary opacity-25 ms-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-raised h-100 border-start border-info border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Top Category</div>
                                            <div class="h5 mb-0 fw-bold">
                                                <?= $topCat ? catLabel($topCat) : '—' ?>
                                            </div>
                                            <?php if ($topCat): ?>
                                                <div class="small text-muted">KES <?= number_format($catTotals[$topCat], 2) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <i class="fa <?= $topCat ? catIcon($topCat, $catMeta) : 'fa-tag' ?> fa-2x text-info opacity-25 ms-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Table card -->
                    <div class="card card-raised shadow-sm">
                        <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2 py-3">
                            <div class="fw-semibold">
                                <i class="fa fa-table me-1 text-muted"></i>Expense Records
                            </div>
                            <!-- Category filter pills — only show categories that exist -->
                            <div class="d-flex gap-1 flex-wrap">
                                <button class="btn btn-sm btn-outline-secondary active" data-cat-filter="all">All</button>
                                <?php foreach ($catMeta as $cat => $meta):
                                    if (!isset($catTotals[$cat])) continue; ?>
                                    <button class="btn btn-sm btn-outline-<?= $meta['color'] ?>" data-cat-filter="<?= $cat ?>">
                                        <i class="fa <?= $meta['icon'] ?> me-1"></i><?= catLabel($cat) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fa fa-calendar me-1 text-muted"></i>Date</th>
                                            <th><i class="fa fa-tag me-1 text-muted"></i>Category</th>
                                            <th><i class="fa fa-align-left me-1 text-muted"></i>Title</th>
                                            <th><i class="fa fa-server me-1 text-muted"></i>Router</th>
                                            <th><i class="fa fa-coins me-1 text-muted"></i>Amount</th>
                                            <th><i class="fa fa-user me-1 text-muted"></i>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody id="expenseTableBody">

                                        <?php if (empty($expenses)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <i class="fa fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                                                    No expenses recorded yet
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                        <?php foreach ($expenses as $e):
                                            $color       = catColor($e['category'], $catMeta);
                                            $icon        = catIcon($e['category'],  $catMeta);
                                            $isThisMonth = substr($e['expense_date'], 0, 7) === $thisMonth;
                                        ?>
                                            <tr data-category="<?= $e['category'] ?>">

                                                <td>
                                                    <div class="fw-semibold"><?= date('d M Y', strtotime($e['expense_date'])) ?></div>
                                                    <?php if ($isThisMonth): ?>
                                                        <span class="badge bg-success-soft text-success" style="font-size:.65rem;">This month</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <span class="badge bg-<?= $color ?>-soft text-<?= $color ?> d-inline-flex align-items-center gap-1">
                                                        <i class="fa <?= $icon ?>" style="font-size:.7rem;"></i>
                                                        <?= catLabel($e['category']) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($e['title']) ?></div>
                                                    <?php if (!empty($e['description'])): ?>
                                                        <small class="text-muted"><?= htmlspecialchars(mb_strimwidth($e['description'], 0, 60, '…')) ?></small>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ($e['router_name']): ?>
                                                        <span class="badge bg-primary-soft text-primary">
                                                            <i class="fa fa-server me-1" style="font-size:.7rem;"></i>
                                                            <?= htmlspecialchars($e['router_name']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <span class="fw-bold text-danger">
                                                        KES <?= number_format($e['amount'], 2) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="bg-secondary-soft text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:.7rem;min-width:28px;">
                                                            <i class="fa fa-user"></i>
                                                        </div>
                                                        <span class="small"><?= htmlspecialchars($e['username']) ?></span>
                                                    </div>
                                                </td>

                                            </tr>
                                        <?php endforeach; ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </main>

            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>

    <!-- ADD EXPENSE MODAL -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="post" action="store.php" class="modal-content border-0 shadow-lg">

                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title"><i class="fa fa-plus-circle me-2"></i>Add Expense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="fa fa-tag me-1 text-muted"></i>Category
                            </label>
                            <select name="category" class="form-select" required>
                                <option value="">Select category…</option>
                                <option value="equipment">🔧 Equipment</option>
                                <option value="installation">🪛 Installation</option>
                                <option value="service">🛎 Service</option>
                                <option value="maintenance">🔩 Maintenance</option>
                                <option value="loss">⚠️ Loss / Damage</option>
                                <option value="power">⚡ Power</option>
                                <option value="rent">🏢 Rent</option>
                                <option value="other">🏷 Other</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="fa fa-coins me-1 text-muted"></i>Amount (KES)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">KES</span>
                                <input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="fa fa-align-left me-1 text-muted"></i>Title
                            </label>
                            <input name="title" class="form-control" placeholder="e.g. New fibre cable roll" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="fa fa-align-justify me-1 text-muted"></i>Description
                                <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Additional details…"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="fa fa-server me-1 text-muted"></i>Router
                                <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <select name="router_id" class="form-select">
                                <option value="">— Not router-specific —</option>
                                <?php foreach ($routers as $r): ?>
                                    <option value="<?= $r['router_id'] ?>">
                                        <?= htmlspecialchars($r['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="fa fa-calendar me-1 text-muted"></i>Expense Date
                            </label>
                            <input type="date" name="expense_date" class="form-control"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa fa-save me-1"></i>Save Expense
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Category filter JS -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const filterBtns = document.querySelectorAll("[data-cat-filter]");
        const rows       = document.querySelectorAll("#expenseTableBody tr[data-category]");

        filterBtns.forEach(btn => {
            btn.addEventListener("click", function () {
                filterBtns.forEach(b => b.classList.remove("active"));
                this.classList.add("active");

                const filter = this.dataset.catFilter;
                rows.forEach(row => {
                    row.style.display = (filter === "all" || row.dataset.category === filter) ? "" : "none";
                });
            });
        });
    });
    </script>

</body>
</html>