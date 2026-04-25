<?php
require_once "../partials/head.php";
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/router.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);


/* Load users (hotspot + pppoe) */
$stmt = $pdo->prepare("
    SELECT hu.*, r.name AS router_name, hp.profile_name AS plan_name
    FROM hotspot_users hu
    JOIN routers r ON r.router_id = hu.router_id
    JOIN user_router_access ur ON ur.router_id = hu.router_id
    LEFT JOIN hotspot_profiles hp ON hp.id = hu.plan_id
    WHERE ur.user_id = ?
    ORDER BY hu.user_type, hu.username
");

$stmt->execute([$user_id]);
$users = $stmt->fetchAll();

/* Load live users */
$liveHotspot = [];
$livePPPoE   = [];

$user_id = $_SESSION['user']['id'];

$stmt = $pdo->prepare("
    SELECT r.*
    FROM routers r
    JOIN user_router_access ur ON ur.router_id = r.router_id
    WHERE ur.user_id = ?
    AND r.status = 'active'
");

$stmt->execute([$user_id]);
$routers = $stmt->fetchAll();



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
$stmt = $pdo->prepare("
    SELECT hp.*
    FROM hotspot_profiles hp
    JOIN user_router_access ur ON ur.router_id = hp.router_id
    WHERE ur.user_id = ?
    AND hp.plan_type = 'pppoe'
");

$stmt->execute([$user_id]);
$plans = $stmt->fetchAll();
$router_id = $routers[0]['router_id'] ?? null;

/* Calculate stats */
$totalUsers = count($users);
$onlineUsers = 0;
$activeUsers = 0;
$pppoeCount = 0;
$hotspotCount = 0;

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
                                <i class="fa fa-users me-2"></i>Network Users
                            </h1>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success-soft text-success">
                                    <i class="fa fa-circle me-1"></i><?= $onlineUsers ?> Online
                                </span>
                                <span class="badge bg-secondary-soft text-secondary">
                                    <i class="fa fa-circle me-1"></i><?= $totalUsers - $onlineUsers ?> Offline
                                </span>
                                <?php if ($_SESSION['user']['role'] !== "staff"): ?>
                                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                        <i class="fa fa-plus me-1"></i>Add User
                                    </button>
                                <?php endif; ?>
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
                                            <i class="fa fa-users fa-2x text-primary opacity-50"></i>
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
                                            <i class="fa fa-wifi fa-2x text-success opacity-50"></i>
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
                                            <i class="fa fa-network-wired fa-2x text-info opacity-50"></i>
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
                                            <i class="fa fa-rss fa-2x text-warning opacity-50"></i>
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
                                    <i class="fa fa-list me-2"></i>User Management
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
                                            <th><i class="fa fa-user me-1"></i>User / MAC</th>
                                            <th><i class="fa fa-tag me-1"></i>Type</th>
                                            <th><i class="fa fa-server me-1"></i>Router</th>
                                            <th><i class="fa fa-box me-1"></i>Plan</th>
                                            <th><i class="fa fa-signal me-1"></i>Online</th>
                                            <th><i class="fa fa-check-circle me-1"></i>Status</th>
                                            <th><i class="fa fa-clock me-1"></i>Expires</th>
                                            <?php if ($_SESSION['user']['role'] !== "staff"): ?>
                                                <th class="text-end"><i class="fa fa-cog me-1"></i>Actions</th>
                                            <?php endif; ?>
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
                                                                <i class="fa fa-<?= $u['user_type'] == 'pppoe' ? 'network-wired' : 'rss' ?>"></i>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($u['username']) ?></strong>
                                                            <?php if ($online): ?>
                                                                <i class="fa fa-circle text-success ms-1" style="font-size: 0.5rem;" title="Online"></i>
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
                                                            <i class="fa fa-check-circle me-1"></i>Online
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fa fa-times-circle me-1"></i>Offline
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($u['status'] == 'active' && !$isExpired): ?>
                                                        <span class="badge bg-success-soft text-success">
                                                            <i class="fa fa-check me-1"></i>Active
                                                        </span>
                                                    <?php elseif ($isExpired): ?>
                                                        <span class="badge bg-warning-soft text-warning">
                                                            <i class="fa fa-clock me-1"></i>Expired
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-soft text-danger">
                                                            <i class="fa fa-ban me-1"></i>Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="<?= $isExpired ? 'text-danger' : 'text-muted' ?>">
                                                        <i class="fa fa-calendar me-1"></i>
                                                        <?= date("M d, Y H:i", strtotime($u['expires_at'])) ?>
                                                    </small>
                                                </td>
                                                <?php if ($_SESSION['user']['role'] !== "staff"): ?>
                                                    <td class="text-end">
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary viewBtn"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#viewUserModal"

                                                                data-username="<?= $u['username'] ?>"
                                                                data-router="<?= $u['router_name'] ?>"
                                                                data-plan="<?= $u['plan_name'] ?>"
                                                                data-status="<?= $u['status'] ?>"
                                                                data-expiry="<?= $u['expires_at'] ?>">

                                                                <i class="fa fa-eye"></i>

                                                            </button>

                                                            <?php if ($u['user_type'] === 'pppoe'): ?>

                                                                <?php if ($u['user_type'] === 'pppoe'): ?>

                                                                    <button class="btn btn-outline-secondary editBtn"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#editUserModal"
                                                                        data-username="<?= $u['username'] ?>"
                                                                        data-plan="<?= $u['plan_id'] ?>"
                                                                        data-expiry="<?= $u['expires_at'] ?>">
                                                                        <i class="fa fa-edit"></i>
                                                                    </button>

                                                                <?php else: ?>

                                                                    <button class="btn btn-outline-secondary" disabled title="Hotspot users cannot be edited">
                                                                        <i class="fa fa-lock"></i>
                                                                    </button>

                                                                <?php endif; ?>


                                                            <?php endif; ?>




                                                            <button class="btn btn-outline-danger deleteBtn"

                                                                data-bs-toggle="modal"

                                                                data-bs-target="#deleteUserModal"

                                                                data-username="<?= $u['username'] ?>">

                                                                <i class="fa fa-trash"></i>

                                                            </button>

                                                        </div>
                                                    </td>
                                                <?php endif; ?>
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

    <script>
        document.addEventListener("click", function(e) {


            /* VIEW */


            if (e.target.closest(".viewBtn")) {


                let btn = e.target.closest(".viewBtn");


                view_username.innerText = btn.dataset.username;

                view_router.innerText = btn.dataset.router;

                view_plan.innerText = btn.dataset.plan;

                view_status.innerText = btn.dataset.status;

                view_expiry.innerText = btn.dataset.expiry;


            }


            /* EDIT */


            if (e.target.closest(".editBtn")) {


                let btn = e.target.closest(".editBtn");


                edit_username.value = btn.dataset.username;

                edit_plan.value = btn.dataset.plan;

                edit_expiry.value = btn.dataset.expiry.replace(" ", "T");


            }


            /* DELETE */


            if (e.target.closest(".deleteBtn")) {


                let btn = e.target.closest(".deleteBtn");


                delete_username.value = btn.dataset.username;

                delete_username_text.innerText = btn.dataset.username;


            }


        });
    </script>

</body>

</html>

<?php require_once "modals.php"; ?>