<?php
// admin/revenue/platform-fees.php
require_once "../../core/db.php";
require_once "../../core/auth.php";
require_once "../../core/app.php";

/* ══════════════════════════════════════
   FILTERS
══════════════════════════════════════ */
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$month        = (int)($_GET['month'] ?? 0); // 0 = all months

$where  = ['1=1'];
$params = [];

if ($filterStatus) {
    $where[]  = 'si.status = ?';
    $params[] = $filterStatus;
}
if ($search) {
    $where[]  = '(u.full_names LIKE ? OR u.phone_number LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($month) {
    $where[]  = 'MONTH(si.created_at) = ?';
    $params[] = $month;
}
$whereSQL = implode(' AND ', $where);

/* ══════════════════════════════════════
   STATS
══════════════════════════════════════ */
$statsThisMonth = $pdo->query("
    SELECT
        IFNULL(SUM(CASE WHEN status='paid'    THEN platform_fee END), 0) AS collected,
        IFNULL(SUM(CASE WHEN status='pending' THEN platform_fee END), 0) AS pending,
        IFNULL(SUM(CASE WHEN status='overdue' THEN platform_fee END), 0) AS overdue,
        COUNT(*)                                                           AS total_invoices
    FROM subscription_invoices
    WHERE MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at)  = YEAR(CURDATE())
")->fetch();

/* ══════════════════════════════════════
   INVOICES
══════════════════════════════════════ */
$stmt = $pdo->prepare("
    SELECT
        si.*,
        u.full_names,
        u.phone_number,
        u.email
    FROM subscription_invoices si
    JOIN users u ON u.user_id = si.user_id
    WHERE $whereSQL
    ORDER BY si.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$invoices = $stmt->fetchAll();
?>
<?php require_once "../../partials/head.php" ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php" ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Header -->
                <header style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%);border-bottom:1px solid rgba(99,102,241,.2);">
                    <div class="container-xl px-4">
                        <div class="d-flex justify-content-between align-items-center py-4 flex-wrap gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="material-icons" style="color:#818cf8;font-size:18px;">account_balance</i>
                                    <span style="font-size:.7rem;color:#64748b;letter-spacing:.1em;text-transform:uppercase;font-weight:600;">Revenue › Platform Fees</span>
                                </div>
                                <h1 class="text-white mb-0 fw-bold" style="font-size:1.6rem;">Platform Fees</h1>
                                <p style="color:#64748b;" class="mb-0 mt-1 small">5% fee collected on all user billed income</p>
                            </div>
                            <?php if ($statsThisMonth['overdue'] > 0): ?>
                            <div>
                                <span class="badge px-3 py-2" style="background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.25);border-radius:8px;">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">warning</i>
                                    KES <?= number_format($statsThisMonth['overdue'], 0) ?> overdue
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 mt-4">

                    <!-- KPI Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted fw-500">Collected This Month</span>
                                        <i class="material-icons" style="color:#4ade80;font-size:20px;">check_circle</i>
                                    </div>
                                    <div class="h4 fw-bold mb-0 text-success">KES <?= number_format($statsThisMonth['collected'], 0) ?></div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#4ade80,#22c55e);"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted fw-500">Pending</span>
                                        <i class="material-icons" style="color:#fbbf24;font-size:20px;">pending</i>
                                    </div>
                                    <div class="h4 fw-bold mb-0 text-warning">KES <?= number_format($statsThisMonth['pending'], 0) ?></div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#fbbf24,#f59e0b);"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted fw-500">Overdue</span>
                                        <i class="material-icons" style="color:#f87171;font-size:20px;">error</i>
                                    </div>
                                    <div class="h4 fw-bold mb-0 text-danger">KES <?= number_format($statsThisMonth['overdue'], 0) ?></div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#f87171,#ef4444);"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted fw-500">Invoices This Month</span>
                                        <i class="material-icons" style="color:#818cf8;font-size:20px;">receipt_long</i>
                                    </div>
                                    <div class="h4 fw-bold mb-0"><?= number_format($statsThisMonth['total_invoices']) ?></div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#6366f1,#8b5cf6);"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                        <div class="card-body p-3">
                            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                                <div>
                                    <label class="form-label small fw-600 mb-1">Search</label>
                                    <input type="text" name="q" class="form-control form-control-sm" style="width:200px;"
                                        placeholder="Name or phone…" value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div>
                                    <label class="form-label small fw-600 mb-1">Status</label>
                                    <select name="status" class="form-select form-select-sm" style="width:140px;">
                                        <option value="">All</option>
                                        <option value="paid"    <?= $filterStatus==='paid'    ? 'selected':'' ?>>Paid</option>
                                        <option value="pending" <?= $filterStatus==='pending' ? 'selected':'' ?>>Pending</option>
                                        <option value="overdue" <?= $filterStatus==='overdue' ? 'selected':'' ?>>Overdue</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small fw-600 mb-1">Month</label>
                                    <select name="month" class="form-select form-select-sm" style="width:140px;">
                                        <option value="">All Months</option>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0,0,0,$m,1)) ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-sm px-3"
                                        style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#818cf8;border-radius:8px;">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">search</i> Filter
                                    </button>
                                    <a href="platform-fees" class="btn btn-sm px-3"
                                        style="background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;border-radius:8px;">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">clear</i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Invoices Table -->
                    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">
                        <div class="card-header d-flex justify-content-between align-items-center px-4 py-3"
                            style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                            <div class="d-flex align-items-center gap-2">
                                <i class="material-icons" style="color:#818cf8;font-size:20px;">receipt_long</i>
                                <span class="fw-bold text-white">Platform Fee Invoices</span>
                                <span class="badge ms-1" style="background:rgba(99,102,241,.2);color:#a5b4fc;font-size:.7rem;"><?= count($invoices) ?> records</span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                    <tr>
                                        <th class="px-4 py-3 small text-muted fw-600">#</th>
                                        <th class="py-3 small text-muted fw-600">User</th>
                                        <th class="py-3 small text-muted fw-600">Period</th>
                                        <th class="py-3 small text-muted fw-600">Billed Income</th>
                                        <th class="py-3 small text-muted fw-600">Platform Fee</th>
                                        <th class="py-3 small text-muted fw-600">Gateway Fee</th>
                                        <th class="py-3 small text-muted fw-600">Total</th>
                                        <th class="py-3 small text-muted fw-600">Status</th>
                                        <th class="py-3 small text-muted fw-600">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td class="px-4">
                                        <span class="font-monospace text-muted small">#<?= str_pad($inv['id'],5,'0',STR_PAD_LEFT) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-600 small"><?= htmlspecialchars($inv['full_names']) ?></div>
                                        <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($inv['phone_number']) ?></div>
                                    </td>
                                    <td>
                                        <span class="small text-muted">
                                            <?= date('M d', strtotime($inv['period_start'])) ?> →
                                            <?= date('M d, Y', strtotime($inv['period_end'])) ?>
                                        </span>
                                    </td>
                                    <td class="fw-600">KES <?= number_format($inv['billed_income'], 0) ?></td>
                                    <td>
                                        <span class="fw-bold" style="color:#f59e0b;">KES <?= number_format($inv['platform_fee'], 0) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($inv['gateway_fee'] > 0): ?>
                                            <span class="text-muted small">KES <?= number_format($inv['gateway_fee'], 0) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold">KES <?= number_format($inv['total_amount'], 0) ?></td>
                                    <td>
                                        <?php
                                        $sc = ['paid'=>'success','pending'=>'warning','overdue'=>'danger'];
                                        $si = $inv['status'];
                                        ?>
                                        <span class="badge" style="
                                            background:rgba(<?= $si==='paid'?'74,222,128':($si==='pending'?'251,191,36':'239,68,68') ?>,.12);
                                            color:<?= $si==='paid'?'#22c55e':($si==='pending'?'#fbbf24':'#ef4444') ?>;
                                            border-radius:20px;padding:4px 10px;font-size:.7rem;">
                                            <?= $si==='paid' ? '✓ Paid' : ucfirst($si) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?= date('M d, Y', strtotime($inv['created_at'])) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="material-icons d-block mb-2" style="font-size:40px;opacity:.25;">receipt_long</i>
                                        No invoices found.
                                    </td>
                                </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>
            <?php require_once "../../partials/footer.php" ?>
        </div>
    </div>
    <?php require_once "../../partials/scripts.php" ?>
</body>
</html>