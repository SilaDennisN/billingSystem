<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

$plans = $pdo->query("
    SELECT bp.*, r.name AS router_name
    FROM billing_plans bp
    JOIN routers r ON r.router_id = bp.router_id
    ORDER BY bp.created_at DESC
")->fetchAll();
?>

<?php require_once "../partials/head.php" ?>
<body class="nav-fixed bg-light">

<?php require_once "../partials/topnav.php" ?>

<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php" ?>

<div id="layoutDrawer_content">
<main>

<header class="bg-primary">
    <div class="container-xl px-5">
        <h1 class="text-white py-3 mb-0 display-6">Billing Plans</h1>
    </div>
</header>

<div class="container-xl px-5 mt-4">

    <div class="card">
        <div class="card-body">

            <div class="d-flex justify-content-between mb-3">
                <h5 class="card-title">Plans</h5>
                <a href="../plans/add" class="btn btn-primary btn-sm">Add Plan</a>
            </div>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Router</th>
                        <th>Hotspot Profile</th>
                        <th>Price</th>
                        <th>Validity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($plans as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['router_name']) ?></td>
                        <td><?= htmlspecialchars($p['hotspot_profile']) ?></td>
                        <td><?= number_format($p['price'], 2) ?></td>
                        <td>
                            <?= $p['validity_days'] ? $p['validity_days'] . ' days' : '' ?>
                            <?= $p['validity_hours'] ? $p['validity_hours'] . ' hours' : '' ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $p['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= $p['status'] ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>

        </div>
    </div>

</div>

</main>
<?php require_once "../partials/footer.php" ?>
</div>
</div>

<?php require_once "../partials/scripts.php" ?>
</body>
</html>
