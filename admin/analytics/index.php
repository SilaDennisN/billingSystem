<?php
// admin/analytics/index.php
require_once "../../core/db.php";
require_once "../../core/auth.php";
require_once "../../core/app.php";

/* ══════════════════════════════════════
   12-MONTH INCOME + FEE TREND
══════════════════════════════════════ */
$trendStmt = $pdo->query("
    SELECT
        DATE_FORMAT(p.created_at, '%Y-%m')          AS month_key,
        DATE_FORMAT(p.created_at, '%b %Y')          AS month_label,
        IFNULL(SUM(p.amount), 0)                    AS total_income,
        ROUND(IFNULL(SUM(p.amount), 0) * 0.05, 2)  AS platform_fee
    FROM payments p
    WHERE p.status = 'used'
      AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
");
$trendRows  = $trendStmt->fetchAll();
$trendLabels = array_column($trendRows, 'month_label');
$trendIncome = array_column($trendRows, 'total_income');
$trendFees   = array_column($trendRows, 'platform_fee');

/* ══════════════════════════════════════
   SUBSCRIPTION STATUS BREAKDOWN
══════════════════════════════════════ */
$subBreakdown = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM subscriptions
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* ══════════════════════════════════════
   GATEWAY PROVIDER BREAKDOWN
══════════════════════════════════════ */
$gwBreakdown = $pdo->query("
    SELECT
        IFNULL(k.provider, 'none') AS provider,
        COUNT(DISTINCT u.user_id)  AS cnt
    FROM users u
    LEFT JOIN subscriptions s   ON s.user_id = u.user_id
    LEFT JOIN user_api_keys k   ON k.user_id = u.user_id
        AND k.provider IN ('mpesa','pesaflux','intasend')
    WHERE s.status = 'active'
    GROUP BY provider
")->fetchAll(PDO::FETCH_KEY_PAIR);

/* ══════════════════════════════════════
   NEW USER SIGNUPS — last 6 months
══════════════════════════════════════ */
$signupStmt = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%b %Y') AS month_label,
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        COUNT(*) AS cnt
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_key, month_label
    ORDER BY month_key ASC
");
$signupRows   = $signupStmt->fetchAll();
$signupLabels = array_column($signupRows, 'month_label');
$signupCounts = array_column($signupRows, 'cnt');

/* ══════════════════════════════════════
   TOP 10 EARNERS THIS MONTH
══════════════════════════════════════ */
$topEarners = $pdo->query("
    SELECT
        u.full_names,
        u.phone_number,
        IFNULL(SUM(p.amount), 0) AS income,
        ROUND(IFNULL(SUM(p.amount), 0) * 0.05, 2) AS fee
    FROM users u
    JOIN user_router_access ura ON ura.user_id = u.user_id
    JOIN payments p ON p.router_id = ura.router_id
    WHERE p.status = 'used'
      AND MONTH(p.created_at) = MONTH(CURDATE())
      AND YEAR(p.created_at)  = YEAR(CURDATE())
    GROUP BY u.user_id, u.full_names, u.phone_number
    ORDER BY income DESC
    LIMIT 10
")->fetchAll();

/* ══════════════════════════════════════
   PLATFORM SUMMARY KPIs
══════════════════════════════════════ */
$kpi = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM users)                                        AS total_users,
        (SELECT COUNT(*) FROM subscriptions WHERE status='active')          AS active_subs,
        (SELECT COUNT(*) FROM subscriptions WHERE gateway_enabled=1)        AS gateways_live,
        (SELECT IFNULL(SUM(amount),0) FROM payments
            WHERE status='used'
              AND MONTH(created_at)=MONTH(CURDATE())
              AND YEAR(created_at)=YEAR(CURDATE()))                          AS income_this_month,
        (SELECT IFNULL(SUM(amount),0) FROM payments
            WHERE status='used'
              AND MONTH(created_at)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
              AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))) AS income_last_month,
        (SELECT COUNT(*) FROM gateway_invoices WHERE status='pending')      AS gw_invoices_pending,
        (SELECT COUNT(*) FROM subscription_invoices WHERE status='overdue') AS overdue_invoices
")->fetch();

$growth = 0;
if ($kpi['income_last_month'] > 0) {
    $growth = round((($kpi['income_this_month'] - $kpi['income_last_month']) / $kpi['income_last_month']) * 100, 1);
}

/* ══════════════════════════════════════
   GATEWAY INVOICE COLLECTION RATE
══════════════════════════════════════ */
$gwCollection = $pdo->query("
    SELECT
        COUNT(*)                                            AS total,
        SUM(CASE WHEN status='paid'    THEN 1 ELSE 0 END)  AS paid,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END)  AS pending,
        SUM(CASE WHEN status='overdue' THEN 1 ELSE 0 END)  AS overdue
    FROM gateway_invoices
    WHERE MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at)  = YEAR(CURDATE())
")->fetch();

/* ══════════════════════════════════════
   DAILY REVENUE — last 30 days
══════════════════════════════════════ */
$dailyStmt = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%b %d') AS day_label,
        DATE(created_at)                 AS day_key,
        IFNULL(SUM(amount), 0)           AS total
    FROM payments
    WHERE status = 'used'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY day_key, day_label
    ORDER BY day_key ASC
");
$dailyRows   = $dailyStmt->fetchAll();
$dailyLabels = array_column($dailyRows, 'day_label');
$dailyTotals = array_column($dailyRows, 'total');
?>
<?php require_once "../../partials/head.php" ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php" ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Page Header -->
                <header style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%);border-bottom:1px solid rgba(99,102,241,.2);">
                    <div class="container-xl px-4">
                        <div class="d-flex justify-content-between align-items-center py-4 flex-wrap gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="material-icons" style="color:#818cf8;font-size:18px;">bar_chart</i>
                                    <span style="font-size:.7rem;color:#64748b;letter-spacing:.1em;text-transform:uppercase;font-weight:600;">Overview › Analytics</span>
                                </div>
                                <h1 class="text-white mb-0 fw-bold" style="font-size:1.6rem;">Platform Analytics</h1>
                                <p style="color:#64748b;" class="mb-0 mt-1 small">
                                    Revenue trends, user growth and gateway health · <?= date('F Y') ?>
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($kpi['overdue_invoices'] > 0): ?>
                                <span class="badge px-3 py-2" style="background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.25);border-radius:8px;font-size:.8rem;">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">warning</i>
                                    <?= $kpi['overdue_invoices'] ?> overdue
                                </span>
                                <?php endif; ?>
                                <?php if ($kpi['gw_invoices_pending'] > 0): ?>
                                <span class="badge px-3 py-2" style="background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.25);border-radius:8px;font-size:.8rem;">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">pending_actions</i>
                                    <?= $kpi['gw_invoices_pending'] ?> pending gateways
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 mt-4">

                    <!-- ══ KPI CARDS ══ -->
                    <div class="row g-3 mb-4">

                        <div class="col-6 col-lg-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted fw-500">Income This Month</span>
                                        <span style="width:34px;height:34px;background:rgba(99,102,241,.08);border-radius:9px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:17px;color:#6366f1;">payments</i>
                                        </span>
                                    </div>
                                    <div class="h4 fw-bold mb-1">KES <?= number_format($kpi['income_this_month'], 0) ?></div>
                                    <div class="small <?= $growth >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <i class="material-icons" style="font-size:13px;vertical-align:middle;"><?= $growth >= 0 ? 'arrow_upward' : 'arrow_downward' ?></i>
                                        <?= abs($growth) ?>% vs last month
                                    </div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#6366f1,#8b5cf6);"></div>
                            </div>
                        </div>

                        <div class="col-6 col-lg-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted fw-500">Platform Fee Due</span>
                                        <span style="width:34px;height:34px;background:rgba(251,191,36,.08);border-radius:9px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:17px;color:#fbbf24;">account_balance</i>
                                        </span>
                                    </div>
                                    <div class="h4 fw-bold mb-1">KES <?= number_format($kpi['income_this_month'] * 0.05, 0) ?></div>
                                    <div class="small text-muted">5% of billed income</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#fbbf24,#f59e0b);"></div>
                            </div>
                        </div>

                        <div class="col-6 col-lg-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted fw-500">Total Users</span>
                                        <span style="width:34px;height:34px;background:rgba(74,222,128,.08);border-radius:9px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:17px;color:#4ade80;">people</i>
                                        </span>
                                    </div>
                                    <div class="h4 fw-bold mb-1"><?= number_format($kpi['total_users']) ?></div>
                                    <div class="small text-muted"><?= number_format($kpi['active_subs']) ?> active subscriptions</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#4ade80,#22c55e);"></div>
                            </div>
                        </div>

                        <div class="col-6 col-lg-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-muted fw-500">Gateways Live</span>
                                        <span style="width:34px;height:34px;background:rgba(14,165,233,.08);border-radius:9px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:17px;color:#0ea5e9;">mobile_friendly</i>
                                        </span>
                                    </div>
                                    <div class="h4 fw-bold mb-1"><?= number_format($kpi['gateways_live']) ?></div>
                                    <div class="small text-muted">M-Pesa gateways active</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#0ea5e9,#38bdf8);"></div>
                            </div>
                        </div>

                    </div>

                    <!-- ══ CHARTS ROW 1 ══ -->
                    <div class="row g-3 mb-4">

                        <!-- 12-month income trend -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:16px;overflow:hidden;">
                                <div class="card-header px-4 py-3"
                                    style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-icons" style="color:#818cf8;font-size:18px;">show_chart</i>
                                        <span class="fw-bold text-white">12-Month Revenue Trend</span>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <canvas id="trendChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Subscription breakdown pie -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:16px;overflow:hidden;">
                                <div class="card-header px-4 py-3"
                                    style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-icons" style="color:#818cf8;font-size:18px;">donut_large</i>
                                        <span class="fw-bold text-white">Subscription Status</span>
                                    </div>
                                </div>
                                <div class="card-body p-4 d-flex flex-column justify-content-center">
                                    <canvas id="subPieChart" height="200"></canvas>
                                    <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                                        <?php
                                        $subColors = ['active'=>'#22c55e','suspended'=>'#ef4444','cancelled'=>'#94a3b8'];
                                        foreach ($subBreakdown as $st => $cnt):
                                            $col = $subColors[$st] ?? '#6366f1';
                                        ?>
                                        <div class="d-flex align-items-center gap-1">
                                            <span style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;display:inline-block;"></span>
                                            <span class="small text-muted"><?= ucfirst($st) ?> (<?= $cnt ?>)</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ══ CHARTS ROW 2 ══ -->
                    <div class="row g-3 mb-4">

                        <!-- Daily revenue last 30 days -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:16px;overflow:hidden;">
                                <div class="card-header px-4 py-3"
                                    style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-icons" style="color:#818cf8;font-size:18px;">timeline</i>
                                        <span class="fw-bold text-white">Daily Revenue — Last 30 Days</span>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <canvas id="dailyChart" height="100"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Gateway provider split + invoice health -->
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:16px;overflow:hidden;">
                                <div class="card-header px-4 py-3"
                                    style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-icons" style="color:#818cf8;font-size:18px;">mobile_friendly</i>
                                        <span class="fw-bold text-white">Gateway Providers</span>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <canvas id="gwPieChart" height="160"></canvas>
                                    <div class="mt-3 d-flex flex-wrap gap-2 justify-content-center">
                                        <?php
                                        $gwColors = ['mpesa'=>'#22c55e','intasend'=>'#0ea5e9','pesaflux'=>'#fbbf24','none'=>'#94a3b8'];
                                        $gwLabels = ['mpesa'=>'M-Pesa (Free)','intasend'=>'IntaSend (Free)','pesaflux'=>'PesaFlux (Billed)','none'=>'No Gateway'];
                                        foreach ($gwBreakdown as $prov => $cnt):
                                            $col = $gwColors[$prov] ?? '#6366f1';
                                            $lbl = $gwLabels[$prov] ?? ucfirst($prov);
                                        ?>
                                        <div class="d-flex align-items-center gap-1">
                                            <span style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;display:inline-block;"></span>
                                            <span class="small text-muted"><?= $lbl ?> (<?= $cnt ?>)</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Invoice collection health -->
                                    <hr class="my-3">
                                    <div class="small fw-600 text-muted mb-2">Gateway Invoice Health (This Month)</div>
                                    <?php
                                    $gwTotal = max(1, $gwCollection['total']);
                                    $paidPct    = round($gwCollection['paid']    / $gwTotal * 100);
                                    $pendingPct = round($gwCollection['pending'] / $gwTotal * 100);
                                    $overduePct = round($gwCollection['overdue'] / $gwTotal * 100);
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span style="color:#22c55e;">Paid</span>
                                            <span><?= $gwCollection['paid'] ?> / <?= $gwCollection['total'] ?></span>
                                        </div>
                                        <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                                            <div style="width:<?= $paidPct ?>%;height:100%;background:#22c55e;border-radius:3px;"></div>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span style="color:#fbbf24;">Pending</span>
                                            <span><?= $gwCollection['pending'] ?></span>
                                        </div>
                                        <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                                            <div style="width:<?= $pendingPct ?>%;height:100%;background:#fbbf24;border-radius:3px;"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span style="color:#ef4444;">Overdue</span>
                                            <span><?= $gwCollection['overdue'] ?></span>
                                        </div>
                                        <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                                            <div style="width:<?= $overduePct ?>%;height:100%;background:#ef4444;border-radius:3px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- ══ ROW 3: New Signups + Top Earners ══ -->
                    <div class="row g-3 mb-4">

                        <!-- New user signups bar -->
                        <div class="col-lg-5">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:16px;overflow:hidden;">
                                <div class="card-header px-4 py-3"
                                    style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-icons" style="color:#818cf8;font-size:18px;">person_add</i>
                                        <span class="fw-bold text-white">New Signups (6 months)</span>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <canvas id="signupChart" height="160"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Top earners this month -->
                        <div class="col-lg-7">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:16px;overflow:hidden;">
                                <div class="card-header px-4 py-3"
                                    style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="material-icons" style="color:#fbbf24;font-size:18px;">emoji_events</i>
                                        <span class="fw-bold text-white">Top Earners — <?= date('F Y') ?></span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-hover mb-0 align-middle">
                                        <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                            <tr>
                                                <th class="px-4 py-2 small text-muted fw-600">#</th>
                                                <th class="py-2 small text-muted fw-600">User</th>
                                                <th class="py-2 small text-muted fw-600">Income</th>
                                                <th class="py-2 small text-muted fw-600">Platform Fee</th>
                                                <th class="py-2 small text-muted fw-600">Share</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $topMax = !empty($topEarners) ? $topEarners[0]['income'] : 1;
                                        foreach ($topEarners as $i => $te):
                                            $barPct = $topMax > 0 ? round(($te['income'] / $topMax) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td class="px-4">
                                                <span class="fw-bold" style="color:<?= $i===0?'#fbbf24':($i===1?'#94a3b8':($i===2?'#cd7c2f':'#cbd5e1')) ?>;font-size:.85rem;"><?= $i+1 ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-600 small"><?= htmlspecialchars($te['full_names']) ?></div>
                                                <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($te['phone_number']) ?></div>
                                            </td>
                                            <td class="fw-bold small">KES <?= number_format($te['income'], 0) ?></td>
                                            <td><span style="color:#f59e0b;font-size:.82rem;font-weight:600;">KES <?= number_format($te['fee'], 0) ?></span></td>
                                            <td style="min-width:90px;">
                                                <div style="height:5px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                                                    <div style="width:<?= $barPct ?>%;height:100%;background:linear-gradient(90deg,#6366f1,#8b5cf6);border-radius:3px;"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($topEarners)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="material-icons d-block mb-1" style="font-size:30px;opacity:.2;">emoji_events</i>
                                                No income data yet this month.
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>

                </div><!-- /container -->
            </main>
            <?php require_once "../../partials/footer.php" ?>
        </div>
    </div>

    <?php require_once "../../partials/scripts.php" ?>

    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
    Chart.defaults.font.family = 'inherit';
    Chart.defaults.color = '#94a3b8';

    const gridColor  = 'rgba(99,102,241,.07)';
    const tooltipBg  = '#1e293b';

    // ── 12-month trend ──────────────────────────────────────────
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [
                {
                    label: 'Billed Income (KES)',
                    data: <?= json_encode(array_map('floatval', $trendIncome)) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,.08)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#6366f1',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                },
                {
                    label: 'Platform Fee (KES)',
                    data: <?= json_encode(array_map('floatval', $trendFees)) ?>,
                    borderColor: '#fbbf24',
                    backgroundColor: 'rgba(251,191,36,.06)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#fbbf24',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, padding: 16 } },
                tooltip: {
                    backgroundColor: tooltipBg,
                    padding: 12,
                    callbacks: {
                        label: ctx => ' KES ' + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                x: { grid: { color: gridColor } },
                y: {
                    grid: { color: gridColor },
                    ticks: { callback: v => 'KES ' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v) }
                }
            }
        }
    });

    // ── Daily revenue ───────────────────────────────────────────
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($dailyLabels) ?>,
            datasets: [{
                label: 'Daily Income (KES)',
                data: <?= json_encode(array_map('floatval', $dailyTotals)) ?>,
                backgroundColor: 'rgba(99,102,241,.55)',
                borderColor: '#6366f1',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: tooltipBg,
                    callbacks: { label: ctx => ' KES ' + ctx.parsed.y.toLocaleString() }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                y: {
                    grid: { color: gridColor },
                    ticks: { callback: v => 'KES ' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v) }
                }
            }
        }
    });

    // ── Subscription status pie ─────────────────────────────────
    new Chart(document.getElementById('subPieChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map('ucfirst', array_keys($subBreakdown))) ?>,
            datasets: [{
                data: <?= json_encode(array_values($subBreakdown)) ?>,
                backgroundColor: ['#22c55e','#ef4444','#94a3b8'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: tooltipBg }
            }
        }
    });

    // ── Gateway providers pie ───────────────────────────────────
    new Chart(document.getElementById('gwPieChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($k) => ['mpesa'=>'M-Pesa','intasend'=>'IntaSend','pesaflux'=>'PesaFlux','none'=>'None'][$k] ?? ucfirst($k), array_keys($gwBreakdown))) ?>,
            datasets: [{
                data: <?= json_encode(array_values($gwBreakdown)) ?>,
                backgroundColor: ['#22c55e','#0ea5e9','#fbbf24','#94a3b8'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '60%',
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: tooltipBg }
            }
        }
    });

    // ── User signups bar ────────────────────────────────────────
    new Chart(document.getElementById('signupChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($signupLabels) ?>,
            datasets: [{
                label: 'New Users',
                data: <?= json_encode(array_map('intval', $signupCounts)) ?>,
                backgroundColor: 'rgba(74,222,128,.55)',
                borderColor: '#22c55e',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: tooltipBg }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    grid: { color: gridColor },
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
    </script>
</body>
</html>