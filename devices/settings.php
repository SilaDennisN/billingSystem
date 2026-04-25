<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../dashboard");
    exit;
}

// Load settings (example table: system_settings)
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

<header class="bg-primary">
    <div class="container-xl px-1">
        <div class="d-flex align-items-center justify-content-between py-4">
            <div>
                <h1 class="text-white mb-1 display-6">System Settings</h1>
                <p class="text-white-50 mb-0">Global application configuration</p>
            </div>
            <i class="material-icons text-white-50" style="font-size:3rem;">settings</i>
        </div>
    </div>
</header>

<div class="container-xl px-1 mt-n4">

<div class="card shadow border-0">
<div class="card-header bg-white">
    <h5 class="mb-0">General Settings</h5>
    <small class="text-muted">Affects the entire system</small>
</div>

<form method="post" action="save_settings.php">
<div class="card-body">

<div class="row g-3">

    <div class="col-md-6">
        <label class="form-label">System Name</label>
        <input type="text" name="system_name" class="form-control"
               value="<?= htmlspecialchars($settings['system_name'] ?? 'InovaTech Billing') ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label">Timezone</label>
        <select name="timezone" class="form-select">
            <?php foreach (timezone_identifiers_list() as $tz): ?>
                <option value="<?= $tz ?>" <?= ($settings['timezone'] ?? '') === $tz ? 'selected' : '' ?>>
                    <?= $tz ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Default Language</label>
        <select name="language" class="form-select">
            <option value="en">English</option>
            <option value="fr">French</option>
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Maintenance Mode</label>
        <select name="maintenance" class="form-select">
            <option value="off">Disabled</option>
            <option value="on" <?= ($settings['maintenance'] ?? '') === 'on' ? 'selected' : '' ?>>
                Enabled
            </option>
        </select>
    </div>

</div>

</div>

<div class="card-footer bg-white text-end">
    <button class="btn btn-primary">
        <i class="material-icons me-1">save</i>
        Save Settings
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
