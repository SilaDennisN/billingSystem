<?php
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}
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
        <h1 class="text-white py-3 mb-0 display-6">Add Router</h1>
    </div>
</header>

<div class="container-xl px-1 mt-4">

    <div class="card">
        <div class="card-body">

            <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="store">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Router Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Host / IP Address</label>
                        <input type="text" name="host" class="form-control" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">API Port</label>
                        <input type="number" name="api_port" class="form-control" value="8728">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">API Username</label>
                        <input type="text" name="api_user" class="form-control" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">API Password</label>
                        <input type="password" name="api_pass" class="form-control" required>
                    </div>
                </div>

                <div class="mt-3 d-flex justify-content-end">
                    <a href="/routers" class="btn btn-light me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        Save & Test Connection
                    </button>
                </div>

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
