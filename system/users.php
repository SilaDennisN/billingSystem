<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../dashboard");
    exit;
}

$users = $pdo->query("
    SELECT user_id, username, email, role, status, last_login, created_at
    FROM users
    ORDER BY created_at DESC
")->fetchAll();
?>
<?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
<div class="alert alert-success alert-dismissible fade show mx-5 mt-3" role="alert">
    <div class="d-flex align-items-center">
        <i class="material-icons me-2">check_circle</i>
        <div>User password reset to default successfully.</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

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
                                <h1 class="text-white mb-1 display-6">System Users</h1>
                                <p class="text-white-50 mb-0">Manage user accounts and permissions</p>
                            </div>
                            <div>
                                <i class="material-icons text-white-50" style="font-size: 3rem;">people</i>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-n4">

                    <!-- Stats Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="text-muted small mb-1">Total Users</div>
                                            <div class="h3 mb-0"><?= count($users) ?></div>
                                        </div>
                                        <div class="text-primary">
                                            <i class="material-icons" style="font-size: 2.5rem;">group</i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="text-muted small mb-1">Active Users</div>
                                            <div class="h3 mb-0"><?= count(array_filter($users, fn($u) => $u['status'] == 'active')) ?></div>
                                        </div>
                                        <div class="text-success">
                                            <i class="material-icons" style="font-size: 2.5rem;">check_circle</i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="text-muted small mb-1">Administrators</div>
                                            <div class="h3 mb-0"><?= count(array_filter($users, fn($u) => $u['role'] == 'admin')) ?></div>
                                        </div>
                                        <div class="text-warning">
                                            <i class="material-icons" style="font-size: 2.5rem;">admin_panel_settings</i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="text-muted small mb-1">Inactive Users</div>
                                            <div class="h3 mb-0"><?= count(array_filter($users, fn($u) => $u['status'] != 'active')) ?></div>
                                        </div>
                                        <div class="text-danger">
                                            <i class="material-icons" style="font-size: 2.5rem;">block</i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Table Card -->
                    <div class="card shadow border-0">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                            <div>
                                <h5 class="card-title mb-0">All Users</h5>
                                <small class="text-muted">View and manage system users</small>
                            </div>
                            <button class="btn btn-primary btn-sm lift" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="material-icons me-1" style="font-size: 1rem; vertical-align: middle;">add</i>
                                Add User
                            </button>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0">
                                                <div class="d-flex align-items-center">
                                                    <i class="material-icons me-2 text-muted" style="font-size: 1.2rem;">person</i>
                                                    Username
                                                </div>
                                            </th>
                                            <th class="border-0">
                                                <div class="d-flex align-items-center">
                                                    <i class="material-icons me-2 text-muted" style="font-size: 1.2rem;">email</i>
                                                    Email
                                                </div>
                                            </th>
                                            <th class="border-0">
                                                <div class="d-flex align-items-center">
                                                    <i class="material-icons me-2 text-muted" style="font-size: 1.2rem;">verified_user</i>
                                                    Role
                                                </div>
                                            </th>
                                            <th class="border-0">
                                                <div class="d-flex align-items-center">
                                                    <i class="material-icons me-2 text-muted" style="font-size: 1.2rem;">circle</i>
                                                    Status
                                                </div>
                                            </th>
                                            <th class="border-0">
                                                <div class="d-flex align-items-center">
                                                    <i class="material-icons me-2 text-muted" style="font-size: 1.2rem;">schedule</i>
                                                    Last Login
                                                </div>
                                            </th>
                                            <th class="border-0 text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <?php foreach ($users as $u): ?>

                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2">
                                                            <div class="avatar-title bg-<?= $u['role'] == 'admin' ? 'primary' : 'secondary' ?>-soft text-<?= $u['role'] == 'admin' ? 'primary' : 'secondary' ?> rounded-circle">
                                                                <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="fw-500"><?= htmlspecialchars($u['username']) ?></div>
                                                            <?php if ($u['user_id'] == $_SESSION['user']['id']): ?>
                                                                <small class="text-muted">(You)</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-muted">
                                                        <?= htmlspecialchars($u['email'] ?? '-') ?>
                                                    </div>
                                                </td>

                                                <td>
                                                    <span class="badge rounded-pill bg-<?= $u['role'] == 'admin' ? 'primary' : 'secondary' ?>-soft text-<?= $u['role'] == 'admin' ? 'primary' : 'secondary' ?>">
                                                        <i class="material-icons me-1" style="font-size: 0.875rem; vertical-align: middle;">
                                                            <?= $u['role'] == 'admin' ? 'admin_panel_settings' : 'person' ?>
                                                        </i>
                                                        <?= ucfirst($u['role']) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <span class="badge rounded-pill bg-<?= $u['status'] == 'active' ? 'success' : 'danger' ?>-soft text-<?= $u['status'] == 'active' ? 'success' : 'danger' ?>">
                                                        <i class="material-icons me-1" style="font-size: 0.875rem; vertical-align: middle;">
                                                            <?= $u['status'] == 'active' ? 'check_circle' : 'cancel' ?>
                                                        </i>
                                                        <?= ucfirst($u['status']) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <div class="small">
                                                        <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : '<span class="text-muted">Never</span>' ?>
                                                    </div>
                                                </td>

                                                <td class="text-end">

                                                    <?php if ($u['user_id'] != $_SESSION['user']['id']): ?>

                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-text dropdown-toggle" data-bs-toggle="dropdown">
                                                                <i class="material-icons">more_vert</i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                                                                <li>
                                                                    <a href="#" class="dropdown-item edit-user"
                                                                        data-id="<?= $u['user_id'] ?>"
                                                                        data-username="<?= $u['username'] ?>"
                                                                        data-email="<?= $u['email'] ?>"
                                                                        data-role="<?= $u['role'] ?>"
                                                                        data-status="<?= $u['status'] ?>">
                                                                        <i class="material-icons me-2">edit</i>
                                                                        Edit User
                                                                    </a>
                                                                </li>

                                                                <li>
                                                                    <a href="resetPassword.php?id=<?= $u['user_id'] ?>"
                                                                        class="dropdown-item"
                                                                        onclick="return confirm('Reset password for this user?')">
                                                                        <i class="material-icons me-2 text-warning">lock_reset</i>
                                                                        Reset Password
                                                                    </a>
                                                                </li>

                                                                <li>
                                                                    <hr class="dropdown-divider">
                                                                </li>

                                                                <li>
                                                                    <a href="toggle.php?id=<?= $u['user_id'] ?>"
                                                                        class="dropdown-item"
                                                                        onclick="return confirm('Change user status?')">
                                                                        <i class="material-icons me-2 text-<?= $u['status'] == 'active' ? 'danger' : 'success' ?>">
                                                                            <?= $u['status'] == 'active' ? 'block' : 'check_circle' ?>
                                                                        </i>
                                                                        <?= $u['status'] == 'active' ? 'Disable User' : 'Enable User' ?>
                                                                    </a>
                                                                </li>

                                                            </ul>
                                                        </div>

                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark border">
                                                            <i class="material-icons" style="font-size: 0.875rem; vertical-align: middle;">person</i>
                                                            Current User
                                                        </span>
                                                    <?php endif; ?>

                                                </td>

                                            </tr>
                                        <?php endforeach; ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-white small text-muted py-3">
                            Showing <?= count($users) ?> user<?= count($users) != 1 ? 's' : '' ?> • Last updated: <?= date('d M Y H:i') ?>
                        </div>
                    </div>

                </div>
            </main>
            <?php require_once "modals.php"; ?>
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>
</body>

</html>