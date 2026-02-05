<?php
require_once "../partials/head.php";
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/router.php";

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

/* Load users (hotspot + pppoe) */
$users = $pdo->query("
    SELECT hu.*, r.name AS router_name, hp.profile_name AS plan_name
    FROM hotspot_users hu
    JOIN routers r ON r.router_id = hu.router_id
    LEFT JOIN hotspot_profiles hp ON hp.id = hu.plan_id
    ORDER BY hu.user_type, hu.username
")->fetchAll();

/* Load captive portal sessions */
$sessions = $pdo->query("
    SELECT hs.*, r.name AS router_name, hp.profile_name AS plan_name
    FROM hotspot_sessions hs
    JOIN routers r ON r.router_id = hs.router_id
    LEFT JOIN hotspot_profiles hp ON hp.id = hs.plan_id
")->fetchAll();

/* Load live users */
$liveHotspot = [];
$livePPPoE   = [];

$routers = $pdo->query("SELECT * FROM routers WHERE status='active'")->fetchAll();



foreach ($routers as $router) {
    try {
        $client = router_connect($router['router_id']);
        if (!$client) continue;

        /* Hotspot active */
        foreach ($client->query('/ip/hotspot/active/print')->read() as $a) {
            $liveHotspot[$a['user']] = true;
        }

        /* PPPoE active */
        foreach ($client->query('/ppp/active/print')->read() as $p) {
            $livePPPoE[$p['name']] = true;
        }
    } catch (Exception $e) {
    }
}

/* Load plans for modal */
$plans = $pdo->query("SELECT * FROM hotspot_profiles WHERE plan_type ='pppoe'")->fetchAll();
$router_id = $routers[0]['router_id'] ?? null;

/* Calculate stats */
$totalUsers = count($users);
$onlineUsers = 0;
$activeUsers = 0;
$pppoeCount = 0;
$hotspotCount = 0;
$captiveCount = count($sessions);

foreach ($users as $u) {
    if ($u['status'] === 'active') $activeUsers++;
    if ($u['user_type'] === 'pppoe') $pppoeCount++;
    else $hotspotCount++;
    
    $online = $u['user_type'] === 'pppoe'
        ? isset($livePPPoE[$u['username']])
        : isset($liveHotspot[$u['username']]);
    if ($online) $onlineUsers++;
}
?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Enhanced Header -->
                <header class="bg-primary">
                    <div class="container-xl px-1">
                        <div class="d-flex justify-content-between align-items-center py-3">
                            <h1 class="text-white mb-0 display-6">
                                <i class="fas fa-users me-2"></i>Network Users
                            </h1>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success-soft text-success">
                                    <i class="fa fa-circle me-1"></i><?= $onlineUsers ?> Online
                                </span>
                                <span class="badge bg-secondary-soft text-secondary">
                                    <i class="fa fa-circle me-1"></i><?= $totalUsers - $onlineUsers ?> Offline
                                </span>
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-plus me-1"></i>Add User
                                </button>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-4">

                    <!-- Stats Cards Row -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-primary border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Users</div>
                                            <div class="h3 mb-0"><?= $totalUsers ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-users fa-2x text-primary opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-success border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Online Now</div>
                                            <div class="h3 mb-0"><?= $onlineUsers ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-wifi fa-2x text-success opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-info border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">PPPoE Users</div>
                                            <div class="h3 mb-0"><?= $pppoeCount ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-network-wired fa-2x text-info opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-warning border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Hotspot Users</div>
                                            <div class="h3 mb-0"><?= $hotspotCount ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-rss fa-2x text-warning opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs for different user types -->
                    <div class="card card-raised shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-list me-2"></i>User Management
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-light btn-sm active" data-filter="all">
                                        All Users
                                    </button>
                                    <button class="btn btn-light btn-sm" data-filter="pppoe">
                                        PPPoE
                                    </button>
                                    <button class="btn btn-light btn-sm" data-filter="hotspot">
                                        Hotspot
                                    </button>
                                    <button class="btn btn-light btn-sm" data-filter="captive">
                                        Captive Portal
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="datatablesSimple" class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i>User / MAC</th>
                                            <th><i class="fas fa-tag me-1"></i>Type</th>
                                            <th><i class="fas fa-server me-1"></i>Router</th>
                                            <th><i class="fas fa-box me-1"></i>Plan</th>
                                            <th><i class="fas fa-signal me-1"></i>Online</th>
                                            <th><i class="fas fa-check-circle me-1"></i>Status</th>
                                            <th><i class="fas fa-clock me-1"></i>Expires</th>
                                            <th class="text-end"><i class="fas fa-cog me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <!-- DB USERS (HOTSPOT + PPPOE) -->
                                        <?php foreach ($users as $u):
                                            $online = $u['user_type'] === 'pppoe'
                                                ? isset($livePPPoE[$u['username']])
                                                : isset($liveHotspot[$u['username']]);
                                            
                                            $isExpired = strtotime($u['expires_at']) < time();
                                        ?>
                                            <tr data-type="<?= $u['user_type'] ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2">
                                                            <div class="avatar-title bg-<?= $u['user_type'] == 'pppoe' ? 'info' : 'secondary' ?>-soft text-<?= $u['user_type'] == 'pppoe' ? 'info' : 'secondary' ?> rounded-circle">
                                                                <i class="fas fa-<?= $u['user_type'] == 'pppoe' ? 'network-wired' : 'rss' ?>"></i>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($u['username']) ?></strong>
                                                            <?php if ($online): ?>
                                                                <i class="fas fa-circle text-success ms-1" style="font-size: 0.5rem;" title="Online"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $u['user_type'] == 'pppoe' ? 'info' : 'secondary' ?>-soft text-<?= $u['user_type'] == 'pppoe' ? 'info' : 'secondary' ?>">
                                                        <?= strtoupper($u['user_type']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-soft text-primary">
                                                        <?= $u['router_name'] ?>
                                                    </span>
                                                </td>
                                                <td><?= $u['plan_name'] ?? '<span class="text-muted">No plan</span>' ?></td>
                                                <td>
                                                    <?php if ($online): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i>Online
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-times-circle me-1"></i>Offline
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($u['status'] == 'active' && !$isExpired): ?>
                                                        <span class="badge bg-success-soft text-success">
                                                            <i class="fas fa-check me-1"></i>Active
                                                        </span>
                                                    <?php elseif ($isExpired): ?>
                                                        <span class="badge bg-warning-soft text-warning">
                                                            <i class="fas fa-clock me-1"></i>Expired
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-soft text-danger">
                                                            <i class="fas fa-ban me-1"></i>Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="<?= $isExpired ? 'text-danger' : 'text-muted' ?>">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?= date("M d, Y H:i", strtotime($u['expires_at'])) ?>
                                                    </small>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-secondary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($online): ?>
                                                            <button class="btn btn-outline-warning" title="Disconnect">
                                                                <i class="fas fa-plug"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-outline-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <!-- CAPTIVE PORTAL (TEMP USERS) -->
                                        <?php foreach ($sessions as $s): 
                                            $isExpired = $s['expires_at'] && strtotime($s['expires_at']) < time();
                                        ?>
                                            <tr data-type="captive">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2">
                                                            <div class="avatar-title bg-warning-soft text-warning rounded-circle">
                                                                <i class="fas fa-wifi"></i>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <code><?= $s['mac_address'] ?></code>
                                                            <?php if ($s['paid']): ?>
                                                                <i class="fas fa-circle text-success ms-1" style="font-size: 0.5rem;" title="Online"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning-soft text-warning">
                                                        <i class="fas fa-wifi me-1"></i>CAPTIVE
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary-soft text-primary">
                                                        <?= $s['router_name'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= $s['plan_name'] ?? '<span class="text-muted fst-italic">Not selected</span>' ?>
                                                </td>
                                                <td>
                                                    <?php if ($s['paid']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i>Online
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-times-circle me-1"></i>Offline
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($s['paid'] && !$isExpired): ?>
                                                        <span class="badge bg-success-soft text-success">
                                                            <i class="fas fa-check-circle me-1"></i>Paid
                                                        </span>
                                                    <?php elseif ($isExpired): ?>
                                                        <span class="badge bg-warning-soft text-warning">
                                                            <i class="fas fa-clock me-1"></i>Expired
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info-soft text-info">
                                                            <i class="fas fa-hourglass-half me-1"></i>Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($s['expires_at']): ?>
                                                        <small class="<?= $isExpired ? 'text-danger' : 'text-muted' ?>">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?= date("M d, Y H:i", strtotime($s['expires_at'])) ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted">-</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" title="Remove">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>
    
    <!-- Filter functionality -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const filterButtons = document.querySelectorAll("[data-filter]");
            const tableRows = document.querySelectorAll("tbody tr[data-type]");

            filterButtons.forEach(btn => {
                btn.addEventListener("click", () => {
                    const filter = btn.dataset.filter;

                    // Update active button
                    filterButtons.forEach(b => b.classList.remove("active"));
                    btn.classList.add("active");

                    // Filter rows
                    tableRows.forEach(row => {
                        if (filter === "all" || row.dataset.type === filter) {
                            row.style.display = "";
                        } else {
                            row.style.display = "none";
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>

<!-- Enhanced Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="store.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add PPPoE User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <input type="hidden" name="router_id" value="<?= $router_id ?>">
                        <input type="hidden" name="user_type" value="pppoe">

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-server me-1"></i>Router
                            </label>
                            <select name="router_id" class="form-select" required>
                                <option value="">Select Router</option>
                                <?php foreach ($routers as $r): ?>
                                    <option value="<?= $r['router_id'] ?>" <?= $r['router_id'] == $router_id ? 'selected' : '' ?>>
                                        <?= $r['name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fas fa-box me-1"></i>Plan / Profile
                            </label>
                            <select name="plan_id" class="form-select" required>
                                <option value="">Select Plan</option>
                                <?php foreach ($plans as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= $p['profile_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user me-1"></i>Username
                            </label>
                            <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-key me-1"></i>Password
                            </label>
                            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> The user will be created on the selected router with the chosen plan configuration.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i>Create PPPoE User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>