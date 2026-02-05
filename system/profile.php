<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

$user_id = $_SESSION['user']['id'];

/* Load current user */
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found");
}
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
                        <div class="d-flex align-items-center justify-content-between py-4">
                            <div>
                                <h1 class="text-white mb-1 display-6">My Profile</h1>
                                <p class="text-white-50 mb-0">Manage your account settings and preferences</p>
                            </div>
                            <div>
                                <i class="material-icons text-white-50" style="font-size: 3rem;">account_circle</i>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-n4">

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="material-icons me-2">check_circle</i>
                                <div>Profile updated successfully</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="material-icons me-2">error</i>
                                <div><?= htmlspecialchars($_GET['error']) ?></div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Header Card -->
                    <div class="card shadow border-0 mb-4">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="avatar avatar-xxl">
                                        <div class="avatar-title bg-primary-soft text-primary rounded-circle" style="font-size: 2.5rem;">
                                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col">
                                    <h3 class="mb-1"><?= htmlspecialchars($user['username']) ?></h3>
                                    <p class="text-muted mb-2">
                                        <i class="material-icons align-middle me-1" style="font-size: 1.2rem;">email</i>
                                        <?= htmlspecialchars($user['email']) ?>
                                    </p>
                                    <div>
                                        <span class="badge rounded-pill bg-<?= $user['role'] == 'admin' ? 'primary' : 'secondary' ?>-soft text-<?= $user['role'] == 'admin' ? 'primary' : 'secondary' ?> me-2">
                                            <i class="material-icons me-1" style="font-size: 0.875rem; vertical-align: middle;">
                                                <?= $user['role'] == 'admin' ? 'admin_panel_settings' : 'person' ?>
                                            </i>
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                        <span class="badge rounded-pill bg-<?= $user['status'] == 'active' ? 'success' : 'danger' ?>-soft text-<?= $user['status'] == 'active' ? 'success' : 'danger' ?>">
                                            <i class="material-icons me-1" style="font-size: 0.875rem; vertical-align: middle;">
                                                <?= $user['status'] == 'active' ? 'check_circle' : 'cancel' ?>
                                            </i>
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="text-end">
                                        <div class="small text-muted mb-1">Member Since</div>
                                        <div class="fw-500"><?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                                        <?php if ($user['last_login']): ?>
                                            <div class="small text-muted mt-2">Last Login</div>
                                            <div class="small"><?= date('M d, Y H:i', strtotime($user['last_login'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">

                        <!-- PROFILE DETAILS -->
                        <div class="col-lg-6">
                            <div class="card shadow border-0 h-100">
                                <div class="card-header bg-white border-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="material-icons text-primary me-2">person</i>
                                        <div>
                                            <h5 class="mb-0">Profile Information</h5>
                                            <small class="text-muted">Update your account details</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-4">

                                    <form method="post" action="update_profile.php">
                                        <div class="mb-4">
                                            <label class="form-label small fw-500 text-muted mb-2">
                                                <i class="material-icons align-middle me-1" style="font-size: 1rem;">badge</i>
                                                Username
                                            </label>
                                            <input type="text" name="username" class="form-control form-control-lg"
                                                value="<?= htmlspecialchars($user['username']) ?>" required>
                                            <div class="form-text">This is your unique identifier in the system</div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label small fw-500 text-muted mb-2">
                                                <i class="material-icons align-middle me-1" style="font-size: 1rem;">email</i>
                                                Email Address
                                            </label>
                                            <input type="email" name="email" class="form-control form-control-lg"
                                                value="<?= htmlspecialchars($user['email']) ?>">
                                            <div class="form-text">We'll never share your email with anyone else</div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label small fw-500 text-muted mb-2">
                                                <i class="material-icons align-middle me-1" style="font-size: 1rem;">verified_user</i>
                                                Account Role
                                            </label>
                                            <div class="input-group input-group-lg">
                                                <span class="input-group-text bg-light border-end-0">
                                                    <i class="material-icons text-muted">
                                                        <?= $user['role'] == 'admin' ? 'admin_panel_settings' : 'person' ?>
                                                    </i>
                                                </span>
                                                <input type="text" class="form-control border-start-0"
                                                    value="<?= ucfirst($user['role']) ?>" disabled>
                                            </div>
                                            <div class="form-text">Your role determines your system permissions</div>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary btn-lg lift">
                                                <i class="material-icons me-2" style="font-size: 1.2rem; vertical-align: middle;">save</i>
                                                Save Changes
                                            </button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>

                        <!-- PASSWORD CHANGE -->
                        <div class="col-lg-6">
                            <div class="card shadow border-0 h-100">
                                <div class="card-header bg-white border-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <i class="material-icons text-warning me-2">lock</i>
                                        <div>
                                            <h5 class="mb-0">Security Settings</h5>
                                            <small class="text-muted">Change your account password</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-4">

                                    <form method="post" action="change_password.php">
                                        <div class="mb-4">
                                            <label class="form-label small fw-500 text-muted mb-2">
                                                <i class="material-icons align-middle me-1" style="font-size: 1rem;">lock_open</i>
                                                Current Password
                                            </label>
                                            <div class="input-group input-group-lg">
                                                <span class="input-group-text bg-light border-end-0">
                                                    <i class="material-icons text-muted">vpn_key</i>
                                                </span>
                                                <input type="password" name="current_password" class="form-control border-start-0"
                                                    placeholder="Enter current password" required>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label small fw-500 text-muted mb-2">
                                                <i class="material-icons align-middle me-1" style="font-size: 1rem;">lock</i>
                                                New Password
                                            </label>
                                            <div class="input-group input-group-lg">
                                                <span class="input-group-text bg-light border-end-0">
                                                    <i class="material-icons text-muted">vpn_key</i>
                                                </span>
                                                <input type="password" name="new_password" class="form-control border-start-0"
                                                    placeholder="Enter new password" required>
                                            </div>
                                            <div class="form-text">Must be at least 8 characters long</div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label small fw-500 text-muted mb-2">
                                                <i class="material-icons align-middle me-1" style="font-size: 1rem;">check_circle</i>
                                                Confirm New Password
                                            </label>
                                            <div class="input-group input-group-lg">
                                                <span class="input-group-text bg-light border-end-0">
                                                    <i class="material-icons text-muted">vpn_key</i>
                                                </span>
                                                <input type="password" name="confirm_password" class="form-control border-start-0"
                                                    placeholder="Confirm new password" required>
                                            </div>
                                        </div>

                                        <div class="alert alert-warning-soft border-0 mb-4" role="alert">
                                            <div class="d-flex">
                                                <i class="material-icons me-2">info</i>
                                                <div class="small">
                                                    Make sure your password is strong and unique. Don't share it with anyone.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-warning btn-lg lift">
                                                <i class="material-icons me-2" style="font-size: 1.2rem; vertical-align: middle;">lock_reset</i>
                                                Update Password
                                            </button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Account Stats -->
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <div class="card shadow-sm border-0">
                                <div class="card-body text-center p-4">
                                    <i class="material-icons text-primary mb-2" style="font-size: 2.5rem;">calendar_today</i>
                                    <div class="h6 text-muted mb-1">Account Age</div>
                                    <div class="h4 mb-0">
                                        <?php
                                        $diff = date_diff(date_create($user['created_at']), date_create('now'));
                                        echo $diff->days . ' days';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow-sm border-0">
                                <div class="card-body text-center p-4">
                                    <i class="material-icons text-success mb-2" style="font-size: 2.5rem;">verified</i>
                                    <div class="h6 text-muted mb-1">Account Status</div>
                                    <div class="h4 mb-0"><?= ucfirst($user['status']) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow-sm border-0">
                                <div class="card-body text-center p-4">
                                    <i class="material-icons text-info mb-2" style="font-size: 2.5rem;">shield</i>
                                    <div class="h6 text-muted mb-1">Security Level</div>
                                    <div class="h4 mb-0">Standard</div>
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
</body>

</html>