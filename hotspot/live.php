<?php
require_once "../partials/head.php";
require_once "../core/db.php";
require_once "../core/auth.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);

$user_id = $_SESSION['user']['id'];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php"; ?>
    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <header class="bg-primary">
                    <div class="container-xl px-1">
                        <div class="d-flex justify-content-between align-items-center py-3">
                            <h1 class="text-white mb-0 display-6">Live Router Dashboard</h1>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success-soft text-success" id="onlineCount">
                                    <i class="fa fa-circle me-1"></i>0 Online
                                </span>
                                <span id="wsStatusBadge" class="badge bg-warning-soft text-warning">
                                    <i class="fa fa-circle me-1"></i>Connecting...
                                </span>
                                <span class="badge bg-secondary-soft text-secondary" id="lastUpdate">
                                    Updated: --
                                </span>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-4">

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-primary border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Users Online</div>
                                            <div class="h3 mb-0" id="statTotalUsers">0</div>
                                        </div>
                                        <div class="ms-3"><i class="fa fa-users fa-2x text-primary opacity-50"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-success border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Download</div>
                                            <div class="h3 mb-0" id="statTotalDownload">0 bps</div>
                                        </div>
                                        <div class="ms-3"><i class="fa fa-download fa-2x text-success opacity-50"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-info border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Upload</div>
                                            <div class="h3 mb-0" id="statTotalUpload">0 bps</div>
                                        </div>
                                        <div class="ms-3"><i class="fa fa-upload fa-2x text-info opacity-50"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-warning border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Active Routers</div>
                                            <div class="h3 mb-0" id="statActiveRouters">0</div>
                                        </div>
                                        <div class="ms-3"><i class="fa fa-server fa-2x text-warning opacity-50"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Table -->
                    <div class="card card-raised shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><i class="fa fa-signal me-2"></i>Live User Sessions</div>
                                <div class="d-flex align-items-center gap-3">
                                    <input type="text" id="searchInput" class="form-control form-control-sm"
                                        placeholder="Search user / IP / router..."
                                        style="width:220px;background:rgba(255,255,255,0.15);border-color:rgba(255,255,255,0.3);color:#fff;">
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="liveUsersTable" class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fa fa-router me-1"></i>Router</th>
                                            <th><i class="fa fa-user me-1"></i>User / Device</th>
                                            <th><i class="fa fa-network-wired me-1"></i>IP Address</th>
                                            <th><i class="fa fa-id-badge me-1"></i>Profile</th>
                                            <th><i class="fa fa-clock me-1"></i>Uptime</th>
                                            <th><i class="fa fa-tachometer-alt me-1"></i>Speed</th>
                                            <th class="text-center"><i class="fa fa-cog me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="liveTableBody"></tbody>
                                </table>
                            </div>

                            <div id="emptyState" class="text-center py-5" style="display:none;">
                                <i class="fa fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users currently online</p>
                            </div>

                            <div id="connectingState" class="text-center py-5">
                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                <p class="text-muted">Connecting to live feed...</p>
                            </div>
                        </div>
                    </div>

                </div>

            </main>
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    (function () {
        const USER_ID = <?= (int)$user_id ?>;
        const INTERVAL_SEC = 5;
        const MAX_POINTS   = 20;

        let ws             = null;
        let charts         = {};
        let previousBytes  = {};   // key => {rx, tx, time}
        let lastUsers      = {};   // key => user obj
        let searchQuery    = '';

        /* ── Helpers ── */
        function formatSpeed(bps) {
            if (bps >= 1e9) return (bps / 1e9).toFixed(2) + ' Gbps';
            if (bps >= 1e6) return (bps / 1e6).toFixed(2) + ' Mbps';
            if (bps >= 1e3) return (bps / 1e3).toFixed(1) + ' Kbps';
            return Math.round(bps) + ' bps';
        }

        function userKey(u) {
            return `${u.router_id}-${u.username}`;
        }

        /* ── WS status badge ── */
        function setWsStatus(state) {
            const badge = document.getElementById('wsStatusBadge');
            const map = {
                connecting: ['bg-warning-soft text-warning', 'Connecting...'],
                online:     ['bg-success-soft text-success', 'Live'],
                offline:    ['bg-danger-soft text-danger',   'Disconnected'],
            };
            const [cls, label] = map[state] || map.connecting;
            badge.className = `badge ${cls}`;
            badge.innerHTML = `<i class="fa fa-circle me-1"></i>${label}`;
        }

        /* ── Stats bar ── */
        function updateStats(users) {
            const routers = new Set(Object.values(users).map(u => u.router_id));
            let totalDl = 0, totalUl = 0;

            Object.values(users).forEach(u => {
                totalDl += u._rx_bps || 0;
                totalUl += u._tx_bps || 0;
            });

            document.getElementById('statTotalUsers').textContent     = Object.keys(users).length;
            document.getElementById('statActiveRouters').textContent   = routers.size;
            document.getElementById('statTotalDownload').textContent   = formatSpeed(totalDl);
            document.getElementById('statTotalUpload').textContent     = formatSpeed(totalUl);
            document.getElementById('onlineCount').innerHTML           = `<i class="fa fa-circle me-1"></i>${Object.keys(users).length} Online`;
            document.getElementById('lastUpdate').textContent          = `Updated: ${new Date().toLocaleTimeString()}`;
        }

        /* ── Mini chart per user ── */
        function getOrCreateChart(key) {
            if (charts[key]) return charts[key];

            const canvas = document.getElementById(`chart-${key}`);
            if (!canvas) return null;

            charts[key] = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        { label: 'DL', data: [], borderColor: '#00ba88', backgroundColor: 'rgba(0,186,136,0.1)', tension: 0.4, fill: true, borderWidth: 2, pointRadius: 0 },
                        { label: 'UL', data: [], borderColor: '#0061f2', backgroundColor: 'rgba(0,97,242,0.1)',   tension: 0.4, fill: true, borderWidth: 2, pointRadius: 0 },
                    ]
                },
                options: {
                    responsive: false, animation: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { display: false },
                        y: { display: false, beginAtZero: true, suggestedMax: 1 }
                    }
                }
            });

            return charts[key];
        }

        function pushToChart(key, dlMbps, ulMbps) {
            const chart = getOrCreateChart(key);
            if (!chart) return;

            const now = new Date().toLocaleTimeString();
            chart.data.labels.push(now);
            chart.data.datasets[0].data.push(dlMbps);
            chart.data.datasets[1].data.push(ulMbps);

            if (chart.data.labels.length > MAX_POINTS) {
                chart.data.labels.shift();
                chart.data.datasets.forEach(ds => ds.data.shift());
            }

            const maxV = Math.max(...chart.data.datasets[0].data, ...chart.data.datasets[1].data, 0.1);
            chart.options.scales.y.suggestedMax = maxV * 1.2;
            chart.update('none');
        }

        /* ── Compute speeds from cumulative bytes (hotspot) or direct bps (pppoe) ── */
        function computeSpeed(key, u, now) {
            let dlBps = 0, ulBps = 0;

            if (u.type === 'PPPoE') {
                /* server sends live bps directly */
                dlBps = u.rx_bps || 0;
                ulBps = u.tx_bps || 0;
            } else {
                /* Hotspot: derive from cumulative byte delta */
                const prev = previousBytes[key];
                if (prev) {
                    const dt = (now - prev.time) / 1000 || INTERVAL_SEC;
                    dlBps = Math.max(0, ((u.rx_bytes - prev.rx) * 8) / dt);
                    ulBps = Math.max(0, ((u.tx_bytes - prev.tx) * 8) / dt);
                }
                previousBytes[key] = { rx: u.rx_bytes, tx: u.tx_bytes, time: now };
            }

            return { dlBps, ulBps };
        }

        /* ── Build a new row ── */
        function buildRow(key, u) {
            const typeColor = u.type === 'Hotspot' ? 'bg-success-soft text-success' : 'bg-info-soft text-info';
            const row = document.createElement('tr');
            row.dataset.key      = key;
            row.dataset.username = u.username.toLowerCase();
            row.dataset.ip       = (u.ip_address || '').toLowerCase();
            row.dataset.router   = (u.router_name || '').toLowerCase();
            row.classList.add('animate__animated', 'animate__fadeIn');

            row.innerHTML = `
                <td><div class="badge bg-primary-soft text-primary">${u.router_name}</div></td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-2">
                            <div class="avatar-title bg-secondary-soft text-secondary rounded-circle">
                                <i class="fa fa-user"></i>
                            </div>
                        </div>
                        <div>
                            <strong>${u.username}</strong>
                            ${u.device ? `<div class="small text-muted">${u.device}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td><code>${u.ip_address || '—'}</code></td>
                <td>
                    <span class="badge bg-info-soft text-info">${u.profile || 'Default'}</span>
                    <span class="badge ${typeColor} ms-1">${u.type}</span>
                </td>
                <td><i class="fa fa-clock text-muted me-1"></i><small>${u.uptime || '—'}</small></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <canvas id="chart-${key}" width="120" height="40"></canvas>
                        <div>
                            <div class="badge bg-success-soft text-success small mb-1" id="speed-dl-${key}">
                                <i class="fa fa-arrow-down"></i> 0 bps
                            </div>
                            <div class="badge bg-info-soft text-info small" id="speed-ul-${key}">
                                <i class="fa fa-arrow-up"></i> 0 bps
                            </div>
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-danger" onclick="kickUser('${u.router_id}','${u.username}','${u.type}','${u.session_id}')" title="Disconnect">
                        <i class="fa fa-times"></i>
                    </button>
                </td>
            `;

            return row;
        }

        /* ── Main update from WS payload ── */
        function handleLiveUsers(users) {
            const tbody      = document.getElementById('liveTableBody');
            const now        = Date.now();
            const newUserMap = {};

            users.forEach(u => {
                const key = userKey(u);
                newUserMap[key] = u;

                /* Add row if new */
                if (!lastUsers[key]) {
                    const row = buildRow(key, u);
                    tbody.appendChild(row);
                } else {
                    /* Update uptime cell */
                    const row = tbody.querySelector(`tr[data-key="${key}"]`);
                    if (row) {
                        const uptimeCell = row.cells[4];
                        if (uptimeCell) uptimeCell.innerHTML = `<i class="fa fa-clock text-muted me-1"></i><small>${u.uptime || '—'}</small>`;
                    }
                }

                /* Compute & push speed */
                const { dlBps, ulBps } = computeSpeed(key, u, now);
                u._rx_bps = dlBps;
                u._tx_bps = ulBps;

                pushToChart(key, dlBps / 1e6, ulBps / 1e6);

                const dlEl = document.getElementById(`speed-dl-${key}`);
                const ulEl = document.getElementById(`speed-ul-${key}`);
                if (dlEl) dlEl.innerHTML = `<i class="fa fa-arrow-down"></i> ${formatSpeed(dlBps)}`;
                if (ulEl) ulEl.innerHTML = `<i class="fa fa-arrow-up"></i> ${formatSpeed(ulBps)}`;
            });

            /* Remove gone users */
            Object.keys(lastUsers).forEach(key => {
                if (!newUserMap[key]) {
                    const row = tbody.querySelector(`tr[data-key="${key}"]`);
                    if (row) {
                        row.classList.add('animate__fadeOut');
                        setTimeout(() => row.remove(), 500);
                    }
                    if (charts[key]) { charts[key].destroy(); delete charts[key]; }
                    delete previousBytes[key];
                }
            });

            lastUsers = newUserMap;

            /* Empty / connecting state */
            const count = Object.keys(lastUsers).length;
            document.getElementById('connectingState').style.display = 'none';
            document.getElementById('emptyState').style.display      = count === 0 ? '' : 'none';
            tbody.style.display = count === 0 ? 'none' : '';

            /* Search filter */
            applySearch();
            updateStats(lastUsers);
        }

        /* ── Search ── */
        function applySearch() {
            const q = searchQuery.toLowerCase();
            document.querySelectorAll('#liveTableBody tr[data-key]').forEach(row => {
                if (!q) { row.style.display = ''; return; }
                const match = row.dataset.username.includes(q)
                           || row.dataset.ip.includes(q)
                           || row.dataset.router.includes(q);
                row.style.display = match ? '' : 'none';
            });
        }

        document.getElementById('searchInput').addEventListener('input', function() {
            searchQuery = this.value;
            applySearch();
        });

        /* ── Kick ── */
        window.kickUser = function(routerId, username, type, sessionId) {
            if (!confirm(`Disconnect ${username}?`)) return;

            fetch('../monitor/action.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    router_id: parseInt(routerId),
                    action:    type === 'Hotspot' ? 'kick_hotspot' : 'kick_pppoe',
                    id:        sessionId,
                    name:      username,
                })
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) alert(d.message || 'Failed to kick user');
            })
            .catch(() => alert('Request failed'));
        };

        /* ── WebSocket ── */
        function connectWS() {
            setWsStatus('connecting');
            ws = new WebSocket('wss://billing.inovatech.co.ke/ws');

            ws.onopen = () => {
                setWsStatus('online');
                ws.send(JSON.stringify({ type: 'subscribe_live', user_id: USER_ID }));
            };

            ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'live_users') {
                        handleLiveUsers(data.users || []);
                    }
                } catch (e) {
                    console.error('WS parse error:', e);
                }
            };

            ws.onerror = () => setWsStatus('offline');

            ws.onclose = () => {
                setWsStatus('offline');
                ws = null;
                setTimeout(connectWS, 3000);
            };
        }

        connectWS();
    })();
    </script>

</body>
</html>