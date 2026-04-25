<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);

$stmt = $pdo->prepare("
    SELECT r.*
    FROM routers r
    INNER JOIN user_router_access ura
        ON ura.router_id = r.router_id
    WHERE ura.user_id = ?
    ORDER BY r.name
");
$stmt->execute([$user_id]);
$routers = $stmt->fetchAll();
?>

<?php require_once "../partials/head.php" ?>

<body class="nav-fixed bg-light">

    <?php require_once "../partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php" ?>

        <div id="layoutDrawer_content">
            <main>

                <header class="bg-primary">
                    <div class="container-xl px-1">
                        <div class="d-flex justify-content-between align-items-center py-3">
                            <h1 class="text-white mb-0 display-6">
                                <i class="fas fa-server me-2"></i>Routers Management
                            </h1>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success-soft text-success" id="onlineRouters">
                                    <i class="fa fa-circle me-1"></i>0 Online
                                <!-- </span>
                                <span class="badge bg-danger-soft text-danger" id="offlineRouters">
                                    <i class="fa fa-circle me-1"></i>0 Offline
                                </span> -->
                                <span id="wsStatusBadge" class="badge bg-warning-soft text-warning">
                                    <i class="fa fa-circle me-1"></i>Connecting...
                                </span>
                                <?php if ($_SESSION['user']['role'] !== "staff"): ?>
                                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addRouterModal">
                                        <i class="fa fa-plus me-1"></i>Add Router
                                    </button>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-4">

                    <?php if (!empty($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-primary border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Routers</div>
                                            <div class="h3 mb-0"><?= count($routers) ?></div>
                                        </div>
                                        <div class="ms-3"><i class="fa fa-server fa-2x text-primary opacity-50"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-success border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Online</div>
                                            <div class="h3 mb-0" id="statOnline">0</div>
                                        </div>
                                        <div class="ms-3"><i class="fa fa-check-circle fa-2x text-success opacity-50"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-danger border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Offline</div>
                                            <div class="h3 mb-0" id="statOffline">0</div>
                                        </div>
                                        <div class="ms-3"><i class="fa fa-times-circle fa-2x text-danger opacity-50"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-info border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Throughput</div>
                                            <div class="h3 mb-0" id="statAvgSpeed">0 Mbps</div>
                                        </div>
                                        <div class="ms-3"><i class="fa fa-tachometer-alt fa-2x text-info opacity-50"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Router Table -->
                    <div class="card card-raised shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><i class="fas fa-list me-2"></i>Router List</div>
                                <small class="text-white-50">
                                    <i class="fa fa-circle-notch fa-spin me-1" id="liveSpinner"></i>
                                    Live updates every 5s
                                </small>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="datatablesSimple" class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fa fa-tag me-1"></i>Name</th>
                                            <th><i class="fa fa-network-wired me-1"></i>Host</th>
                                            <th><i class="fa fa-signal me-1"></i>Status</th>
                                            <th><i class="fa fa-microchip me-1"></i>CPU</th>
                                            <th><i class="fa fa-memory me-1"></i>RAM</th>
                                            <th><i class="fa fa-clock me-1"></i>Uptime</th>
                                            <th><i class="fa fa-tachometer-alt me-1"></i>Throughput</th>
                                            <?php if ($_SESSION['user']['role'] !== "staff"): ?>
                                                <th class="text-end"><i class="fas fa-cog me-1"></i>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($routers as $router): ?>
                                            <tr data-router-id="<?= $router['router_id'] ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2">
                                                            <div class="avatar-title bg-primary-soft text-primary rounded-circle">
                                                                <i class="fa fa-server"></i>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($router['name']) ?></strong>
                                                            <div class="small text-muted" id="hostname-<?= $router['router_id'] ?>"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><code class="text-muted"><?= htmlspecialchars($router['host']) ?></code></td>
                                                <td>
                                                    <span class="badge bg-secondary status-badge" id="status-<?= $router['router_id'] ?>">
                                                        <i class="fa fa-spinner fa-spin me-1"></i>Checking...
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2" style="min-width:80px;">
                                                        <div class="progress flex-grow-1" style="height:6px;">
                                                            <div class="progress-bar bg-primary" id="cpuBar-<?= $router['router_id'] ?>" style="width:0%;transition:width .4s;"></div>
                                                        </div>
                                                        <small id="cpuVal-<?= $router['router_id'] ?>" class="text-muted">—</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2" style="min-width:80px;">
                                                        <div class="progress flex-grow-1" style="height:6px;">
                                                            <div class="progress-bar bg-success" id="memBar-<?= $router['router_id'] ?>" style="width:0%;transition:width .4s;"></div>
                                                        </div>
                                                        <small id="memVal-<?= $router['router_id'] ?>" class="text-muted">—</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small class="text-muted" id="uptime-<?= $router['router_id'] ?>">—</small>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <canvas id="speedChart-<?= $router['router_id'] ?>" width="120" height="40"></canvas>
                                                        <div>
                                                            <div class="badge bg-success-soft text-success small mb-1" id="speed-dl-<?= $router['router_id'] ?>">
                                                                <i class="fa fa-arrow-down"></i> —
                                                            </div>
                                                            <div class="badge bg-info-soft text-info small" id="speed-ul-<?= $router['router_id'] ?>">
                                                                <i class="fa fa-arrow-up"></i> —
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <?php if ($_SESSION['user']['role'] !== "staff"): ?>
                                                    <td class="text-end">
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary view-router"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#viewRouterModal"
                                                                data-id="<?= $router['router_id'] ?>"
                                                                data-name="<?= htmlspecialchars($router['name']) ?>">
                                                                <i class="fa fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-outline-secondary edit-router"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editRouterModal"
                                                                data-id="<?= $router['router_id'] ?>"
                                                                data-name="<?= htmlspecialchars($router['name']) ?>"
                                                                data-host="<?= htmlspecialchars($router['host']) ?>"
                                                                data-port="<?= $router['api_port'] ?>">
                                                                <i class="fa fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger delete-router"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteRouterModal"
                                                                data-id="<?= $router['router_id'] ?>"
                                                                data-name="<?= htmlspecialchars($router['name']) ?>">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (empty($routers)): ?>
                                <div class="text-center py-5">
                                    <i class="fa fa-server fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No routers configured yet</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRouterModal">
                                        <i class="fa fa-plus me-1"></i>Add Your First Router
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </main>
            <?php require_once "../partials/footer.php" ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php" ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    (function () {
        const MAX_POINTS = 20;
        const charts     = {};
        const routerIds  = [];

        /* ── Collect router IDs from the table ── */
        document.querySelectorAll('tr[data-router-id]').forEach(row => {
            routerIds.push(parseInt(row.dataset.routerId));
        });

        /* ── Format helpers ── */
        function fmtSpeed(bps) {
            if (bps >= 1e9) return (bps / 1e9).toFixed(2) + ' Gbps';
            if (bps >= 1e6) return (bps / 1e6).toFixed(2) + ' Mbps';
            if (bps >= 1e3) return (bps / 1e3).toFixed(1) + ' Kbps';
            return Math.round(bps) + ' bps';
        }

        function speedColor(mbps) {
            if (mbps >= 50) return { border: '#00ba88', bg: 'rgba(0,186,136,0.12)' };
            if (mbps >= 10) return { border: '#f4a100', bg: 'rgba(244,161,0,0.12)' };
            return             { border: '#e81500', bg: 'rgba(232,21,0,0.12)' };
        }

        /* ── Init a mini chart per router ── */
        function initChart(routerId) {
            const canvas = document.getElementById(`speedChart-${routerId}`);
            if (!canvas || charts[routerId]) return;

            charts[routerId] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        { label: 'DL', data: [], borderColor: '#00ba88', backgroundColor: 'rgba(0,186,136,0.12)', tension: 0.4, fill: true, borderWidth: 2, pointRadius: 0 },
                        { label: 'UL', data: [], borderColor: '#0061f2', backgroundColor: 'rgba(0,97,242,0.12)',   tension: 0.4, fill: true, borderWidth: 2, pointRadius: 0 },
                    ]
                },
                options: {
                    responsive: false, animation: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { display: false },
                        y: { display: false, beginAtZero: true, suggestedMax: 5 }
                    }
                }
            });
        }

        routerIds.forEach(initChart);

        /* ── Update one router row from WS data ── */
        function updateRow(id, d) {
            const rid = parseInt(id);

            /* Status badge */
            const badge = document.getElementById(`status-${rid}`);
            if (badge) {
                if (d.status === 'offline' || d.status === 'error') {
                    badge.innerHTML  = '<i class="fa fa-times-circle me-1"></i>Offline';
                    badge.className  = 'badge status-badge bg-danger';
                } else {
                    badge.innerHTML  = '<i class="fa fa-check-circle me-1"></i>Online';
                    badge.className  = 'badge status-badge bg-success';
                }
            }

            if (d.status === 'offline' || d.status === 'error') return;

            /* CPU bar */
            const cpuBar = document.getElementById(`cpuBar-${rid}`);
            const cpuVal = document.getElementById(`cpuVal-${rid}`);
            if (cpuBar) cpuBar.style.width = (d.cpu || 0) + '%';
            if (cpuBar) cpuBar.className   = `progress-bar ${d.cpu > 80 ? 'bg-danger' : d.cpu > 50 ? 'bg-warning' : 'bg-primary'}`;
            if (cpuVal) cpuVal.textContent = (d.cpu || 0) + '%';

            /* RAM bar */
            const memBar = document.getElementById(`memBar-${rid}`);
            const memVal = document.getElementById(`memVal-${rid}`);
            if (memBar) memBar.style.width = (d.memory || 0) + '%';
            if (memBar) memBar.className   = `progress-bar ${d.memory > 85 ? 'bg-danger' : d.memory > 60 ? 'bg-warning' : 'bg-success'}`;
            if (memVal) memVal.textContent = (d.memory || 0) + '%';

            /* Uptime */
            const uptime = document.getElementById(`uptime-${rid}`);
            if (uptime) uptime.textContent = d.uptime || '—';

            /* Hostname */
            const hn = document.getElementById(`hostname-${rid}`);
            if (hn && d.hostname) hn.textContent = d.hostname;

            /* Speed chart */
            const chart = charts[rid];
            if (!chart) return;

            const rxMbps = parseFloat(d.rx) || 0;
            const txMbps = parseFloat(d.tx) || 0;
            const now    = new Date().toLocaleTimeString();

            chart.data.labels.push(now);
            chart.data.datasets[0].data.push(rxMbps);
            chart.data.datasets[1].data.push(txMbps);

            if (chart.data.labels.length > MAX_POINTS) {
                chart.data.labels.shift();
                chart.data.datasets.forEach(ds => ds.data.shift());
            }

            /* Dynamic colour based on download speed */
            const col = speedColor(rxMbps);
            chart.data.datasets[0].borderColor      = col.border;
            chart.data.datasets[0].backgroundColor  = col.bg;

            const maxV = Math.max(...chart.data.datasets[0].data, ...chart.data.datasets[1].data, 1);
            chart.options.scales.y.suggestedMax = maxV * 1.3;
            chart.update('none');

            /* Speed badges */
            const dlEl = document.getElementById(`speed-dl-${rid}`);
            const ulEl = document.getElementById(`speed-ul-${rid}`);
            if (dlEl) dlEl.innerHTML = `<i class="fa fa-arrow-down"></i> ${fmtSpeed(rxMbps * 1e6)}`;
            if (ulEl) ulEl.innerHTML = `<i class="fa fa-arrow-up"></i> ${fmtSpeed(txMbps * 1e6)}`;

            updateStats();
        }

        /* ── Header stats ── */
        function updateStats() {
            let online = 0, offline = 0, totalRx = 0;

            document.querySelectorAll('tr[data-router-id]').forEach(row => {
                const rid   = row.dataset.routerId;
                const badge = document.getElementById(`status-${rid}`);
                if (!badge) return;

                if (badge.classList.contains('bg-success')) { online++; }
                else if (badge.classList.contains('bg-danger'))  { offline++; }

                const chart = charts[rid];
                if (chart && chart.data.datasets[0].data.length) {
                    totalRx += chart.data.datasets[0].data.at(-1) || 0;
                }
            });

            document.getElementById('statOnline').textContent  = online;
            document.getElementById('statOffline').textContent = offline;
            document.getElementById('onlineRouters').innerHTML = `<i class="fa fa-circle me-1"></i>${online} Online`;
            document.getElementById('offlineRouters').innerHTML= `<i class="fa fa-circle me-1"></i>${offline} Offline`;
            document.getElementById('statAvgSpeed').textContent= fmtSpeed(totalRx * 1e6);
        }

        /* ── WS status badge ── */
        function setWsStatus(state) {
            const badge = document.getElementById('wsStatusBadge');
            const spinner = document.getElementById('liveSpinner');
            const map = {
                connecting: ['bg-warning-soft text-warning', 'Connecting...', true],
                online:     ['bg-success-soft text-success', 'Live',          true],
                offline:    ['bg-danger-soft text-danger',   'Disconnected',  false],
            };
            const [cls, label, spin] = map[state] || map.connecting;
            badge.className  = `badge ${cls}`;
            badge.innerHTML  = `<i class="fa fa-circle me-1"></i>${label}`;
            if (spinner) spinner.style.display = spin ? '' : 'none';
        }

        /* ── WebSocket — one connection, subscribe to all routers ── */
        let ws         = null;
        let pendingSubs = [...routerIds];  // subscribe after open

        function connectWS() {
            setWsStatus('connecting');
            ws = new WebSocket('wss://billing.inovatech.co.ke/ws');

            ws.onopen = () => {
                setWsStatus('online');
                /* Subscribe to every router this user has */
                pendingSubs.forEach(rid => {
                    ws.send(JSON.stringify({ type: 'subscribe', router_id: rid }));
                });
            };

            ws.onmessage = (event) => {
                try {
                    const d = JSON.parse(event.data);
                    /* d comes from getRouterStats — it has router_id? No —
                       the server broadcasts per router_id subscription,
                       so we need to know which router sent this.
                       The server needs to include router_id in the payload. */
                    if (d.router_id) {
                        updateRow(d.router_id, d);
                    }
                } catch(e) {}
            };

            ws.onerror = () => setWsStatus('offline');

            ws.onclose = () => {
                setWsStatus('offline');
                ws = null;
                setTimeout(connectWS, 3000);
            };
        }

        if (routerIds.length) connectWS();

    })();
    </script>

    <!-- Action Buttons -->
    <script>
        document.addEventListener("click", function(e) {
            if (e.target.closest(".view-router")) {
                let btn = e.target.closest(".view-router");
                let id  = btn.dataset.id;
                document.getElementById("routerModalName").innerText = btn.dataset.name;
                fetch("router_info.php?id=" + id)
                    .then(res => res.json())
                    .then(data => {
                        routerModel.innerText   = data.model;
                        routerFirmware.innerText = data.version;
                        routerUptime.innerText  = data.uptime;
                        routerCPU.innerText     = data.cpu + "%";
                        let used    = data.total_memory - data.free_memory;
                        let percent = (used / data.total_memory) * 100;
                        ramProgress.style.width  = percent + "%";
                        ramProgress.innerText    = percent.toFixed(1) + "%";
                        ramTotal.innerText       = (data.total_memory / 1024 / 1024).toFixed(1) + " MB";
                        ramFree.innerText        = (data.free_memory  / 1024 / 1024).toFixed(1) + " MB";
                    });
            }
        });
    </script>

</body>
</html>

<?php require_once "router_modals.php"; ?>

<!-- Add Router Modal -->
<div class="modal fade" id="addRouterModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="store">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Router</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-tag me-1"></i>Router Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Main Router" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-network-wired me-1"></i>Host / IP Address</label>
                            <input type="text" name="host" class="form-control" placeholder="192.168.1.1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-plug me-1"></i>API Port</label>
                            <input type="number" name="api_port" class="form-control" value="8728" required>
                            <small class="text-muted">Default: 8728</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-user me-1"></i>API Username</label>
                            <input type="text" name="api_user" class="form-control" placeholder="admin" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><i class="fas fa-key me-1"></i>API Password</label>
                            <input type="password" name="api_pass" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Ensure the API service is enabled on your MikroTik and the credentials have sufficient permissions.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i>Save & Test Connection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>