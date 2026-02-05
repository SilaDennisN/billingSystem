<?php
require_once "../partials/head.php";
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/expiry_engine.php";

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    run_expiry_engine($pdo);
    $ran = true;
}
?>
<body class="nav-fixed bg-light">

<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php"; ?>

<div id="layoutDrawer_content">
<main>

<header class="bg-dark">
    <div class="container-xl px-5">
        <h1 class="text-white py-3 mb-0 display-6">Expiry Engine</h1>
    </div>
</header>

<div class="container-xl px-5 mt-4">

<div class="card card-raised shadow-sm">
<div class="card-body">

<?php if ($ran): ?>
<div class="alert alert-success">
    Expiry engine executed successfully.
</div>
<?php endif; ?>

<p class="mb-4">
This will immediately disconnect and disable all expired hotspot users
across all routers.
</p>

<form method="POST">
    <button class="btn btn-danger">
        Run Expiry Engine Now
    </button>
</form>

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
