<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

$routers = $pdo->query("SELECT router_id, name FROM routers WHERE status='active'")->fetchAll();
$profiles = [];
$router_id = $_GET['router_id'] ?? null;

if ($router_id) {
    $client = router_connect($router_id);
    if ($client) {
        $profiles = $client->query(
            new RouterOS\Query('/ip/hotspot/profile/print')
        )->read();
    }
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
                        <div class="container-xl px-5"><h1 class="text-white py-3 mb-0 display-6">Blank Page</h1></div>
                    </header>
<form method="POST" action="../plans/store">

    <label>Router</label>
    <select name="router_id" class="form-select" onchange="location='?router_id='+this.value" required>
        <option value="">-- Select Router --</option>
        <?php foreach ($routers as $r): ?>
            <option value="<?= $r['router_id'] ?>" <?= $router_id==$r['router_id']?'selected':'' ?>>
                <?= $r['name'] ?>
            </option>
        <?php endforeach ?>
    </select>

    <?php if ($profiles): ?>
        <label class="mt-3">Hotspot Profile</label>
        <select name="hotspot_profile" class="form-select" required>
            <?php foreach ($profiles as $p): ?>
                <option value="<?= $p['name'] ?>"><?= $p['name'] ?></option>
            <?php endforeach ?>
        </select>
    <?php endif ?>

    <label class="mt-3">Plan Name</label>
    <input name="name" class="form-control" required>

    <label class="mt-3">Price</label>
    <input name="price" type="number" step="0.01" class="form-control" required>

    <label class="mt-3">Validity (Days)</label>
    <input name="validity_days" type="number" class="form-control">

    <label class="mt-3">Validity (Hours)</label>
    <input name="validity_hours" type="number" class="form-control">

    <button class="btn btn-primary mt-4">Save Plan</button>
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

