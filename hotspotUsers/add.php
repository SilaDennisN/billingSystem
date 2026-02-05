<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";

if (!is_logged_in()) exit;

$routers = $pdo->query("SELECT router_id, name FROM routers WHERE status='active'")->fetchAll();
$plans = [];
$router_id = $_GET['router_id'] ?? null;

if ($router_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM billing_plans 
        WHERE router_id = ? AND status='active'
    ");
    $stmt->execute([$router_id]);
    $plans = $stmt->fetchAll();
}
?>

 <?php require_once "../partials/head.php"  ?>
    <body class="nav-fixed bg-light">
        <!-- Top app bar navigation menu-->
        <?php require_once "../partials/topnav.php"  ?>
        <!-- Layout wrapper-->
        <div id="layoutDrawer">
            <!-- Layout navigation-->
            <?php require_once "../partials/sidebar.php"  ?>
            <!-- Layout content-->
            <div id="layoutDrawer_content">
                <!-- Main page content-->
                <main>
                    <!-- Page header-->
                    <header class="bg-dark">
                        <div class="container-xl px-2"><h1 class="text-white py-3 mb-0 display-6">Blank Page</h1></div>
                    </header>
<form method="POST" action="../hotspotUsers/store">

    <label>Router</label>
    <select name="router_id" class="form-select"
        onchange="location='?router_id='+this.value" required>
        <option value="">-- Select Router --</option>
        <?php foreach ($routers as $r): ?>
            <option value="<?= $r['router_id'] ?>"
                <?= $router_id==$r['router_id']?'selected':'' ?>>
                <?= $r['name'] ?>
            </option>
        <?php endforeach ?>
    </select>

    <?php if ($plans): ?>
        <label class="mt-3">Billing Plan</label>
        <select name="plan_id" class="form-select" required>
            <?php foreach ($plans as $p): ?>
                <option value="<?= $p['plan_id'] ?>">
                    <?= $p['name'] ?> (<?= $p['price'] ?>)
                </option>
            <?php endforeach ?>
        </select>
    <?php endif ?>

    <label class="mt-3">Username</label>
    <input name="username" class="form-control" required>

    <label class="mt-3">Password</label>
    <input name="password" class="form-control" required>

    <button class="btn btn-primary mt-4">Create User</button>
</form>


                </main>
                <!-- Footer-->
                <!-- Min-height is set inline to match the height of the drawer footer-->
                <?php require_once "../partials/footer.php"  ?>
            </div>
        </div>
        <?php require_once "../partials/scripts.php"  ?>
</body>    
</html>
