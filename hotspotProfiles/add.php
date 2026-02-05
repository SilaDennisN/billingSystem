<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ..//auth/login");
    exit;
}

$routers = $pdo->query("SELECT router_id, name FROM routers WHERE status='active'")->fetchAll();
?>

<?php require_once "../partials/head.php" ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php" ?>
        <div id="layoutDrawer_content">
            <main>

                <header class="bg-dark">
                    <div class="container-xl px-1">
                        <h1 class="text-white py-3 mb-0 display-6">Add Hotspot Profile</h1>
                    </div>
                </header>

                <div class="container-xl px-1 mt-4">
                    <div class="card">
                        <div class="card-body">

                            <form method="POST" action="store.php">

                                <label>Router</label>
                                <select name="router_id" class="form-select" required>
                                    <?php foreach ($routers as $r): ?>
                                        <option value="<?= $r['router_id'] ?>"><?= $r['name'] ?></option>
                                    <?php endforeach ?>
                                </select>

                                <label class="mt-3">Profile Name</label>
                                <input name="profile_name" class="form-control" required>

                                <label class="mt-3">Rate Limit (e.g. 5M/5M)</label>
                                <input name="rate_limit" class="form-control">

                                <label class="mt-3">Shared Users</label>
                                <input name="shared_users" type="number" value="1" class="form-control">

                                <button class="btn btn-primary mt-4">Create Profile</button>

                            </form>

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