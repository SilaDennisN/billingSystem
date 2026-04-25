<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';
check_subscription_gate($pdo, $user_id);

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

/* ── DATE FILTER ──────────────────────────────────────────────────── */
$period   = $_GET['period'] ?? 'month';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

switch ($period) {
    case 'today':
        $from = date('Y-m-d 00:00:00');
        $to   = date('Y-m-d 23:59:59');
        $label = 'Today — ' . date('d M Y');
        break;
    case 'week':
        $from = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $to   = date('Y-m-d 23:59:59');
        $label = 'This Week';
        break;
    case 'year':
        $from = date('Y-01-01 00:00:00');
        $to   = date('Y-12-31 23:59:59');
        $label = 'This Year — ' . date('Y');
        break;
    case 'custom':
        $from  = $dateFrom ? $dateFrom . ' 00:00:00' : date('Y-m-01 00:00:00');
        $to    = $dateTo   ? $dateTo   . ' 23:59:59' : date('Y-m-d 23:59:59');
        $label = date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));
        break;
    default: // month
        $from  = date('Y-m-01 00:00:00');
        $to    = date('Y-m-d 23:59:59');
        $label = 'This Month — ' . date('F Y');
        $period = 'month';
}



$routerPlaceholders = implode(',', array_fill(0, count($routerIds), '?'));

/* ── REVENUE ──────────────────────────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(p.amount), 0) AS total,
        COALESCE(SUM(CASE WHEN p.user_type='hotspot' THEN p.amount ELSE 0 END),0) AS hotspot,
        COALESCE(SUM(CASE WHEN p.user_type='pppoe' THEN p.amount ELSE 0 END),0) AS pppoe,
        COUNT(*) AS tx_count
    FROM payments p
    WHERE p.status IN ('used','confirmed')
    AND p.created_at BETWEEN ? AND ?
    AND p.router_id IN ($routerPlaceholders)
");

$params = array_merge([$from, $to], $routerIds);
$stmt->execute($params);
$revenue = $stmt->fetch();


/* ── PREVIOUS PERIOD REVENUE (for % change) ───────────────────────── */
$diffSec  = strtotime($to) - strtotime($from);
$prevFrom = date('Y-m-d H:i:s', strtotime($from) - $diffSec - 1);
$prevTo   = date('Y-m-d H:i:s', strtotime($from) - 1);
$stmtPrev = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM payments
    WHERE status IN ('used','confirmed')
    AND created_at BETWEEN ? AND ?
    AND router_id IN ($routerPlaceholders)
");

$params = array_merge([$prevFrom, $prevTo], $routerIds);
$stmtPrev->execute($params);
$prevRevenue = $stmtPrev->fetchColumn();
$revenueChange = $prevRevenue > 0
    ? round((($revenue['total'] - $prevRevenue) / $prevRevenue) * 100, 1)
    : null;

/* ── EXPENSES ─────────────────────────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0) AS total,
        COUNT(*) AS count
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    AND router_id IN ($routerPlaceholders)
");

$params = array_merge(
    [date('Y-m-d', strtotime($from)), date('Y-m-d', strtotime($to))],
    $routerIds
);

$stmt->execute($params);
$expenses = $stmt->fetch();

/* ── EXPENSES BY CATEGORY ─────────────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT category, COALESCE(SUM(amount),0) AS total
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    AND router_id IN ($routerPlaceholders)
    GROUP BY category
    ORDER BY total DESC
");

$params = array_merge(
    [date('Y-m-d', strtotime($from)), date('Y-m-d', strtotime($to))],
    $routerIds
);

$stmt->execute($params);
$expensesByCategory = $stmt->fetchAll();

/* ── USERS ────────────────────────────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(user_type='hotspot') AS hotspot,
        SUM(user_type='pppoe') AS pppoe,
        SUM(status='active') AS active,
        SUM(status='expired') AS expired,
        SUM(status='disabled') AS disabled
    FROM hotspot_users
    WHERE router_id IN ($routerPlaceholders)
");

$stmt->execute($routerIds);
$users = $stmt->fetch();

/* ── NEW USERS IN PERIOD ──────────────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM hotspot_users
    WHERE created_at BETWEEN ? AND ?
    AND router_id IN ($routerPlaceholders)
");

$params = array_merge([$from, $to], $routerIds);

$stmt->execute($params);
$newUsers = $stmt->fetchColumn();

/* ── TOP PLANS ────────────────────────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT
        hp.profile_name,
        hp.price,
        hp.plan_type,
        COUNT(hu.user_id) AS user_count,
        COALESCE(SUM(p.amount),0) AS revenue
    FROM hotspot_profiles hp
    LEFT JOIN hotspot_users hu
           ON hu.plan_id = hp.id
          AND hu.router_id IN ($routerPlaceholders)

    LEFT JOIN payments p
           ON p.username = hu.username
          AND p.router_id = hu.router_id
          AND p.status IN ('used','confirmed')
          AND p.created_at BETWEEN ? AND ?

    WHERE hp.router_id IN ($routerPlaceholders)

    GROUP BY hp.id
    ORDER BY revenue DESC
    LIMIT 5
");

$params = array_merge(
    $routerIds,
    [$from, $to],
    $routerIds
);

$stmt->execute($params);
$topPlans = $stmt->fetchAll();

/* ── DAILY REVENUE TREND (last 30 days or period) ────────────────── */
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS day,
           COALESCE(SUM(amount),0) AS total
    FROM payments
    WHERE status IN ('used','confirmed')
    AND created_at BETWEEN ? AND ?
    AND router_id IN ($routerPlaceholders)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");

$params = array_merge([$from, $to], $routerIds);
$stmt->execute($params);
$dailyRevenue = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/* ── ROUTERS ──────────────────────────────────────────────────────── */
$routers = $pdo->query("
    SELECT COUNT(*) AS total, SUM(online_status = 'online') AS online FROM routers
")->fetch();

/* ── RECENT PAYMENTS ──────────────────────────────────────────────── */
$stmt = $pdo->prepare("
    SELECT p.*, hp.profile_name
    FROM payments p
    LEFT JOIN hotspot_profiles hp ON hp.id = p.plan_id
    WHERE p.status IN ('used','confirmed')
    AND p.created_at BETWEEN ? AND ?
    AND p.router_id IN ($routerPlaceholders)
    ORDER BY p.created_at DESC
    LIMIT 10
");

$params = array_merge([$from, $to], $routerIds);
$stmt->execute($params);
$recentPayments = $stmt->fetchAll();

$profit = $revenue['total'] - $expenses['total'];

/* ── CHART DATA ───────────────────────────────────────────────────── */
$chartLabels  = json_encode(array_keys($dailyRevenue));
$chartData    = json_encode(array_values($dailyRevenue));
$planLabels   = json_encode(array_column($topPlans, 'profile_name'));
$planRevenue  = json_encode(array_column($topPlans, 'revenue'));
$catLabels    = json_encode(array_column($expensesByCategory, 'category'));
$catTotals    = json_encode(array_column($expensesByCategory, 'total'));
?>
<?php require_once "../partials/head.php"; ?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<body class="nav-fixed bg-light">
<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php"; ?>

<div id="layoutDrawer_content">
<main>

<!-- ── PAGE HEADER ──────────────────────────────────────────────── -->
<header class="bg-primary">
    <div class="container-xl px-1">
        <div class="d-flex align-items-center justify-content-between py-4">
            <div>
                <h1 class="text-white mb-1 display-6">Reports & Analytics</h1>
                <p class="text-white-50 mb-0">
                    <i class="material-icons align-middle me-1" style="font-size:1rem;">event</i>
                    <?= htmlspecialchars($label) ?>
                </p>
            </div>
            <a href="download.php?period=<?= urlencode($period) ?>&date_from=<?= urlencode(date('Y-m-d', strtotime($from))) ?>&date_to=<?= urlencode(date('Y-m-d', strtotime($to))) ?>"
               class="btn btn-light btn-sm">
                <i class="material-icons align-middle me-1" style="font-size:1rem;">download</i>
                Export PDF
            </a>
        </div>
    </div>
</header>

<div class="container-xl px-1 mt-n3">

    <!-- ── PERIOD FILTER ────────────────────────────────────────── -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2 px-1">
            <form method="get" class="d-flex align-items-center flex-wrap gap-2">
                <span class="text-muted small fw-500 me-1">Period:</span>
                <?php foreach (['today'=>'Today','week'=>'This Week','month'=>'This Month','year'=>'This Year','custom'=>'Custom'] as $k=>$v): ?>
                <a href="?period=<?= $k ?>"
                   class="btn btn-sm <?= $period===$k ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= $v ?>
                </a>
                <?php endforeach; ?>

                <?php if ($period === 'custom'): ?>
                <div class="d-flex align-items-center gap-2 ms-2">
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($dateFrom ?: date('Y-m-01')) ?>">
                    <span class="text-muted">–</span>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($dateTo ?: date('Y-m-d')) ?>">
                    <input type="hidden" name="period" value="custom">
                    <button class="btn btn-sm btn-primary">Apply</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ── KPI CARDS ────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <!-- Revenue -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Total Revenue</p>
                            <h3 class="text-success mb-0">KES <?= number_format($revenue['total'], 2) ?></h3>
                            <small class="text-muted"><?= $revenue['tx_count'] ?> transactions</small>
                        </div>
                        <div class="bg-success-soft rounded-circle p-2">
                            <i class="material-icons text-success">payments</i>
                        </div>
                    </div>
                    <?php if ($revenueChange !== null): ?>
                    <div class="mt-2 small <?= $revenueChange >= 0 ? 'text-success' : 'text-danger' ?>">
                        <i class="material-icons align-middle" style="font-size:0.9rem;">
                            <?= $revenueChange >= 0 ? 'trending_up' : 'trending_down' ?>
                        </i>
                        <?= abs($revenueChange) ?>% vs previous period
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Expenses -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Total Expenses</p>
                            <h3 class="text-danger mb-0">KES <?= number_format($expenses['total'], 2) ?></h3>
                            <small class="text-muted"><?= $expenses['count'] ?> expense entries</small>
                        </div>
                        <div class="bg-danger-soft rounded-circle p-2">
                            <i class="material-icons text-danger">receipt_long</i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Profit -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Net Profit</p>
                            <h3 class="<?= $profit >= 0 ? 'text-primary' : 'text-danger' ?> mb-0">
                                KES <?= number_format($profit, 2) ?>
                            </h3>
                            <?php if ($revenue['total'] > 0): ?>
                            <small class="text-muted">
                                <?= round(($profit / $revenue['total']) * 100, 1) ?>% margin
                            </small>
                            <?php endif; ?>
                        </div>
                        <div class="bg-primary-soft rounded-circle p-2">
                            <i class="material-icons text-primary">account_balance</i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users -->
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Active Users</p>
                            <h3 class="mb-0"><?= $users['active'] ?></h3>
                            <small class="text-muted">
                                +<?= $newUsers ?> new · <?= $users['expired'] ?> expired
                            </small>
                        </div>
                        <div class="bg-info-soft rounded-circle p-2">
                            <i class="material-icons text-info">people</i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── CHARTS ROW ────────────────────────────────────────────── -->
    <div class="row g-4 mb-4">

        <!-- Daily Revenue Trend -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="material-icons text-primary me-2">show_chart</i>
                        <h6 class="mb-0">Revenue Trend</h6>
                    </div>
                    <span class="badge bg-success-soft text-success">
                        KES <?= number_format($revenue['total'], 2) ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if (empty($dailyRevenue)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="material-icons" style="font-size:3rem;">bar_chart</i>
                            <p class="mt-2">No revenue data for this period</p>
                        </div>
                    <?php else: ?>
                        <canvas id="revenueChart" height="100"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Revenue Split -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3 d-flex align-items-center">
                    <i class="material-icons text-warning me-2">donut_small</i>
                    <h6 class="mb-0">Revenue Split</h6>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <canvas id="splitChart" style="max-height:200px"></canvas>
                    <div class="mt-3 w-100">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><span class="badge bg-primary me-1">&nbsp;</span> Hotspot</span>
                            <strong>KES <?= number_format($revenue['hotspot'], 2) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span><span class="badge bg-warning me-1">&nbsp;</span> PPPoE</span>
                            <strong>KES <?= number_format($revenue['pppoe'], 2) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── BOTTOM ROW ────────────────────────────────────────────── -->
    <div class="row g-4 mb-4">

        <!-- Top Plans -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3 d-flex align-items-center">
                    <i class="material-icons text-success me-2">workspace_premium</i>
                    <h6 class="mb-0">Top Plans by Revenue</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($topPlans)): ?>
                        <p class="text-muted text-center py-4">No data</p>
                    <?php else: ?>
                        
                    <table id="datatablesSimple" class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">Plan</th>
                                <th class="small text-center">Users</th>
                                <th class="small text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($topPlans as $plan): ?>
                        <tr>
                            <td>
                                <span class="fw-500"><?= htmlspecialchars($plan['profile_name']) ?></span>
                                <br>
                                <small class="badge bg-<?= $plan['plan_type']==='pppoe' ? 'warning' : 'info' ?>-soft
                                                   text-<?= $plan['plan_type']==='pppoe' ? 'warning' : 'info' ?>">
                                    <?= strtoupper($plan['plan_type']) ?>
                                </small>
                            </td>
                            <td class="text-center"><?= $plan['user_count'] ?></td>
                            <td class="text-end fw-500 text-success">
                                KES <?= number_format($plan['revenue'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Expenses by Category -->
        <div class="col-lg-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3 d-flex align-items-center">
                    <i class="material-icons text-danger me-2">category</i>
                    <h6 class="mb-0">Expenses by Category</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($expensesByCategory)): ?>
                        <p class="text-muted text-center py-4">No expenses this period</p>
                    <?php else: ?>
                        <canvas id="expenseChart" style="max-height:180px" class="mb-3"></canvas>
                        <?php foreach ($expensesByCategory as $cat): ?>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-capitalize"><?= htmlspecialchars($cat['category']) ?></span>
                            <strong>KES <?= number_format($cat['total'], 2) ?></strong>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- User Breakdown -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3 d-flex align-items-center">
                    <i class="material-icons text-info me-2">people_alt</i>
                    <h6 class="mb-0">User Overview</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-success-soft rounded p-3">
                                <div class="h4 text-success mb-0"><?= $users['active'] ?></div>
                                <small class="text-muted">Active</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-danger-soft rounded p-3">
                                <div class="h4 text-danger mb-0"><?= $users['expired'] ?></div>
                                <small class="text-muted">Expired</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-primary-soft rounded p-3">
                                <div class="h4 text-primary mb-0"><?= $users['hotspot'] ?></div>
                                <small class="text-muted">Hotspot</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-warning-soft rounded p-3">
                                <div class="h4 text-warning mb-0"><?= $users['pppoe'] ?></div>
                                <small class="text-muted">PPPoE</small>
                            </div>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Total All-Time</span>
                        <strong><?= $users['total'] ?> users</strong>
                    </div>
                    <div class="d-flex justify-content-between small mt-1">
                        <span class="text-muted">New This Period</span>
                        <strong class="text-success">+<?= $newUsers ?></strong>
                    </div>
                    <div class="d-flex justify-content-between small mt-1">
                        <span class="text-muted">Routers Online</span>
                        <strong><?= $routers['online'] ?> / <?= $routers['total'] ?></strong>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── RECENT PAYMENTS TABLE ─────────────────────────────────── -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <i class="material-icons text-primary me-2">receipt</i>
                <h6 class="mb-0">Recent Payments</h6>
            </div>
            <small class="text-muted">Last 10 in period</small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentPayments)): ?>
                <p class="text-muted text-center py-4">No payments in this period</p>
            <?php else: ?>
            <div class="table-responsive">
                <table id="datatablesSimple" class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small">Date</th>
                            <th class="small">Username</th>
                            <th class="small">Plan</th>
                            <th class="small">Type</th>
                            <th class="small">Method</th>
                            <th class="small text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                    <tr>
                        <td class="small text-muted"><?= date('d M, H:i', strtotime($p['created_at'])) ?></td>
                        <td class="small fw-500"><?= htmlspecialchars($p['username']) ?></td>
                        <td class="small"><?= htmlspecialchars($p['profile_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge bg-<?= $p['user_type']==='pppoe' ? 'warning' : 'info' ?>-soft
                                               text-<?= $p['user_type']==='pppoe' ? 'warning' : 'info' ?> small">
                                <?= strtoupper($p['user_type'] ?? '—') ?>
                            </span>
                        </td>
                        <td class="small"><?= htmlspecialchars($p['payment_method'] ?? '—') ?></td>
                        <td class="text-end fw-500 text-success small">
                            KES <?= number_format($p['amount'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /container -->
</main>
<?php require_once "../partials/footer.php"; ?>
</div>
</div>

<?php require_once "../partials/scripts.php"; ?>

<script>
const primary  = '#0061f2';
const success  = '#00ac69';
const warning  = '#f4a100';
const danger   = '#e81500';
const muted    = '#a7aeb8';

/* ── Revenue Trend Chart ───────────────────────── */
<?php if (!empty($dailyRevenue)): ?>
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
            label: 'Revenue (KES)',
            data: <?= $chartData ?>,
            borderColor: success,
            backgroundColor: 'rgba(0,172,105,0.08)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: success,
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => 'KES ' + v.toLocaleString() }
            },
            x: { grid: { display: false } }
        }
    }
});
<?php endif; ?>

/* ── Revenue Split Donut ────────────────────────── */
new Chart(document.getElementById('splitChart'), {
    type: 'doughnut',
    data: {
        labels: ['Hotspot', 'PPPoE'],
        datasets: [{
            data: [<?= $revenue['hotspot'] ?>, <?= $revenue['pppoe'] ?>],
            backgroundColor: [primary, warning],
            borderWidth: 0,
        }]
    },
    options: {
        cutout: '70%',
        plugins: { legend: { display: false } }
    }
});

/* ── Expenses by Category ───────────────────────── */
<?php if (!empty($expensesByCategory)): ?>
new Chart(document.getElementById('expenseChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $catLabels ?>,
        datasets: [{
            data: <?= $catTotals ?>,
            backgroundColor: [danger, warning, primary, success, muted, '#6f42c1', '#20c997'],
            borderWidth: 0,
        }]
    },
    options: {
        cutout: '65%',
        plugins: { legend: { display: false } }
    }
});
<?php endif; ?>
</script>
</body>
</html>