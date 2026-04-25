<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);


$me = (int)$_SESSION['user']['id'];

/* ── Get routers assigned to the logged-in admin ── */
$myRouterStmt = $pdo->prepare("
    SELECT router_id FROM user_router_access WHERE user_id = ?
");
$myRouterStmt->execute([$me]);
$myRouterIds = $myRouterStmt->fetchAll(PDO::FETCH_COLUMN);

/* ── Fetch users who share at least one router with me, excluding myself ── */
if (!empty($myRouterIds)) {
    $ph = implode(',', array_fill(0, count($myRouterIds), '?'));
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.username, u.email, u.role, u.status, u.last_login, u.created_at
        FROM users u
        INNER JOIN user_router_access ura ON ura.user_id = u.user_id
        WHERE ura.router_id IN ($ph)
        ORDER BY u.created_at DESC
    ");
    $stmt->execute($myRouterIds);
} else {
    /* Admin has no routers — show nobody except self */
    $stmt = $pdo->prepare("SELECT user_id, username, email, role, status, last_login, created_at FROM users WHERE user_id = ?");
    $stmt->execute([$me]);
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Fetch all active routers (for the assign-routers modal) ── */
$allRouters = $pdo->query("SELECT router_id, name FROM routers WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

/* ── Per-user router assignments (for display & edit pre-fill) ── */
$assignStmt = $pdo->query("SELECT user_id, router_id FROM user_router_access");
$allAssignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);
$assignMap = [];
foreach ($allAssignments as $a) {
    $assignMap[$a['user_id']][] = (int)$a['router_id'];
}
?>
<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show mx-5 mt-3" role="alert">
    <i class="fa fa-check-circle me-2"></i>
    <?php
    $msgs = [
        'created' => 'User created successfully.',
        'updated' => 'User updated successfully.',
        'deleted' => 'User deleted.',
        'reset'   => 'Password reset to default successfully.',
        'toggled' => 'User status updated.',
    ];
    echo $msgs[$_GET['success']] ?? 'Action completed.';
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php require_once "../partials/head.php"; ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Header -->
                <header style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 60%,#0f3460 100%);border-bottom:3px solid #3b82f6;">
                    <div class="container-xl px-5 py-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:46px;height:46px;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.4);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fa fa-users" style="color:#60a5fa;font-size:1.2rem;"></i>
                                </div>
                                <div>
                                    <h1 class="text-white mb-0" style="font-size:1.4rem;font-weight:700;">System Users</h1>
                                    <p class="mb-0" style="color:rgba(255,255,255,0.5);font-size:0.78rem;">Users sharing your router assignments</p>
                                </div>
                            </div>
                            <button class="btn btn-sm px-3 py-2"
                                style="background:rgba(59,130,246,0.2);color:#93c5fd;border:1px solid rgba(59,130,246,0.35);border-radius:8px;font-size:0.82rem;font-weight:600;"
                                data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fa fa-plus me-2"></i>Add User
                            </button>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 py-4">

                    <!-- Stat cards -->
                    <div class="row g-3 mb-4">
                        <?php
                        $total    = count($users);
                        $active   = count(array_filter($users, fn($u) => $u['status'] === 'active'));
                        $admins   = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
                        $inactive = $total - $active;
                        $stats = [
                            ['label'=>'Total Users',   'val'=>$total,    'icon'=>'fa-users',               'bg'=>'#eff6ff','ic'=>'#3b82f6'],
                            ['label'=>'Active',        'val'=>$active,   'icon'=>'fa-circle-check',        'bg'=>'#f0fdf4','ic'=>'#22c55e'],
                            ['label'=>'Admins',        'val'=>$admins,   'icon'=>'fa-user-shield',         'bg'=>'#fffbeb','ic'=>'#f59e0b'],
                            ['label'=>'Inactive',      'val'=>$inactive, 'icon'=>'fa-ban',                 'bg'=>'#fef2f2','ic'=>'#ef4444'],
                        ];
                        foreach ($stats as $s): ?>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.7px;color:#94a3b8;"><?= $s['label'] ?></span>
                                        <div style="width:32px;height:32px;border-radius:8px;background:<?= $s['bg'] ?>;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa <?= $s['icon'] ?>" style="color:<?= $s['ic'] ?>;font-size:0.8rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><?= $s['val'] ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Table card -->
                    <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);overflow:hidden;">

                        <div class="card-header d-flex align-items-center justify-content-between py-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                            <span style="font-weight:700;color:#1e293b;font-size:0.9rem;">
                                <i class="fa fa-list me-2" style="color:#94a3b8;"></i>User List
                            </span>
                            <span style="font-size:0.75rem;color:#94a3b8;">
                                <?= $total ?> user<?= $total != 1 ? 's' : '' ?> · Updated <?= date('d M Y H:i') ?>
                            </span>
                        </div>

                        <div class="card-body p-0">
                            <?php if (!empty($users)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size:0.83rem;">
                                    <thead style="background:#f8fafc;">
                                        <tr style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.6px;color:#94a3b8;">
                                            <th class="px-4 py-3">User</th>
                                            <th class="py-3">Email</th>
                                            <th class="py-3">Role</th>
                                            <th class="py-3">Status</th>
                                            <th class="py-3">Routers</th>
                                            <th class="py-3">Last Login</th>
                                            <th class="py-3 text-end px-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $u): ?>
                                        <?php $uRouters = $assignMap[$u['user_id']] ?? []; ?>
                                        <tr style="border-bottom:1px solid #f1f5f9;">
                                            <td class="px-4 py-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="width:34px;height:34px;border-radius:50%;background:<?= $u['role']==='admin' ? '#eff6ff' : '#f8fafc' ?>;border:1px solid <?= $u['role']==='admin' ? '#bfdbfe' : '#e2e8f0' ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;color:<?= $u['role']==='admin' ? '#3b82f6' : '#64748b' ?>;flex-shrink:0;">
                                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($u['username']) ?></div>
                                                        <?php if ($u['user_id'] == $me): ?>
                                                        <div style="font-size:0.7rem;color:#94a3b8;">You</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="color:#64748b;"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                                            <td>
                                                <span style="background:<?= $u['role']==='admin'?'#eff6ff':'#f8fafc' ?>;color:<?= $u['role']==='admin'?'#1d4ed8':'#475569' ?>;font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px;">
                                                    <?= ucfirst($u['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="background:<?= $u['status']==='active'?'#f0fdf4':'#fef2f2' ?>;color:<?= $u['status']==='active'?'#15803d':'#dc2626' ?>;font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:20px;display:inline-flex;align-items:center;gap:4px;">
                                                    <span style="width:6px;height:6px;border-radius:50%;background:<?= $u['status']==='active'?'#22c55e':'#ef4444' ?>;"></span>
                                                    <?= ucfirst($u['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($uRouters)): ?>
                                                    <span style="background:#f0f9ff;color:#0369a1;font-size:0.72rem;font-weight:600;padding:3px 9px;border-radius:20px;">
                                                        <i class="fa fa-server me-1" style="font-size:0.65rem;"></i><?= count($uRouters) ?> router<?= count($uRouters)!=1?'s':'' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#94a3b8;font-size:0.75rem;">None assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color:#64748b;font-size:0.78rem;">
                                                <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : '<span style="color:#94a3b8;">Never</span>' ?>
                                            </td>
                                            <td class="text-end px-4">
                                                <?php if ($u['user_id'] != $me): ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm dropdown-toggle"
                                                        style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;color:#64748b;font-size:0.78rem;padding:4px 10px;"
                                                        data-bs-toggle="dropdown">
                                                        <i class="fa fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border-radius:10px;border:1px solid #e2e8f0;font-size:0.82rem;min-width:170px;">
                                                        <li>
                                                            <a href="#" class="dropdown-item py-2 edit-user"
                                                                data-id="<?= $u['user_id'] ?>"
                                                                data-username="<?= htmlspecialchars($u['username']) ?>"
                                                                data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
                                                                data-role="<?= $u['role'] ?>"
                                                                data-status="<?= $u['status'] ?>"
                                                                data-routers='<?= json_encode($uRouters) ?>'>
                                                                <i class="fa fa-edit me-2 text-primary"></i>Edit User
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="#" class="dropdown-item py-2 assign-routers"
                                                                data-id="<?= $u['user_id'] ?>"
                                                                data-username="<?= htmlspecialchars($u['username']) ?>"
                                                                data-routers='<?= json_encode($uRouters) ?>'>
                                                                <i class="fa fa-server me-2 text-info"></i>Assign Routers
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="#" class="dropdown-item py-2 reset-password"
                                                                data-id="<?= $u['user_id'] ?>"
                                                                data-username="<?= htmlspecialchars($u['username']) ?>">
                                                                <i class="fa fa-lock-open me-2 text-warning"></i>Reset Password
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider my-1"></li>
                                                        <li>
                                                            <a href="#" class="dropdown-item py-2 toggle-status"
                                                                data-id="<?= $u['user_id'] ?>"
                                                                data-username="<?= htmlspecialchars($u['username']) ?>"
                                                                data-status="<?= $u['status'] ?>">
                                                                <i class="fa fa-<?= $u['status']==='active'?'ban':'circle-check' ?> me-2 text-<?= $u['status']==='active'?'danger':'success' ?>"></i>
                                                                <?= $u['status']==='active' ? 'Disable' : 'Enable' ?> User
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="#" class="dropdown-item py-2 delete-user"
                                                                data-id="<?= $u['user_id'] ?>"
                                                                data-username="<?= htmlspecialchars($u['username']) ?>">
                                                                <i class="fa fa-trash me-2 text-danger"></i>Delete User
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <?php else: ?>
                                                <span style="background:#f1f5f9;color:#94a3b8;font-size:0.72rem;padding:4px 10px;border-radius:20px;border:1px solid #e2e8f0;">
                                                    <i class="fa fa-user me-1"></i>You
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <div style="width:56px;height:56px;background:#f1f5f9;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                                    <i class="fa fa-users" style="color:#94a3b8;font-size:1.4rem;"></i>
                                </div>
                                <p style="color:#64748b;font-size:0.88rem;margin-bottom:12px;">No users share your router assignments yet.</p>
                                <button class="btn btn-sm px-4" style="background:#3b82f6;color:#fff;border:none;border-radius:9px;font-weight:600;"
                                    data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fa fa-plus me-1"></i>Add First User
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </main>

            <?php require_once "modals.php"; ?>
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        function set(id, val) {
            const el = document.getElementById(id);
            if (el) el.value = val;
        }

        /* ── Edit User ── */
        document.querySelectorAll('.edit-user').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                set('edit-id',       btn.dataset.id);
                set('edit-username', btn.dataset.username);
                set('edit-email',    btn.dataset.email);
                set('edit-role',     btn.dataset.role);
                set('edit-status',   btn.dataset.status);
                new bootstrap.Modal('#editUserModal').show();
            });
        });

        /* ── Assign Routers ── */
        document.querySelectorAll('.assign-routers').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                set('assign-user-id', btn.dataset.id);
                document.getElementById('assignRouterTitle').textContent = btn.dataset.username;
                const assigned = JSON.parse(btn.dataset.routers || '[]');
                document.querySelectorAll('.router-checkbox').forEach(cb => {
                    cb.checked = assigned.includes(parseInt(cb.value));
                });
                new bootstrap.Modal('#assignRoutersModal').show();
            });
        });

        /* ── Reset Password ── */
        document.querySelectorAll('.reset-password').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                set('reset-id', btn.dataset.id);
                document.getElementById('resetUsername').textContent = btn.dataset.username;
                new bootstrap.Modal('#resetPasswordModal').show();
            });
        });

        /* ── Toggle Status ── */
        document.querySelectorAll('.toggle-status').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                set('toggle-id',     btn.dataset.id);
                set('toggle-status', btn.dataset.status);
                const action = btn.dataset.status === 'active' ? 'disable' : 'enable';
                document.getElementById('toggleUsername').textContent = btn.dataset.username;
                document.getElementById('toggleAction').textContent   = action;
                document.getElementById('toggleActionBtn').textContent = ucfirst(action) + ' User';
                document.getElementById('toggleActionBtn').className =
                    'btn ' + (action === 'disable' ? 'btn-danger' : 'btn-success');
                new bootstrap.Modal('#toggleStatusModal').show();
            });
        });

        /* ── Delete User ── */
        document.querySelectorAll('.delete-user').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                set('delete-id', btn.dataset.id);
                document.getElementById('deleteUsername').textContent = btn.dataset.username;
                new bootstrap.Modal('#deleteUserModal').show();
            });
        });

        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    });
    </script>

</body>
</html>