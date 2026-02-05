<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

/* ======================
   METRICS
====================== */
$activeUsers = $pdo->query("SELECT COUNT(*) FROM hotspot_users WHERE status='active'")->fetchColumn();
$expiredUsers = $pdo->query("SELECT COUNT(*) FROM hotspot_users WHERE status='expired'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM hotspot_users")->fetchColumn();
$totalPackages = $pdo->query("SELECT COUNT(*) FROM hotspot_profiles")->fetchColumn();
$routersOnline = $pdo->query("SELECT COUNT(*) FROM routers WHERE online_status='online'")->fetchColumn();
$totalRouters = $pdo->query("SELECT COUNT(*) FROM routers")->fetchColumn();

$todayRevenue = $pdo->query("
    SELECT IFNULL(SUM(amount),0) 
    FROM payments 
    WHERE status='used'
    AND DATE(created_at)=CURDATE()
")->fetchColumn();

$monthRevenue = $pdo->query("
    SELECT IFNULL(SUM(amount),0) 
    FROM payments 
    WHERE status='used'
    AND MONTH(created_at)=MONTH(CURDATE())
    AND YEAR(created_at)=YEAR(CURDATE())
")->fetchColumn();

$totalRevenue = $pdo->query("
    SELECT IFNULL(SUM(amount),0) 
    FROM payments 
    WHERE status='used'
")->fetchColumn();

$pendingPayments = $pdo->query("
    SELECT COUNT(*) FROM payments WHERE status='pending'
")->fetchColumn();

/* ======================
   RECENT DATA
====================== */
$recentPayments = $pdo->query("
    SELECT p.amount, p.created_at, h.username, p.payment_method
    FROM payments p
    LEFT JOIN hotspot_users h ON h.username = p.username
    WHERE p.status='used'
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetchAll();

$recentUsers = $pdo->query("
    SELECT username, expires_at, status, user_type, created_at
    FROM hotspot_users
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

/* ======================
   ANALYTICS DATA
====================== */
// Revenue by day for last 7 days
$revenueByDay = $pdo->query("
    SELECT DATE(created_at) as date, SUM(amount) as total
    FROM payments
    WHERE status='used'
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// User growth (last 30 days)
$userGrowth = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM hotspot_users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Top packages by user count
$topPackages = $pdo->query("
    SELECT hp.profile_name, COUNT(hu.user_id) as user_count, hp.price
    FROM hotspot_profiles hp
    LEFT JOIN hotspot_users hu ON hu.plan_id = hp.id
    GROUP BY hp.id
    ORDER BY user_count DESC
    LIMIT 5
")->fetchAll();

// Users by type
$usersByType = $pdo->query("
    SELECT user_type, COUNT(*) as count
    FROM hotspot_users
    GROUP BY user_type
")->fetchAll();

// Expiring soon (next 7 days)
$expiringSoon = $pdo->query("
    SELECT COUNT(*) 
    FROM hotspot_users 
    WHERE status='active' 
    AND expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();
?>

<?php require_once "../partials/head.php"; ?>
<body class="nav-fixed bg-light">

<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php"; ?>

<div id="layoutDrawer_content">
<main>

<!-- Enhanced Header -->
<header class="bg-primary">
    <div class="container-xl px-1">
        <div class="d-flex justify-content-between align-items-center py-3">
            <div>
                <h1 class="text-white mb-0 display-6">
                    <i class="fas fa-chart-line me-2"></i>Dashboard
                </h1>
                <p class="text-white-50 mb-0 mt-1">ISP Overview & System Analytics</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-soft text-success">
                    <i class="fas fa-circle me-1"></i>System Online
                </span>
                <span class="badge bg-info-soft text-info">
                    <i class="fas fa-clock me-1"></i><?= date('M d, Y H:i') ?>
                </span>
            </div>
        </div>
    </div>
</header>

<div class="container-xl px-1 mt-4">

<!-- =======================
     TOP STATS - Enhanced
======================= -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-raised border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small text-muted mb-1">Active Users</div>
                        <div class="h2 mb-0"><?= $activeUsers ?></div>
                        <div class="small text-success">
                            <i class="fas fa-arrow-up me-1"></i>
                            <?= round(($activeUsers/$totalUsers)*100, 1) ?>% of total
                        </div>
                    </div>
                    <div class="ms-3">
                        <div class="avatar avatar-xl bg-success-soft">
                            <i class="fas fa-users fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
                <a href="../hotspotUsers" class="btn btn-sm btn-success-soft text-success w-100 mt-3">
                    <i class="fas fa-eye me-1"></i>View Users
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-raised border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small text-muted mb-1">Today's Revenue</div>
                        <div class="h2 mb-0">KES <?= number_format($todayRevenue, 0) ?></div>
                        <div class="small text-primary">
                            <i class="fas fa-calendar-day me-1"></i>
                            <?= date('l') ?>
                        </div>
                    </div>
                    <div class="ms-3">
                        <div class="avatar avatar-xl bg-primary-soft">
                            <i class="fas fa-coins fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
                <a href="../payments" class="btn btn-sm btn-primary-soft text-primary w-100 mt-3">
                    <i class="fas fa-money-bill-wave me-1"></i>View Payments
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-raised border-start border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small text-muted mb-1">Expiring Soon</div>
                        <div class="h2 mb-0"><?= $expiringSoon ?></div>
                        <div class="small text-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Next 7 days
                        </div>
                    </div>
                    <div class="ms-3">
                        <div class="avatar avatar-xl bg-warning-soft">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
                <a href="../hotspotUsers?filter=expiring" class="btn btn-sm btn-warning-soft text-warning w-100 mt-3">
                    <i class="fas fa-list me-1"></i>View List
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card card-raised border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small text-muted mb-1">Routers Online</div>
                        <div class="h2 mb-0"><?= $routersOnline ?> / <?= $totalRouters ?></div>
                        <div class="small text-info">
                            <i class="fas fa-signal me-1"></i>
                            <?= round(($routersOnline/$totalRouters)*100, 1) ?>% uptime
                        </div>
                    </div>
                    <div class="ms-3">
                        <div class="avatar avatar-xl bg-info-soft">
                            <i class="fas fa-server fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
                <a href="../routers" class="btn btn-sm btn-info-soft text-info w-100 mt-3">
                    <i class="fas fa-cog me-1"></i>Manage Routers
                </a>
            </div>
        </div>
    </div>
</div>

<!-- =======================
     SECONDARY STATS
======================= -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card card-raised h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-2">Month Revenue</div>
                <div class="h3 text-success mb-0">KES <?= number_format($monthRevenue, 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card card-raised h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-2">Total Revenue</div>
                <div class="h3 text-primary mb-0">KES <?= number_format($totalRevenue, 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card card-raised h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-2">Expired Users</div>
                <div class="h3 text-danger mb-0"><?= $expiredUsers ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card card-raised h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-2">Pending Payments</div>
                <div class="h3 text-warning mb-0"><?= $pendingPayments ?></div>
            </div>
        </div>
    </div>
</div>

<!-- =======================
     CHARTS ROW
======================= -->
<div class="row mb-4">
    <!-- Revenue Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card card-raised h-100">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-bar me-2"></i>Revenue Overview (Last 7 Days)
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- User Distribution -->
    <div class="col-lg-4 mb-4">
        <div class="card card-raised h-100">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-pie-chart me-2"></i>Users by Type
            </div>
            <div class="card-body">
                <canvas id="userTypeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- =======================
     MAIN CONTENT
======================= -->
<div class="row">

<!-- LEFT COLUMN -->
<div class="col-lg-8">

    <!-- Recent Payments -->
    <div class="card card-raised shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <div><i class="fas fa-money-bill-wave me-2"></i>Recent Payments</div>
                <a href="../payments" class="btn btn-sm btn-light">View All</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th><i class="fas fa-user me-1"></i>User</th>
                            <th><i class="fas fa-credit-card me-1"></i>Method</th>
                            <th><i class="fas fa-money-bill me-1"></i>Amount</th>
                            <th><i class="fas fa-clock me-1"></i>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-primary-soft">
                                        <i class="fas fa-user text-primary"></i>
                                    </div>
                                    <strong><?= htmlspecialchars($p['username'] ?? 'Guest') ?></strong>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-soft text-secondary">
                                    <?= strtoupper($p['payment_method'] ?? 'N/A') ?>
                                </span>
                            </td>
                            <td><strong class="text-success">KES <?= number_format($p['amount'],2) ?></strong></td>
                            <td>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?= date('M d, H:i', strtotime($p['created_at'])) ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentPayments)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">No recent payments</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Packages -->
    <div class="card card-raised shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-trophy me-2"></i>Top Packages by Users
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Package Name</th>
                            <th>Users</th>
                            <th>Price</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topPackages as $pkg): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pkg['profile_name']) ?></strong></td>
                            <td>
                                <span class="badge bg-primary-soft text-primary">
                                    <?= $pkg['user_count'] ?> users
                                </span>
                            </td>
                            <td>KES <?= number_format($pkg['price'] ?? 0, 2) ?></td>
                            <td><strong class="text-success">KES <?= number_format(($pkg['price'] ?? 0) * $pkg['user_count'], 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="card card-raised shadow-sm">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <div><i class="fas fa-user-plus me-2"></i>Recently Added Users</div>
                <a href="../hotspotUsers" class="btn btn-sm btn-light">View All</a>
            </div>
        </div>
        <div class="card-body">
            <?php foreach ($recentUsers as $u): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-3 bg-<?= $u['user_type']=='pppoe'?'info':'secondary' ?>-soft">
                            <i class="fas fa-user text-<?= $u['user_type']=='pppoe'?'info':'secondary' ?>"></i>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($u['username']) ?></strong>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Expires: <?= date('M d, Y', strtotime($u['expires_at'])) ?>
                            </small>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?= $u['status']=='active'?'success':'danger' ?>">
                            <?= ucfirst($u['status']) ?>
                        </span>
                        <br>
                        <span class="badge bg-<?= $u['user_type']=='pppoe'?'info':'warning' ?>-soft text-<?= $u['user_type']=='pppoe'?'info':'warning' ?> mt-1">
                            <?= strtoupper($u['user_type']) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recentUsers)): ?>
                <p class="text-center text-muted py-3">No recent users</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- RIGHT COLUMN -->
<div class="col-lg-4">

    <!-- Quick Actions -->
    <div class="card card-raised shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-bolt me-2"></i>Quick Actions
        </div>
        <div class="card-body d-grid gap-2">
            <a href="../hotspotUsers/add" class="btn btn-success">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </a>
            <a href="../hotspotProfiles/add" class="btn btn-primary">
                <i class="fas fa-box me-2"></i>Create Package
            </a>
            <a href="../payments" class="btn btn-outline-primary">
                <i class="fas fa-eye me-2"></i>View Payments
            </a>
            <a href="../routers" class="btn btn-outline-secondary">
                <i class="fas fa-server me-2"></i>Manage Routers
            </a>
            <a href="../invoices" class="btn btn-outline-info">
                <i class="fas fa-file-invoice me-2"></i>View Invoices
            </a>
        </div>
    </div>

    <!-- System Status -->
    <div class="card card-raised shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-heartbeat me-2"></i>System Status
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                <div>
                    <div class="small text-muted">Database</div>
                    <strong>Connected</strong>
                </div>
                <i class="fas fa-check-circle fa-2x text-success"></i>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                <div>
                    <div class="small text-muted">Routers Online</div>
                    <strong><?= $routersOnline ?> / <?= $totalRouters ?></strong>
                </div>
                <i class="fas fa-server fa-2x text-info"></i>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted">Active Sessions</div>
                    <strong><?= $activeUsers ?></strong>
                </div>
                <i class="fas fa-users fa-2x text-success"></i>
            </div>
        </div>
    </div>

    <!-- System Notes -->
    <div class="card card-raised shadow-sm">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-sticky-note me-2"></i>System Notes
        </div>
        <div class="card-body">
            <div class="small text-muted mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Quick notes and reminders for admin team
            </div>
            <textarea class="form-control mb-2" rows="4" placeholder="Write a note…"></textarea>
            <button class="btn btn-primary btn-sm w-100">
                <i class="fas fa-save me-1"></i>Save Note
            </button>
        </div>
    </div>

</div>

</div>

</div>
</main>

<?php require_once "../partials/footer.php"; ?>
</div>
</div>

<?php require_once "../partials/scripts.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($revenueByDay as $r): ?>
                    '<?= date('M d', strtotime($r['date'])) ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Revenue (KES)',
                data: [
                    <?php foreach ($revenueByDay as $r): ?>
                        <?= $r['total'] ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(0, 97, 242, 0.8)',
                borderColor: 'rgba(0, 97, 242, 1)',
                borderWidth: 2,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'KES ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // User Type Pie Chart
    const userTypeCtx = document.getElementById('userTypeChart').getContext('2d');
    new Chart(userTypeCtx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php foreach ($usersByType as $ut): ?>
                    '<?= strtoupper($ut['user_type']) ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($usersByType as $ut): ?>
                        <?= $ut['count'] ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    'rgba(0, 97, 242, 0.8)',
                    'rgba(244, 161, 0, 0.8)',
                    'rgba(0, 186, 136, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

</body>
</html>