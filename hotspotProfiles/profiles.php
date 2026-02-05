<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";

use RouterOS\Query;

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

/* Load routers */
$routers = $pdo->query("
    SELECT router_id, name 
    FROM routers 
    WHERE status='active'
    ORDER BY name
")->fetchAll();

$selected_router = $_GET['router_id'] ?? ($routers[0]['router_id'] ?? null);
$profiles = [];
$error = null;

// Stats
$totalProfiles = 0;
$hotspotCount = 0;
$pppoeCount = 0;
$configuredCount = 0;

if ($selected_router) {

    $router = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
    $router->execute([$selected_router]);
    $router = $router->fetch();

    $client = router_connect($selected_router);

    if ($client) {

        try {
            $hotspot = $client->query(
                new Query('/ip/hotspot/user/profile/print')
            )->read();

            $pppoe = $client->query(
                new Query('/ppp/profile/print')
            )->read();

            $dbProfiles = $pdo->prepare("
                SELECT * FROM hotspot_profiles 
                WHERE router_id=?
            ");
            $dbProfiles->execute([$selected_router]);
            $dbProfiles = $dbProfiles->fetchAll(PDO::FETCH_ASSOC);

            $dbIndex = [];
            foreach ($dbProfiles as $p) {
                $dbIndex[$p['profile_name']] = $p;
            }

            foreach ($hotspot as $p) {
                $profiles[] = mergeProfile($p, 'hotspot', $dbIndex);
                $hotspotCount++;
            }

            foreach ($pppoe as $p) {
                $profiles[] = mergeProfile($p, 'pppoe', $dbIndex);
                $pppoeCount++;
            }

            $totalProfiles = count($profiles);
            $configuredCount = count(array_filter($profiles, fn($p) => $p['in_db']));

        } catch (Exception $e) {
            $error = "Failed to fetch profiles from router";
        }
    } else {
        $error = "Router not reachable";
    }
}

function mergeProfile($routerProfile, $type, $dbIndex)
{
    $name = $routerProfile['name'];
    $db   = $dbIndex[$name] ?? [];

    return [
        'name'         => $name,
        'type'         => $type,
        'rate_limit'   => $routerProfile['rate-limit'] ?? '-',
        'shared_users' => $routerProfile['shared-users'] ?? '-',
        'price'        => $db['price'] ?? '—',
        'validity'     => isset($db['validity_days'])
            ? "{$db['validity_days']}d {$db['validity_hours']}h"
            : '—',
        'in_db'        => isset($db['profile_name'])
    ];
}
?>

<?php require_once "../partials/head.php"; ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>
        <div id="layoutDrawer_content">
            <main>

                <!-- Enhanced Header -->
                <header class="bg-dark">
                    <div class="container-xl px-1">
                        <div class="d-flex justify-content-between align-items-center py-3">
                            <h1 class="text-white mb-0 display-6">
                                <i class="fas fa-box me-2"></i>Packages & Profiles
                            </h1>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success-soft text-success">
                                    <i class="fas fa-check-circle me-1"></i><?= $configuredCount ?> Configured
                                </span>
                                <span class="badge bg-warning-soft text-warning">
                                    <i class="fas fa-exclamation-circle me-1"></i><?= $totalProfiles - $configuredCount ?> Router Only
                                </span>
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
                                            <div class="small text-muted">Total Profiles</div>
                                            <div class="h3 mb-0"><?= $totalProfiles ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-layer-group fa-2x text-primary opacity-50"></i>
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
                                            <div class="small text-muted">Hotspot Profiles</div>
                                            <div class="h3 mb-0"><?= $hotspotCount ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-wifi fa-2x text-info opacity-50"></i>
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
                                            <div class="small text-muted">PPPoE Profiles</div>
                                            <div class="h3 mb-0"><?= $pppoeCount ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-network-wired fa-2x text-warning opacity-50"></i>
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
                                            <div class="small text-muted">Configured</div>
                                            <div class="h3 mb-0"><?= $configuredCount ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Router Selector Card -->
                    <div class="card card-raised shadow-sm mb-4">
                        <div class="card-body">
                            <form method="GET">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-server me-1"></i>Select Router
                                        </label>
                                        <select name="router_id" class="form-select" onchange="this.form.submit()">
                                            <?php foreach ($routers as $r): ?>
                                                <option value="<?= $r['router_id'] ?>"
                                                    <?= $selected_router == $r['router_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($r['name']) ?>
                                                </option>
                                            <?php endforeach ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <button type="button"
                                            class="btn btn-primary w-100"
                                            data-bs-toggle="modal"
                                            data-bs-target="#addProfileModal">
                                            <i class="fas fa-plus me-2"></i>Add New Package
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Main Table Card -->
                    <div class="card card-raised shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-list me-2"></i>Profile List
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-light btn-sm active" data-filter="all">
                                        All Profiles
                                    </button>
                                    <button class="btn btn-light btn-sm" data-filter="hotspot">
                                        Hotspot
                                    </button>
                                    <button class="btn btn-light btn-sm" data-filter="pppoe">
                                        PPPoE
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="datatablesSimple" class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-tag me-1"></i>Profile Name</th>
                                            <th><i class="fas fa-layer-group me-1"></i>Type</th>
                                            <th><i class="fas fa-tachometer-alt me-1"></i>Rate Limit</th>
                                            <th><i class="fas fa-users me-1"></i>Shared Users</th>
                                            <th><i class="fas fa-money-bill me-1"></i>Price</th>
                                            <th><i class="fas fa-clock me-1"></i>Validity</th>
                                            <th><i class="fas fa-check-circle me-1"></i>Status</th>
                                            <th class="text-end"><i class="fas fa-cog me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <?php foreach ($profiles as $p): ?>
                                            <tr data-profile-type="<?= $p['type'] ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2">
                                                            <div class="avatar-title bg-<?= $p['type']=='pppoe'?'info':'secondary' ?>-soft text-<?= $p['type']=='pppoe'?'info':'secondary' ?> rounded-circle">
                                                                <i class="fas fa-box"></i>
                                                            </div>
                                                        </div>
                                                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                                                    </div>
                                                </td>

                                                <td>
                                                    <span class="badge bg-<?= $p['type'] == 'pppoe' ? 'info' : 'secondary' ?>-soft text-<?= $p['type'] == 'pppoe' ? 'info' : 'secondary' ?>">
                                                        <i class="fas fa-<?= $p['type'] == 'pppoe' ? 'network-wired' : 'wifi' ?> me-1"></i>
                                                        <?= strtoupper($p['type']) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?php if ($p['rate_limit'] !== '-'): ?>
                                                        <code class="text-primary"><?= $p['rate_limit'] ?></code>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ($p['shared_users'] !== '-'): ?>
                                                        <span class="badge bg-info-soft text-info">
                                                            <?= $p['shared_users'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ($p['price'] !== '—'): ?>
                                                        <strong class="text-success">
                                                            <i class="fas fa-money-bill-wave me-1"></i>
                                                            KES <?= number_format($p['price'], 2) ?>
                                                        </strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ($p['validity'] !== '—'): ?>
                                                        <span class="badge bg-warning-soft text-warning">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?= $p['validity'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if ($p['in_db']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i>Configured
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Router Only
                                                        </span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary view-profile"
                                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                                            data-type="<?= $p['type'] ?>"
                                                            data-rate="<?= htmlspecialchars($p['rate_limit']) ?>"
                                                            data-shared="<?= $p['shared_users'] ?>"
                                                            data-price="<?= $p['price'] ?>"
                                                            data-validity="<?= $p['validity'] ?>"
                                                            data-router="<?= $selected_router ?>"
                                                            title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>

                                                        <button class="btn btn-outline-secondary edit-profile"
                                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                                            data-type="<?= $p['type'] ?>"
                                                            data-rate="<?= htmlspecialchars($p['rate_limit']) ?>"
                                                            data-shared="<?= $p['shared_users'] ?>"
                                                            data-price="<?= $p['price'] ?>"
                                                            data-validity="<?= $p['validity'] ?>"
                                                            data-router="<?= $selected_router ?>"
                                                            title="Edit Profile">
                                                            <i class="fas fa-edit"></i>
                                                        </button>

                                                        <button class="btn btn-outline-danger delete-profile"
                                                            data-name="<?= htmlspecialchars($p['name']) ?>"
                                                            data-type="<?= $p['type'] ?>"
                                                            data-router="<?= $selected_router ?>"
                                                            title="Delete Profile">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach ?>

                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <?php if (empty($profiles)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No profiles found on this router</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProfileModal">
                                        <i class="fas fa-plus me-1"></i>Create Your First Profile
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </main>
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>
    
    <!-- Enhanced Modals -->
    <?php include "modals.php"; ?>

    <!-- Filter and Action Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // Filter functionality
            const filterButtons = document.querySelectorAll("[data-filter]");
            
            filterButtons.forEach(btn => {
                btn.addEventListener("click", () => {
                    const filter = btn.dataset.filter;
                    
                    // Update active button
                    filterButtons.forEach(b => b.classList.remove("active"));
                    btn.classList.add("active");
                    
                    // Filter using Simple-DataTables
                    const table = document.getElementById("datatablesSimple");
                    if (table && table.datatable) {
                        if (filter === "all") {
                            table.datatable.search("");
                        } else {
                            table.datatable.search(filter);
                        }
                    }
                });
            });

            // View Profile Modal
            document.addEventListener('click', function(e) {
                if (e.target.closest('.view-profile')) {
                    e.preventDefault();
                    const btn = e.target.closest('.view-profile');

                    view('view-name', btn.dataset.name);
                    view('view-type', btn.dataset.type.toUpperCase());
                    view('view-rate', btn.dataset.rate);
                    view('view-shared', btn.dataset.shared);
                    view('view-price', btn.dataset.price !== '—' ? 'KES ' + parseFloat(btn.dataset.price).toFixed(2) : '—');
                    view('view-validity', btn.dataset.validity);

                    new bootstrap.Modal('#viewProfileModal').show();
                }

                // Edit Profile Modal
                if (e.target.closest('.edit-profile')) {
                    e.preventDefault();
                    const btn = e.target.closest('.edit-profile');

                    set('edit-name', btn.dataset.name);
                    set('edit-rate', btn.dataset.rate);
                    set('edit-shared', btn.dataset.shared);
                    set('edit-price', btn.dataset.price);
                    set('edit-validity', btn.dataset.validity);
                    set('edit-router', btn.dataset.router);

                    new bootstrap.Modal('#editProfileModal').show();
                }

                // Delete Profile Modal
                if (e.target.closest('.delete-profile')) {
                    e.preventDefault();
                    const btn = e.target.closest('.delete-profile');

                    set('delete-name', btn.dataset.name);
                    set('delete-router', btn.dataset.router);
                    document.getElementById('delete-profile-name').innerText = btn.dataset.name;

                    new bootstrap.Modal('#deleteProfileModal').show();
                }
            });

            function set(id, val) {
                const elem = document.getElementById(id);
                if (elem) elem.value = val;
            }

            function view(id, val) {
                const elem = document.getElementById(id);
                if (elem) elem.innerText = val;
            }
        });
    </script>
</body>

</html>