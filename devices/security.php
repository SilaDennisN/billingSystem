<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../dashboard");
    exit;
}

$settings = $pdo->query("
    SELECT setting_key, setting_value 
    FROM system_settings
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<?php require_once "../partials/head.php"; ?>
<body class="nav-fixed bg-light">

<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php"; ?>

<div id="layoutDrawer_content">
<main>

<header class="bg-danger">
    <div class="container-xl px-1">
        <div class="d-flex align-items-center justify-content-between py-4">
            <div>
                <h1 class="text-white mb-1 display-6">Security Settings</h1>
                <p class="text-white-50 mb-0">Authentication & protection rules</p>
            </div>
            <i class="material-icons text-white-50" style="font-size:3rem;">security</i>
        </div>
    </div>
</header>

<div class="container-xl px-1 mt-n4">

<div class="card shadow border-0">

<form method="post" action="save_security.php">

<div class="card-body">

<div class="row g-3">

    <div class="col-md-6">
        <label class="form-label">Minimum Password Length</label>
        <input type="number" name="min_password_length"
               class="form-control"
               value="<?= $settings['min_password_length'] ?? 8 ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label">Session Timeout (minutes)</label>
        <input type="number" name="session_timeout"
               class="form-control"
               value="<?= $settings['session_timeout'] ?? 30 ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label">Two-Factor Authentication</label>
        <select name="2fa" class="form-select">
            <option value="off">Disabled</option>
            <option value="on" <?= ($settings['2fa'] ?? '') === 'on' ? 'selected' : '' ?>>
                Enabled
            </option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Max Login Attempts</label>
        <input type="number" name="max_login_attempts"
               class="form-control"
               value="<?= $settings['max_login_attempts'] ?? 5 ?>">
    </div>

</div>

</div>

<div class="card-footer bg-white text-end">
    <button class="btn btn-danger">
        <i class="material-icons me-1">shield</i>
        Save Security Settings
    </button>
</div>

</form>

</div>
</div>
</main>

<?php require_once "../partials/footer.php"; ?>
</div>
</div>

<?php require_once "../partials/scripts.php"; ?>
</body>
</html>
