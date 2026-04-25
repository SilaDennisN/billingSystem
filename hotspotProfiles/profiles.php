<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

use RouterOS\Query;

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);


/* ── Scope routers to logged-in user ── */
$user_id = $_SESSION['user']['id'];

$stmt = $pdo->prepare("
    SELECT r.router_id, r.name 
    FROM routers r
    INNER JOIN user_router_access ura ON ura.router_id = r.router_id
    WHERE r.status = 'active'
    AND ura.user_id = ?
    ORDER BY r.name
");
$stmt->execute([$user_id]);
$routers = $stmt->fetchAll();

$selected_router = $_GET['router_id'] ?? ($routers[0]['router_id'] ?? null);
$profiles = [];
$error = null;

$totalProfiles  = 0;
$hotspotCount   = 0;
$pppoeCount     = 0;
$configuredCount = 0;

if ($selected_router) {

    /* Ensure selected router belongs to this user */
    $allowed = array_column($routers, 'router_id');
    if (!in_array($selected_router, $allowed)) {
        $selected_router = $allowed[0] ?? null;
    }

    $router = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
    $router->execute([$selected_router]);
    $router = $router->fetch();

    $client = router_connect($selected_router);

    if ($client) {
        try {
            $hotspot = $client->query(new Query('/ip/hotspot/user/profile/print'))->read();
            $pppoe   = $client->query(new Query('/ppp/profile/print'))->read();

            $dbProfiles = $pdo->prepare("SELECT * FROM hotspot_profiles WHERE router_id=?");
            $dbProfiles->execute([$selected_router]);
            $dbProfiles = $dbProfiles->fetchAll(PDO::FETCH_ASSOC);

            $dbIndex = [];
            foreach ($dbProfiles as $p) {
                $dbIndex[$p['profile_name']] = $p;
            }

            foreach ($hotspot as $p) {
                $merged = mergeProfile($p, 'hotspot', $dbIndex);
                /* Only show profiles configured in DB — skip router defaults */
                if (!$merged['in_db']) continue;
                $profiles[] = $merged;
                $hotspotCount++;
            }

            foreach ($pppoe as $p) {
                $merged = mergeProfile($p, 'pppoe', $dbIndex);
                if (!$merged['in_db']) continue;
                $profiles[] = $merged;
                $pppoeCount++;
            }

            $totalProfiles   = count($profiles);
            $configuredCount = $totalProfiles; // all shown are configured

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
        'in_db'        => isset($db['profile_name']),
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

                <!-- Header -->
                <header style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%); border-bottom: 3px solid #3b82f6;">
                    <div class="container-xl px-1 py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:46px;height:46px;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.4);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fa fa-box" style="color:#60a5fa;font-size:1.2rem;"></i>
                                </div>
                                <div>
                                    <h1 class="text-white mb-0" style="font-size:1.4rem;font-weight:700;letter-spacing:-0.3px;">Packages &amp; Profiles</h1>
                                    <p class="mb-0" style="color:rgba(255,255,255,0.5);font-size:0.78rem;">Configured packages on your routers</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.3);color:#4ade80;font-size:0.75rem;font-weight:600;padding:4px 10px;border-radius:20px;">
                                    <i class="fa fa-check-circle me-1"></i><?= $totalProfiles ?> Packages
                                </span>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 py-4">

                    <!-- Stat cards -->
                    <div class="row g-4 mb-4">

                        <div class="col-6 col-md-3">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Total</span>
                                        <div style="width:32px;height:32px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa fa-layer-group" style="color:#3b82f6;font-size:0.8rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><?= $totalProfiles ?></div>
                                    <div style="font-size:0.75rem;color:#94a3b8;margin-top:3px;">packages</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Hotspot</span>
                                        <div style="width:32px;height:32px;border-radius:8px;background:#f0f9ff;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa fa-wifi" style="color:#0ea5e9;font-size:0.8rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><?= $hotspotCount ?></div>
                                    <div style="font-size:0.75rem;color:#94a3b8;margin-top:3px;">profiles</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">PPPoE</span>
                                        <div style="width:32px;height:32px;border-radius:8px;background:#fffbeb;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa fa-network-wired" style="color:#f59e0b;font-size:0.8rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><?= $pppoeCount ?></div>
                                    <div style="font-size:0.75rem;color:#94a3b8;margin-top:3px;">profiles</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Routers</span>
                                        <div style="width:32px;height:32px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa fa-server" style="color:#22c55e;font-size:0.8rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><?= count($routers) ?></div>
                                    <div style="font-size:0.75rem;color:#94a3b8;margin-top:3px;">assigned to you</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Router selector + Add button -->
                    <div class="card border-0 mb-4" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                        <div class="card-body p-4">
                            <form method="GET">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">
                                            <i class="fa fa-server me-1"></i>Select Router
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
                                    <?php if ($_SESSION['user']['role'] !== 'staff'): ?>
                                    <div class="col-md-6">
                                        <button type="button"
                                            class="btn w-100 py-2"
                                            style="background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:9px;font-weight:600;font-size:0.88rem;"
                                            data-bs-toggle="modal"
                                            data-bs-target="#addProfileModal">
                                            <i class="fa fa-plus me-2"></i>Add New Package
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert d-flex align-items-center gap-2 mb-4" style="background:#2b0d0d;border:1px solid #7a1a1a;border-radius:10px;color:#f87171;">
                            <i class="fas fa-triangle-exclamation"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Table card -->
                    <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);overflow:hidden;">

                        <div class="card-header d-flex align-items-center justify-content-between py-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                            <span style="font-weight:700;color:#1e293b;font-size:0.9rem;">
                                <i class="fa fa-list me-2" style="color:#94a3b8;"></i>Package List
                            </span>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-sm active filter-btn" data-filter="all"
                                    style="border-radius:7px 0 0 7px;font-size:0.78rem;font-weight:600;">All</button>
                                <button class="btn btn-sm filter-btn" data-filter="hotspot"
                                    style="font-size:0.78rem;font-weight:600;">Hotspot</button>
                                <button class="btn btn-sm filter-btn" data-filter="pppoe"
                                    style="border-radius:0 7px 7px 0;font-size:0.78rem;font-weight:600;">PPPoE</button>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <?php if (!empty($profiles)): ?>
                            <div class="table-responsive">
                                <table id="datatablesSimple" class="table table-hover align-middle mb-0">
                                    <thead style="background:#f8fafc;">
                                        <tr style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.6px;color:#94a3b8;">
                                            <th class="px-4 py-3">Package Name</th>
                                            <th class="py-3">Type</th>
                                            <th class="py-3">Rate Limit</th>
                                            <th class="py-3">Shared Users</th>
                                            <th class="py-3">Price</th>
                                            <th class="py-3">Validity</th>
                                            <?php if ($_SESSION['user']['role'] !== 'staff'): ?>
                                            <th class="py-3 text-end px-4">Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($profiles as $p): ?>
                                        <tr data-profile-type="<?= $p['type'] ?>" style="border-bottom:1px solid #f1f5f9;">

                                            <td class="px-4 py-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="width:32px;height:32px;border-radius:8px;background:<?= $p['type'] === 'pppoe' ? '#fffbeb' : '#eff6ff' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                        <i class="fa fa-<?= $p['type'] === 'pppoe' ? 'network-wired' : 'wifi' ?>" style="color:<?= $p['type'] === 'pppoe' ? '#f59e0b' : '#3b82f6' ?>;font-size:0.8rem;"></i>
                                                    </div>
                                                    <strong style="color:#1e293b;font-size:0.88rem;"><?= htmlspecialchars($p['name']) ?></strong>
                                                </div>
                                            </td>

                                            <td>
                                                <span style="background:<?= $p['type'] === 'pppoe' ? '#fffbeb' : '#eff6ff' ?>;color:<?= $p['type'] === 'pppoe' ? '#b45309' : '#1d4ed8' ?>;font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px;">
                                                    <?= strtoupper($p['type']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php if ($p['rate_limit'] !== '-'): ?>
                                                    <code style="background:#f1f5f9;color:#0369a1;padding:3px 7px;border-radius:5px;font-size:0.8rem;"><?= htmlspecialchars($p['rate_limit']) ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if ($p['shared_users'] !== '-'): ?>
                                                    <span style="background:#f0f9ff;color:#0369a1;font-size:0.78rem;font-weight:600;padding:3px 9px;border-radius:20px;">
                                                        <?= $p['shared_users'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if ($p['price'] !== '—'): ?>
                                                    <span style="color:#15803d;font-weight:700;font-size:0.88rem;">
                                                        KES <?= number_format((float)$p['price'], 2) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if ($p['validity'] !== '—'): ?>
                                                    <span style="background:#fefce8;color:#a16207;font-size:0.78rem;font-weight:600;padding:3px 9px;border-radius:20px;">
                                                        <i class="fa fa-calendar me-1"></i><?= $p['validity'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <?php if ($_SESSION['user']['role'] !== 'staff'): ?>
                                            <td class="text-end px-4">
                                                <div class="d-flex justify-content-end gap-1">
                                                    <button type="button" class="btn btn-sm view-profile"
                                                        style="background:#eff6ff;color:#3b82f6;border:none;border-radius:7px;width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;"
                                                        data-name="<?= htmlspecialchars($p['name']) ?>"
                                                        data-type="<?= $p['type'] ?>"
                                                        data-rate="<?= htmlspecialchars($p['rate_limit']) ?>"
                                                        data-shared="<?= $p['shared_users'] ?>"
                                                        data-price="<?= $p['price'] ?>"
                                                        data-validity="<?= $p['validity'] ?>"
                                                        data-router="<?= $selected_router ?>"
                                                        title="View">
                                                        <i class="fa fa-eye" style="font-size:0.8rem;"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm edit-profile"
                                                        style="background:#f0fdf4;color:#22c55e;border:none;border-radius:7px;width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;"
                                                        data-name="<?= htmlspecialchars($p['name']) ?>"
                                                        data-type="<?= $p['type'] ?>"
                                                        data-rate="<?= htmlspecialchars($p['rate_limit']) ?>"
                                                        data-shared="<?= $p['shared_users'] ?>"
                                                        data-price="<?= $p['price'] ?>"
                                                        data-validity="<?= $p['validity'] ?>"
                                                        data-router="<?= $selected_router ?>"
                                                        title="Edit">
                                                        <i class="fa fa-edit" style="font-size:0.8rem;"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm delete-profile"
                                                        style="background:#fef2f2;color:#ef4444;border:none;border-radius:7px;width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;"
                                                        data-name="<?= htmlspecialchars($p['name']) ?>"
                                                        data-type="<?= $p['type'] ?>"
                                                        data-router="<?= $selected_router ?>"
                                                        title="Delete">
                                                        <i class="fa fa-trash" style="font-size:0.8rem;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <?php endif; ?>

                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-5">
                                <div style="width:64px;height:64px;background:#f1f5f9;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                    <i class="fa fa-box" style="font-size:1.5rem;color:#94a3b8;"></i>
                                </div>
                                <p style="color:#64748b;font-size:0.9rem;margin-bottom:12px;">
                                    <?= $error ? 'Could not load profiles.' : 'No configured packages found on this router.' ?>
                                </p>
                                <?php if (!$error && $_SESSION['user']['role'] !== 'staff'): ?>
                                <button class="btn btn-sm px-4"
                                    style="background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:9px;font-weight:600;"
                                    data-bs-toggle="modal" data-bs-target="#addProfileModal">
                                    <i class="fa fa-plus me-1"></i>Add First Package
                                </button>
                                <?php endif; ?>
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
    <?php include "modals.php"; ?>

    <style>
        .filter-btn { background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0; }
        .filter-btn.active { background:#3b82f6;color:#fff;border-color:#3b82f6; }
        .filter-btn:hover:not(.active) { background:#e2e8f0; }
        tr:last-child { border-bottom: none !important; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            /* ── Filter buttons ── */
            const filterBtns = document.querySelectorAll('.filter-btn');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    const filter = btn.dataset.filter;
                    document.querySelectorAll('tr[data-profile-type]').forEach(row => {
                        row.style.display = (filter === 'all' || row.dataset.profileType === filter) ? '' : 'none';
                    });
                });
            });

            /* ── Modal helpers ── */
            function set(id, val) {
                const el = document.getElementById(id);
                if (el) el.value = val;
            }
            function view(id, val) {
                const el = document.getElementById(id);
                if (el) el.innerText = val;
            }

            document.addEventListener('click', function (e) {

                if (e.target.closest('.view-profile')) {
                    const btn = e.target.closest('.view-profile');
                    view('view-name',     btn.dataset.name);
                    view('view-type',     btn.dataset.type.toUpperCase());
                    view('view-rate',     btn.dataset.rate);
                    view('view-shared',   btn.dataset.shared);
                    view('view-price',    btn.dataset.price !== '—' ? 'KES ' + parseFloat(btn.dataset.price).toFixed(2) : '—');
                    view('view-validity', btn.dataset.validity);
                    new bootstrap.Modal('#viewProfileModal').show();
                }

                if (e.target.closest('.edit-profile')) {
                    const btn = e.target.closest('.edit-profile');
                    set('edit-name',   btn.dataset.name);
                    set('edit-type',   btn.dataset.type);
                    set('edit-rate',   btn.dataset.rate);
                    set('edit-shared', btn.dataset.shared);
                    set('edit-price',  btn.dataset.price);

                    const v = btn.dataset.validity;
                    if (v && v !== '—') {
                        const parts = v.split(' ');
                        set('edit-validity-days',  parseInt(parts[0]));
                        set('edit-validity-hours', parseInt(parts[1]));
                    }
                    set('edit-router', btn.dataset.router);
                    new bootstrap.Modal('#editProfileModal').show();
                }

                if (e.target.closest('.delete-profile')) {
                    const btn = e.target.closest('.delete-profile');
                    set('delete-name',   btn.dataset.name);
                    set('delete-router', btn.dataset.router);
                    set('delete-type',   btn.dataset.type);
                    const label = document.getElementById('delete-profile-name');
                    if (label) label.innerText = btn.dataset.name;
                    new bootstrap.Modal('#deleteProfileModal').show();
                }
            });
        });
    </script>
</body>
</html>