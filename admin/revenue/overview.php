<?php
// admin/revenue/overview.php  — Per-User Income
require_once "../../core/db.php";
require_once "../../core/auth.php";
require_once "../../core/app.php";

/* ══════════════════════════════════════
   DATE RANGE — current & previous month
══════════════════════════════════════ */
$month = (int)($_GET['month'] ?? date('n'));
$year  = (int)($_GET['year']  ?? date('Y'));
// Clamp
if ($month < 1 || $month > 12) { $month = (int)date('n'); }
if ($year  < 2020 || $year > 2030) { $year  = (int)date('Y'); }

$monthLabel = date('F Y', mktime(0,0,0,$month,1,$year));
$prevMonth  = $month === 1 ? 12 : $month - 1;
$prevYear   = $month === 1 ? $year - 1 : $year;

/* ══════════════════════════════════════
   PLATFORM TOTALS
══════════════════════════════════════ */
$totals = $pdo->prepare("
    SELECT
        IFNULL(SUM(p.amount), 0)                    AS total_income,
        IFNULL(ROUND(SUM(p.amount) * 0.05, 2), 0)  AS total_fee
    FROM payments p
    WHERE p.status = 'used'
      AND MONTH(p.created_at) = ?
      AND YEAR(p.created_at)  = ?
");
$totals->execute([$month, $year]);
$totalsRow = $totals->fetch();

$prevTotals = $pdo->prepare("
    SELECT IFNULL(SUM(p.amount), 0) AS total_income
    FROM payments p
    WHERE p.status = 'used'
      AND MONTH(p.created_at) = ?
      AND YEAR(p.created_at)  = ?
");
$prevTotals->execute([$prevMonth, $prevYear]);
$prevRow = $prevTotals->fetch();

$growthPct = 0;
if ($prevRow['total_income'] > 0) {
    $growthPct = round((($totalsRow['total_income'] - $prevRow['total_income']) / $prevRow['total_income']) * 100, 1);
}

$activeUsers = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn();

/* ══════════════════════════════════════
   PER-USER INCOME
══════════════════════════════════════ */
$stmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.full_names,
        u.phone_number,
        u.email,
        s.status           AS sub_status,
        s.gateway_enabled,
        s.gateway_plan,
        k.provider         AS key_provider,
        IFNULL(income.total, 0)                        AS monthly_income,
        ROUND(IFNULL(income.total, 0) * 0.05, 2)       AS platform_fee,
        IFNULL(prev_income.total, 0)                    AS prev_income
    FROM users u
    LEFT JOIN subscriptions s ON s.user_id = u.user_id
    LEFT JOIN user_api_keys k ON k.user_id = u.user_id
        AND k.provider IN ('mpesa','pesaflux','intasend')
    LEFT JOIN (
        SELECT ura.user_id, SUM(p.amount) AS total
        FROM payments p
        JOIN user_router_access ura ON ura.router_id = p.router_id
        WHERE p.status = 'used'
          AND MONTH(p.created_at) = ?
          AND YEAR(p.created_at)  = ?
        GROUP BY ura.user_id
    ) income ON income.user_id = u.user_id
    LEFT JOIN (
        SELECT ura.user_id, SUM(p.amount) AS total
        FROM payments p
        JOIN user_router_access ura ON ura.router_id = p.router_id
        WHERE p.status = 'used'
          AND MONTH(p.created_at) = ?
          AND YEAR(p.created_at)  = ?
        GROUP BY ura.user_id
    ) prev_income ON prev_income.user_id = u.user_id
    WHERE s.status = 'active'
    ORDER BY monthly_income DESC
    LIMIT 100
");
$stmt->execute([$month, $year, $prevMonth, $prevYear]);
$users = $stmt->fetchAll();

// Top earner
$topIncome = !empty($users) ? $users[0]['monthly_income'] : 1;
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
                                    <i class="material-icons" style="color:#818cf8;font-size:18px;">trending_up</i>
                                    <span style="font-size:.7rem;color:#64748b;letter-spacing:.1em;text-transform:uppercase;font-weight:600;">Revenue › Per-User Income</span>
                                </div>
                                <h1 class="text-white mb-0 fw-bold" style="font-size:1.6rem;">Per-User Income</h1>
                                <p style="color:#64748b;" class="mb-0 mt-1 small">Monthly billed income per ISP — 5% platform fee applied</p>
                            </div>
                            <!-- Month navigator -->
                            <div class="d-flex align-items-center gap-2">
                                <?php
                                $pmNav = $month === 1 ? 12 : $month - 1;
                                $pyNav = $month === 1 ? $year - 1 : $year;
                                $nmNav = $month === 12 ? 1 : $month + 1;
                                $nyNav = $month === 12 ? $year + 1 : $year;
                                ?>
                                <a href="?month=<?= $pmNav ?>&year=<?= $pyNav ?>" class="btn btn-sm"
                                    style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#a5b4fc;border-radius:8px;">
                                    <i class="material-icons" style="font-size:18px;vertical-align:middle;">chevron_left</i>
                                </a>
                                <span class="fw-bold text-white px-2"><?= $monthLabel ?></span>
                                <a href="?month=<?= $nmNav ?>&year=<?= $nyNav ?>" class="btn btn-sm"
                                    style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#a5b4fc;border-radius:8px;">
                                    <i class="material-icons" style="font-size:18px;vertical-align:middle;">chevron_right</i>
                                </a>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 mt-4">

                    <!-- KPI Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="small text-muted fw-500">Total Billed Income</span>
                                        <span style="width:36px;height:36px;background:rgba(99,102,241,.08);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:#6366f1;">payments</i>
                                        </span>
                                    </div>
                                    <div class="h4 fw-bold mb-0">KES <?= number_format($totalsRow['total_income'], 0) ?></div>
                                    <div class="small mt-1 <?= $growthPct >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <i class="material-icons" style="font-size:13px;vertical-align:middle;"><?= $growthPct >= 0 ? 'arrow_upward' : 'arrow_downward' ?></i>
                                        <?= abs($growthPct) ?>% vs <?= date('M', mktime(0,0,0,$prevMonth,1,$prevYear)) ?>
                                    </div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#6366f1,#8b5cf6);"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="small text-muted fw-500">Platform Fees (5%)</span>
                                        <span style="width:36px;height:36px;background:rgba(251,191,36,.08);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:#fbbf24;">account_balance</i>
                                        </span>
                                    </div>
                                    <div class="h4 fw-bold mb-0">KES <?= number_format($totalsRow['total_fee'], 0) ?></div>
                                    <div class="small text-muted mt-1">owed to platform</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#fbbf24,#f59e0b);"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="small text-muted fw-500">Active ISPs</span>
                                        <span style="width:36px;height:36px;background:rgba(74,222,128,.08);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:#4ade80;">people</i>
                                        </span>
                                    </div>
                                    <div class="h4 fw-bold mb-0"><?= number_format($activeUsers) ?></div>
                                    <div class="small text-muted mt-1">with active subscriptions</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#4ade80,#22c55e);"></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="small text-muted fw-500">Avg Income / ISP</span>
                                        <span style="width:36px;height:36px;background:rgba(14,165,233,.08);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:#0ea5e9;">bar_chart</i>
                                        </span>
                                    </div>
                                    <div class="h4 fw-bold mb-0">KES <?= count($users) > 0 ? number_format($totalsRow['total_income'] / count($users), 0) : '0' ?></div>
                                    <div class="small text-muted mt-1">across <?= count($users) ?> billing users</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#0ea5e9,#38bdf8);"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">
                        <div class="card-header d-flex justify-content-between align-items-center px-4 py-3"
                            style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                            <div class="d-flex align-items-center gap-2">
                                <i class="material-icons" style="color:#818cf8;font-size:20px;">manage_accounts</i>
                                <span class="fw-bold text-white">Income Breakdown</span>
                                <span class="badge ms-1" style="background:rgba(99,102,241,.2);color:#a5b4fc;font-size:.7rem;"><?= $monthLabel ?></span>
                            </div>
                            <input type="text" id="incomeSearch" class="form-control form-control-sm"
                                placeholder="Search user…"
                                style="background:rgba(255,255,255,.06);border:1px solid rgba(99,102,241,.25);color:#e2e8f0;border-radius:8px;width:180px;font-size:.8rem;">
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle" id="incomeTable">
                                <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                    <tr>
                                        <th class="px-4 py-3 small text-muted fw-600">#</th>
                                        <th class="py-3 small text-muted fw-600">User</th>
                                        <th class="py-3 small text-muted fw-600">This Month Income</th>
                                        <th class="py-3 small text-muted fw-600">Platform Fee (5%)</th>
                                        <th class="py-3 small text-muted fw-600">vs Last Month</th>
                                        <th class="py-3 small text-muted fw-600">Income Share</th>
                                        <th class="py-3 small text-muted fw-600">Provider</th>
                                        <th class="py-3 small text-muted fw-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($users as $i => $u):
                                    $pct  = $topIncome > 0 ? round(($u['monthly_income'] / $topIncome) * 100) : 0;
                                    $diff = $u['monthly_income'] - $u['prev_income'];
                                    $diffPct = $u['prev_income'] > 0 ? round(($diff / $u['prev_income']) * 100, 1) : null;
                                ?>
                                <tr class="income-row">
                                    <td class="px-4">
                                        <span class="fw-bold" style="color:<?= $i < 3 ? '#fbbf24' : '#94a3b8' ?>;font-size:.85rem;"><?= $i + 1 ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div style="width:34px;height:34px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.75rem;">
                                                <?= strtoupper(substr($u['full_names'],0,1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-600 small"><?= htmlspecialchars($u['full_names']) ?></div>
                                                <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($u['phone_number']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold" style="color:#0f172a;font-size:.95rem;">
                                            KES <?= number_format($u['monthly_income'], 0) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-600" style="color:<?= $u['platform_fee'] > 0 ? '#f59e0b' : '#94a3b8' ?>;">
                                            KES <?= number_format($u['platform_fee'], 0) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($diffPct !== null): ?>
                                            <span style="color:<?= $diff >= 0 ? '#22c55e' : '#ef4444' ?>;font-size:.8rem;font-weight:600;">
                                                <i class="material-icons" style="font-size:13px;vertical-align:middle;"><?= $diff >= 0 ? 'arrow_upward' : 'arrow_downward' ?></i>
                                                <?= abs($diffPct) ?>%
                                            </span>
                                        <?php elseif ($u['monthly_income'] > 0): ?>
                                            <span class="text-muted small">New</span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="min-width:130px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="flex-grow-1" style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden;">
                                                <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,#6366f1,#8b5cf6);border-radius:3px;transition:width .4s;"></div>
                                            </div>
                                            <span style="font-size:.7rem;color:#64748b;min-width:30px;"><?= $pct ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php $p = strtolower($u['key_provider'] ?? ''); ?>
                                        <?php if ($p === 'mpesa'): ?>
                                            <span class="badge" style="background:rgba(74,222,128,.1);color:#22c55e;font-size:.7rem;border-radius:6px;">M-Pesa</span>
                                        <?php elseif ($p === 'intasend'): ?>
                                            <span class="badge" style="background:rgba(14,165,233,.1);color:#0ea5e9;font-size:.7rem;border-radius:6px;">IntaSend</span>
                                        <?php elseif ($p === 'pesaflux'): ?>
                                            <span class="badge" style="background:rgba(251,191,36,.1);color:#fbbf24;font-size:.7rem;border-radius:6px;">PesaFlux</span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:.75rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../../admin/users/view?id=<?= $u['user_id'] ?>"
                                            class="btn btn-sm"
                                            style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:#818cf8;border-radius:7px;padding:3px 8px;">
                                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="material-icons d-block mb-2" style="font-size:40px;opacity:.25;">trending_up</i>
                                        No income data for <?= $monthLabel ?>.
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
    <script>
    document.getElementById('incomeSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#incomeTable tbody .income-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
    </script>
</body>
</html>