<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);


/* Load expenses */
$expenses = $pdo->prepare("
    SELECT e.*, r.name AS router_name, u.username
    FROM expenses e
    LEFT JOIN routers r ON r.router_id = e.router_id
    JOIN users u ON u.user_id = e.created_by
    WHERE e.router_id = ?
    ORDER BY e.expense_date DESC
");

$expenses->execute([$router_id]);
$expenses = $expenses->fetchAll();

/* Load routers */
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
?>

<?php require_once "../partials/head.php"; ?>

<body class="nav-fixed bg-light">

    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <header class="bg-primary">
                    <div class="container-xl px-1">
                        <div class="d-flex align-items-center justify-content-between py-4">
                            <div>
                                <h1 class="text-white mb-1 display-6">Expenses</h1>
                                <p class="text-white-50 mb-0">Manage, add or view Expenses</p>
                            </div>
                            <div>
                                <i class="material-icons text-white-50" style="font-size: 3rem;"></i>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-n4">

                    <div class="card shadow-sm mb-4">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Expense Records</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                + Add Expense
                            </button>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="datatablesSimple" class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>Title</th>
                                            <th>Router</th>
                                            <th>Amount</th>
                                            <th>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <?php if (!$expenses): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    No expenses recorded
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                        <?php foreach ($expenses as $e): ?>
                                            <tr>
                                                <td><?= date('d M Y', strtotime($e['expense_date'])) ?></td>

                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= ucfirst($e['category']) ?>
                                                    </span>
                                                </td>

                                                <td><?= htmlspecialchars($e['title']) ?></td>

                                                <td><?= $e['router_name'] ?? '—' ?></td>

                                                <td class="fw-bold text-danger">
                                                    KES <?= number_format($e['amount'], 2) ?>
                                                </td>

                                                <td><?= htmlspecialchars($e['username']) ?></td>
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
        <div class="modal-dialog modal-lg">
            <form method="post" action="store.php" class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Add Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select</option>
                            <option value="equipment">Equipment</option>
                            <option value="installation">Installation</option>
                            <option value="service">Service</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="loss">Loss / Damage</option>
                            <option value="power">Power</option>
                            <option value="rent">Rent</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Amount (KES)</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Title</label>
                        <input name="title" class="form-control" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Router (optional)</label>
                        <select name="router_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($routers as $r): ?>
                                <option value="<?= $r['router_id'] ?>">
                                    <?= htmlspecialchars($r['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Expense Date</label>
                        <input type="date" name="expense_date" class="form-control"
                            value="<?= date('Y-m-d') ?>" required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save Expense</button>
                </div>

            </form>
        </div>
    </div>

</body>

</html>