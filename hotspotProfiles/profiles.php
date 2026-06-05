<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
$user_id = $_SESSION['user']['id'];
check_subscription_gate($pdo, $user_id);

/* ── Only fetch routers (fast DB query, no router connection) ── */
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

/* Ensure selected router belongs to this user */
$allowed = array_column($routers, 'router_id');
if ($selected_router && !in_array($selected_router, $allowed)) {
    $selected_router = $allowed[0] ?? null;
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
                                <span id="header-total-badge" style="background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.3);color:#4ade80;font-size:0.75rem;font-weight:600;padding:4px 10px;border-radius:20px;">
                                    <i class="fa fa-check-circle me-1"></i><span id="stat-total-badge">—</span> Packages
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
                                    <div id="stat-total" style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;">—</div>
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
                                    <div id="stat-hotspot" style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;">—</div>
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
                                    <div id="stat-pppoe" style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;">—</div>
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
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">
                                        <i class="fa fa-server me-1"></i>Select Router
                                    </label>
                                    <select id="router-select" name="router_id" class="form-select">
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
                        </div>
                    </div>

                    <!-- Table card -->
                    <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);overflow:hidden;">

                        <div class="card-header d-flex align-items-center justify-content-between py-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                            <span style="font-weight:700;color:#1e293b;font-size:0.9rem;">
                                <i class="fa fa-list me-2" style="color:#94a3b8;"></i>Package List
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <!-- Retry button (hidden by default) -->
                                <button id="retry-btn" class="btn btn-sm d-none"
                                    style="background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:7px;font-size:0.78rem;font-weight:600;">
                                    <i class="fa fa-rotate-right me-1"></i>Retry
                                </button>
                                <div class="btn-group btn-group-sm" role="group" id="filter-group">
                                    <button class="btn btn-sm active filter-btn" data-filter="all"
                                        style="border-radius:7px 0 0 7px;font-size:0.78rem;font-weight:600;">All</button>
                                    <button class="btn btn-sm filter-btn" data-filter="hotspot"
                                        style="font-size:0.78rem;font-weight:600;">Hotspot</button>
                                    <button class="btn btn-sm filter-btn" data-filter="pppoe"
                                        style="border-radius:0 7px 7px 0;font-size:0.78rem;font-weight:600;">PPPoE</button>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic content area -->
                        <div class="card-body p-0" id="profiles-container">
                            <!-- Skeleton loader shown on first paint -->
                            <div id="profiles-loading" class="py-5 text-center">
                                <div class="d-flex justify-content-center mb-3">
                                    <div class="spinner-border text-primary" role="status" style="width:2rem;height:2rem;">
                                        <span class="visually-hidden">Loading…</span>
                                    </div>
                                </div>
                                <p style="color:#64748b;font-size:0.88rem;margin:0;">Connecting to router and fetching profiles…</p>
                            </div>
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

        /* Skeleton pulse animation */
        @keyframes skeleton-pulse {
            0%   { opacity: 1; }
            50%  { opacity: 0.4; }
            100% { opacity: 1; }
        }
        .skeleton-row td { padding: 14px 16px; }
        .skeleton-cell {
            height: 14px;
            border-radius: 6px;
            background: #e2e8f0;
            animation: skeleton-pulse 1.4s ease-in-out infinite;
        }

        /* Fade-in for loaded table */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .profiles-loaded {
            animation: fadeIn 0.3s ease-out;
        }
    </style>

    <script>
    (function () {
        'use strict';

        const isStaff = <?= json_encode($_SESSION['user']['role'] === 'staff') ?>;
        const container  = document.getElementById('profiles-container');
        const retryBtn   = document.getElementById('retry-btn');
        const routerSel  = document.getElementById('router-select');
        let   activeFilter = 'all';
        let   currentRouterID = routerSel ? routerSel.value : null;

        /* ── Helpers ── */
        function esc(str) {
            const d = document.createElement('div');
            d.appendChild(document.createTextNode(str));
            return d.innerHTML;
        }

        function setStats(total, hotspot, pppoe) {
            document.getElementById('stat-total').innerText        = total;
            document.getElementById('stat-hotspot').innerText      = hotspot;
            document.getElementById('stat-pppoe').innerText        = pppoe;
            document.getElementById('stat-total-badge').innerText  = total;
        }

        function resetStats() {
            ['stat-total','stat-hotspot','stat-pppoe','stat-total-badge'].forEach(id => {
                document.getElementById(id).innerText = '—';
            });
        }

        /* ── Skeleton rows while loading ── */
        function showSkeleton() {
            resetStats();
            retryBtn.classList.add('d-none');

            const widths = ['40%','15%','20%','15%','18%','16%'];
            let rows = '';
            for (let i = 0; i < 5; i++) {
                rows += '<tr class="skeleton-row">' +
                    widths.map(w =>
                        `<td><div class="skeleton-cell" style="width:${w};animation-delay:${i * 0.1}s"></div></td>`
                    ).join('') +
                    (isStaff ? '' : '<td><div class="skeleton-cell" style="width:80px;margin-left:auto;animation-delay:' + (i * 0.1) + 's"></div></td>') +
                '</tr>';
            }

            container.innerHTML = `
                <div class="table-responsive profiles-loaded">
                    <table class="table align-middle mb-0">
                        <thead style="background:#f8fafc;">
                            <tr style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.6px;color:#94a3b8;">
                                <th class="px-4 py-3">Package Name</th>
                                <th class="py-3">Type</th>
                                <th class="py-3">Rate Limit</th>
                                <th class="py-3">Shared Users</th>
                                <th class="py-3">Price</th>
                                <th class="py-3">Validity</th>
                                ${isStaff ? '' : '<th class="py-3 text-end px-4">Actions</th>'}
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
        }

        /* ── Error state ── */
        function showError(msg) {
            retryBtn.classList.remove('d-none');
            container.innerHTML = `
                <div class="text-center py-5">
                    <div style="width:56px;height:56px;background:#fef2f2;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                        <i class="fa fa-triangle-exclamation" style="font-size:1.4rem;color:#ef4444;"></i>
                    </div>
                    <p style="color:#64748b;font-size:0.9rem;margin-bottom:4px;font-weight:600;">Could not load profiles</p>
                    <p style="color:#94a3b8;font-size:0.82rem;margin:0;">${esc(msg)}</p>
                </div>`;
        }

        /* ── Empty state ── */
        function showEmpty() {
            retryBtn.classList.add('d-none');
            container.innerHTML = `
                <div class="text-center py-5">
                    <div style="width:64px;height:64px;background:#f1f5f9;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                        <i class="fa fa-box" style="font-size:1.5rem;color:#94a3b8;"></i>
                    </div>
                    <p style="color:#64748b;font-size:0.9rem;margin-bottom:12px;">No configured packages found on this router.</p>
                    ${isStaff ? '' : `
                    <button class="btn btn-sm px-4"
                        style="background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border:none;border-radius:9px;font-weight:600;"
                        data-bs-toggle="modal" data-bs-target="#addProfileModal">
                        <i class="fa fa-plus me-1"></i>Add First Package
                    </button>`}
                </div>`;
        }

        /* ── Build action buttons ── */
        function buildActions(p, routerID) {
            if (isStaff) return '';
            return `
                <td class="text-end px-4">
                    <div class="d-flex justify-content-end gap-1">
                        <button type="button" class="btn btn-sm view-profile"
                            style="background:#eff6ff;color:#3b82f6;border:none;border-radius:7px;width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;"
                            data-name="${esc(p.name)}"
                            data-type="${esc(p.type)}"
                            data-rate="${esc(p.rate_limit)}"
                            data-shared="${esc(p.shared_users)}"
                            data-price="${esc(p.price)}"
                            data-validity="${esc(p.validity)}"
                            data-router="${esc(routerID)}"
                            title="View">
                            <i class="fa fa-eye" style="font-size:0.8rem;"></i>
                        </button>
                        <button type="button" class="btn btn-sm edit-profile"
                            style="background:#f0fdf4;color:#22c55e;border:none;border-radius:7px;width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;"
                            data-name="${esc(p.name)}"
                            data-type="${esc(p.type)}"
                            data-rate="${esc(p.rate_limit)}"
                            data-shared="${esc(p.shared_users)}"
                            data-price="${esc(p.price)}"
                            data-validity="${esc(p.validity)}"
                            data-router="${esc(routerID)}"
                            title="Edit">
                            <i class="fa fa-edit" style="font-size:0.8rem;"></i>
                        </button>
                        <button type="button" class="btn btn-sm delete-profile"
                            style="background:#fef2f2;color:#ef4444;border:none;border-radius:7px;width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;"
                            data-name="${esc(p.name)}"
                            data-type="${esc(p.type)}"
                            data-router="${esc(routerID)}"
                            title="Delete">
                            <i class="fa fa-trash" style="font-size:0.8rem;"></i>
                        </button>
                    </div>
                </td>`;
        }

        /* ── Render table from profile array ── */
        function renderTable(profiles, routerID) {
            retryBtn.classList.add('d-none');

            if (!profiles.length) {
                showEmpty();
                return;
            }

            const rows = profiles.map(p => {
                const isHotspot = p.type === 'pppoe';
                const iconBg    = isHotspot ? '#fffbeb' : '#eff6ff';
                const iconColor = isHotspot ? '#f59e0b' : '#3b82f6';
                const iconClass = isHotspot ? 'network-wired' : 'wifi';
                const tagBg     = isHotspot ? '#fffbeb' : '#eff6ff';
                const tagColor  = isHotspot ? '#b45309' : '#1d4ed8';

                const rateCell = p.rate_limit !== '-'
                    ? `<code style="background:#f1f5f9;color:#0369a1;padding:3px 7px;border-radius:5px;font-size:0.8rem;">${esc(p.rate_limit)}</code>`
                    : `<span class="text-muted">—</span>`;

                const sharedCell = p.shared_users !== '-'
                    ? `<span style="background:#f0f9ff;color:#0369a1;font-size:0.78rem;font-weight:600;padding:3px 9px;border-radius:20px;">${esc(p.shared_users)}</span>`
                    : `<span class="text-muted">—</span>`;

                const priceCell = p.price !== '—'
                    ? `<span style="color:#15803d;font-weight:700;font-size:0.88rem;">KES ${parseFloat(p.price).toFixed(2)}</span>`
                    : `<span class="text-muted">—</span>`;

                const validityCell = p.validity !== '—'
                    ? `<span style="background:#fefce8;color:#a16207;font-size:0.78rem;font-weight:600;padding:3px 9px;border-radius:20px;"><i class="fa fa-calendar me-1"></i>${esc(p.validity)}</span>`
                    : `<span class="text-muted">—</span>`;

                return `
                <tr data-profile-type="${esc(p.type)}" style="border-bottom:1px solid #f1f5f9;">
                    <td class="px-4 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:32px;height:32px;border-radius:8px;background:${iconBg};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fa fa-${iconClass}" style="color:${iconColor};font-size:0.8rem;"></i>
                            </div>
                            <strong style="color:#1e293b;font-size:0.88rem;">${esc(p.name)}</strong>
                        </div>
                    </td>
                    <td>
                        <span style="background:${tagBg};color:${tagColor};font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px;">
                            ${esc(p.type.toUpperCase())}
                        </span>
                    </td>
                    <td>${rateCell}</td>
                    <td>${sharedCell}</td>
                    <td>${priceCell}</td>
                    <td>${validityCell}</td>
                    ${buildActions(p, routerID)}
                </tr>`;
            }).join('');

            container.innerHTML = `
                <div class="table-responsive profiles-loaded">
                    <table id="datatablesSimple" class="table table-hover align-middle mb-0">
                        <thead style="background:#f8fafc;">
                            <tr style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.6px;color:#94a3b8;">
                                <th class="px-4 py-3">Package Name</th>
                                <th class="py-3">Type</th>
                                <th class="py-3">Rate Limit</th>
                                <th class="py-3">Shared Users</th>
                                <th class="py-3">Price</th>
                                <th class="py-3">Validity</th>
                                ${isStaff ? '' : '<th class="py-3 text-end px-4">Actions</th>'}
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;

            /* Re-apply active filter */
            applyFilter(activeFilter);
        }

        /* ── Apply filter to rendered rows ── */
        function applyFilter(filter) {
            activeFilter = filter;
            document.querySelectorAll('tr[data-profile-type]').forEach(row => {
                row.style.display = (filter === 'all' || row.dataset.profileType === filter) ? '' : 'none';
            });
        }

        /* ── Core fetch function ── */
        function loadProfiles(routerID) {
            if (!routerID) return;
            currentRouterID = routerID;
            showSkeleton();

            fetch(`fetch.php?router_id=${encodeURIComponent(routerID)}`)
                .then(res => {
                    if (!res.ok) throw new Error(`Server error ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    if (data.error) {
                        showError(data.error);
                        resetStats();
                        return;
                    }
                    setStats(data.total, data.hotspotCount, data.pppoeCount);
                    renderTable(data.profiles, routerID);
                })
                .catch(err => {
                    showError('Could not reach server. Check your connection.');
                    resetStats();
                    console.error('loadProfiles error:', err);
                });
        }

        /* ── Filter buttons ── */
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                applyFilter(btn.dataset.filter);
            });
        });

        /* ── Router select change ── */
        if (routerSel) {
            routerSel.addEventListener('change', e => loadProfiles(e.target.value));
        }

        /* ── Retry button ── */
        retryBtn.addEventListener('click', () => loadProfiles(currentRouterID));

        /* ── Modal helpers ── */
        function setVal(id, val) {
            const el = document.getElementById(id);
            if (el) el.value = val;
        }
        function setTxt(id, val) {
            const el = document.getElementById(id);
            if (el) el.innerText = val;
        }

        document.addEventListener('click', function (e) {

            if (e.target.closest('.view-profile')) {
                const btn = e.target.closest('.view-profile');
                setTxt('view-name',     btn.dataset.name);
                setTxt('view-type',     btn.dataset.type.toUpperCase());
                setTxt('view-rate',     btn.dataset.rate);
                setTxt('view-shared',   btn.dataset.shared);
                setTxt('view-price',    btn.dataset.price !== '—' ? 'KES ' + parseFloat(btn.dataset.price).toFixed(2) : '—');
                setTxt('view-validity', btn.dataset.validity);
                new bootstrap.Modal('#viewProfileModal').show();
            }

            if (e.target.closest('.edit-profile')) {
                const btn = e.target.closest('.edit-profile');
                setVal('edit-name',   btn.dataset.name);
                setVal('edit-type',   btn.dataset.type);
                setVal('edit-rate',   btn.dataset.rate);
                setVal('edit-shared', btn.dataset.shared);
                setVal('edit-price',  btn.dataset.price);

                const v = btn.dataset.validity;
                if (v && v !== '—') {
                    const parts = v.split(' ');
                    setVal('edit-validity-days',  parseInt(parts[0]));
                    setVal('edit-validity-hours', parseInt(parts[1]));
                }
                setVal('edit-router', btn.dataset.router);
                new bootstrap.Modal('#editProfileModal').show();
            }

            if (e.target.closest('.delete-profile')) {
                const btn = e.target.closest('.delete-profile');
                setVal('delete-name',   btn.dataset.name);
                setVal('delete-router', btn.dataset.router);
                setVal('delete-type',   btn.dataset.type);
                const label = document.getElementById('delete-profile-name');
                if (label) label.innerText = btn.dataset.name;
                new bootstrap.Modal('#deleteProfileModal').show();
            }
        });

        /* ── Boot: load profiles for initially selected router ── */
        if (currentRouterID) {
            loadProfiles(currentRouterID);
        } else {
            container.innerHTML = `
                <div class="text-center py-5">
                    <p style="color:#94a3b8;font-size:0.9rem;">No routers assigned to your account.</p>
                </div>`;
        }

    })();
    </script>
</body>
</html>