<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

/* ===== REVENUE ===== */
$revenue = $pdo->query("
    SELECT
        SUM(p.amount) AS total,
        SUM(CASE WHEN hu.user_type='hotspot' THEN p.amount ELSE 0 END) AS hotspot,
        SUM(CASE WHEN hu.user_type='pppoe' THEN p.amount ELSE 0 END) AS pppoe
    FROM payments p
    JOIN hotspot_users hu
        ON hu.username = p.username
       AND hu.router_id = p.router_id
    WHERE p.status IN ('confirmed','used')
")->fetch();


/* ===== EXPENSES ===== */
$expenses = $pdo->query("
    SELECT SUM(amount) AS total FROM expenses
")->fetch();

/* ===== USERS ===== */
$users = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(user_type='hotspot') AS hotspot,
        SUM(user_type='pppoe') AS pppoe,
        SUM(status='expired') AS expired
    FROM hotspot_users
")->fetch();

/* ===== ROUTERS ===== */
$routers = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(online_status='online') AS online
    FROM routers
")->fetch();

$profit = ($revenue['total'] ?? 0) - ($expenses['total'] ?? 0);
?>

<?php require_once "../partials/head.php"; ?>
<body class="nav-fixed bg-light">

<?php require_once "../partials/topnav.php"; ?>
<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php"; ?>

<div id="layoutDrawer_content">
<main>

<header class="bg-primary">
    <div class="container-xl px-5">
        <h1 class="text-white py-3 mb-0 display-6">Reports Dashboard</h1>
    </div>
</header>

<div class="container-xl px-5 mt-4">

<!-- SUMMARY CARDS -->
<div class="row g-4 mb-4">

<div class="col-md-3">
<div class="card shadow-sm">
<div class="card-body">
    <h6 class="text-muted">Total Revenue</h6>
    <h3 class="text-success">KES <?= number_format($revenue['total'],2) ?></h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow-sm">
<div class="card-body">
    <h6 class="text-muted">Total Expenses</h6>
    <h3 class="text-danger">KES <?= number_format($expenses['total'],2) ?></h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow-sm">
<div class="card-body">
    <h6 class="text-muted">Net Profit</h6>
    <h3 class="<?= $profit >= 0 ? 'text-primary':'text-danger' ?>">
        KES <?= number_format($profit,2) ?>
    </h3>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow-sm">
<div class="card-body">
    <h6 class="text-muted">Active Users</h6>
    <h3><?= $users['hotspot'] + $users['pppoe'] ?></h3>
</div>
</div>
</div>

</div>

<!-- DETAIL TABLE -->
<div class="card shadow-sm">
<div class="card-body">

<table class="table table-bordered">
<tr><th colspan="2">Revenue Breakdown</th></tr>
<tr><td>Hotspot Revenue</td><td>KES <?= number_format($revenue['hotspot'],2) ?></td></tr>
<tr><td>PPPoE Revenue</td><td>KES <?= number_format($revenue['pppoe'],2) ?></td></tr>

<tr><th colspan="2">Users</th></tr>
<tr><td>Total Users</td><td><?= $users['total'] ?></td></tr>
<tr><td>Hotspot Users</td><td><?= $users['hotspot'] ?></td></tr>
<tr><td>PPPoE Users</td><td><?= $users['pppoe'] ?></td></tr>
<tr><td>Expired Users</td><td><?= $users['expired'] ?></td></tr>

<tr><th colspan="2">Routers</th></tr>
<tr><td>Total Routers</td><td><?= $routers['total'] ?></td></tr>
<tr><td>Online Routers</td><td><?= $routers['online'] ?></td></tr>
</table>

<a href="download.php" class="btn btn-outline-primary mt-3">
    📥 Download Full Report (PDF)
</a>

</div>
</div>

</div>
</main>

<?php require_once "../partials/footer.php"; ?>
</div>
</div>

<?php require_once "../partials/scripts.php"; ?>
</body>
</html>
