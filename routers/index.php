<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

$routers = $pdo->query("SELECT * FROM routers ORDER BY name")->fetchAll();
?>

<?php require_once "../partials/head.php" ?>

<body class="nav-fixed bg-light">

    <?php require_once "../partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php" ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Enhanced Header -->
                <header class="bg-primary">
                    <div class="container-xl px-1">
                        <div class="d-flex justify-content-between align-items-center py-3">
                            <h1 class="text-white mb-0 display-6">
                                <i class="fas fa-server me-2"></i>Routers Management
                            </h1>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success-soft text-success" id="onlineRouters">
                                    <i class="fa fa-circle me-1"></i>0 Online
                                </span>
                                <span class="badge bg-danger-soft text-danger" id="offlineRouters">
                                    <i class="fa fa-circle me-1"></i>0 Offline
                                </span>
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addRouterModal">
                                    <i class="fas fa-plus me-1"></i>Add Router
                                </button>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-4">

                    <!-- Alerts -->
                    <?php if (!empty($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= $_SESSION['success'];
                            unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= $_SESSION['error'];
                            unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Cards Row -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card card-raised border-start border-primary border-4">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="small text-muted">Total Routers</div>
                                            <div class="h3 mb-0"><?= count($routers) ?></div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-server fa-2x text-primary opacity-50"></i>
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
                                            <div class="small text-muted">Online</div>
                                            <div class="h3 mb-0" id="statOnline">0</div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-check-circle fa-2x text-success opacity-50"></i>
                                        </div>
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
                                        <div class="ms-3">
                                            <i class="fas fa-times-circle fa-2x text-danger opacity-50"></i>
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
                                            <div class="small text-muted">Avg Speed</div>
                                            <div class="h3 mb-0" id="statAvgSpeed">0 Mbps</div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fas fa-tachometer-alt fa-2x text-info opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Card -->
                    <div class="card card-raised shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-list me-2"></i>Router List
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="autoRefreshToggle" checked>
                                    <label class="form-check-label text-white" for="autoRefreshToggle">
                                        Auto-refresh (5s)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-tag me-1"></i>Name</th>
                                            <th><i class="fas fa-network-wired me-1"></i>Host</th>
                                            <th><i class="fas fa-signal me-1"></i>Status</th>
                                            <th><i class="fas fa-clock me-1"></i>Last Seen</th>
                                            <th><i class="fas fa-tachometer-alt me-1"></i>Network Speed</th>
                                            <th class="text-end"><i class="fas fa-cog me-1"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($routers as $router): ?>
                                            <tr data-router-id="<?= $router['router_id'] ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2">
                                                            <div class="avatar-title bg-primary-soft text-primary rounded-circle">
                                                                <i class="fas fa-server"></i>
                                                            </div>
                                                        </div>
                                                        <strong><?= htmlspecialchars($router['name']) ?></strong>
                                                    </div>
                                                </td>
                                                <td><code class="text-muted"><?= htmlspecialchars($router['host']) ?></code></td>
                                                <td>
                                                    <span class="badge bg-secondary status-badge">
                                                        <i class="fas fa-spinner fa-spin me-1"></i>Checking...
                                                    </span>
                                                </td>
                                                <td class="last-seen">
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= $router['last_seen'] ?? '—' ?>
                                                    </small>
                                                </td>
                                                <td class="router-speed">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <canvas id="speedChart-<?= $router['router_id'] ?>" width="120" height="40"></canvas>
                                                        <div>
                                                            <div class="badge bg-info-soft text-info" id="currentSpeed-<?= $router['router_id'] ?>">
                                                                <i class="fas fa-sync-alt fa-spin"></i> Loading...
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary view-router" data-id="<?= $router['router_id'] ?>" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-secondary edit-router" data-id="<?= $router['router_id'] ?>" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger delete-router" data-id="<?= $router['router_id'] ?>" title="Delete">
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
                            <?php if (empty($routers)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-server fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No routers configured yet</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRouterModal">
                                        <i class="fas fa-plus me-1"></i>Add Your First Router
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

    <!-- Router Speed Chart Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const charts = {};
            const autoRefreshToggle = document.getElementById("autoRefreshToggle");
            let refreshInterval;

            function formatSpeed(bitsPerSecond) {
                const units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
                let speed = bitsPerSecond;
                let unitIndex = 0;

                while (speed >= 1000 && unitIndex < units.length - 1) {
                    speed /= 1000;
                    unitIndex++;
                }

                const decimals = speed < 10 ? 2 : 1;
                return `${speed.toFixed(decimals)} ${units[unitIndex]}`;
            }

            // Initialize charts
            document.querySelectorAll("tr[data-router-id]").forEach(row => {
                const routerId = row.dataset.routerId;
                const canvas = document.getElementById(`speedChart-${routerId}`);
                if (!canvas) return;
                const ctx = canvas.getContext('2d');

                charts[routerId] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                                label: 'Download',
                                data: [],
                                borderColor: '#00ba88',
                                backgroundColor: 'rgba(0,186,136,0.15)',
                                tension: 0.4,
                                fill: true,
                                pointRadius: 0
                            },
                            {
                                label: 'Upload',
                                data: [],
                                borderColor: '#0061f2',
                                backgroundColor: 'rgba(0,97,242,0.15)',
                                tension: 0.4,
                                fill: true,
                                pointRadius: 0
                            }
                        ]
                    },
                    options: {
                        responsive: false,
                        animation: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                display: false
                            },
                            y: {
                                beginAtZero: true,
                                display: false
                            }
                        }
                    }
                });

            });

            function updateStats() {
                let onlineCount = 0;
                let offlineCount = 0;
                let totalSpeed = 0;
                let speedCount = 0;

                document.querySelectorAll("tr[data-router-id]").forEach(row => {
                    const badge = row.querySelector(".status-badge");
                    if (badge.textContent.includes("Online")) {
                        onlineCount++;
                    } else if (badge.textContent.includes("Offline")) {
                        offlineCount++;
                    }

                    const routerId = row.dataset.routerId;
                    const chart = charts[routerId];
                    if (chart && chart.data.datasets[0].data.length > 0) {
                        const lastSpeed = chart.data.datasets[0].data[chart.data.datasets[0].data.length - 1];
                        totalSpeed += lastSpeed;
                        speedCount++;
                    }
                });

                document.getElementById("statOnline").textContent = onlineCount;
                document.getElementById("statOffline").textContent = offlineCount;
                document.getElementById("onlineRouters").innerHTML = `<i class="fa fa-circle me-1"></i>${onlineCount} Online`;
                document.getElementById("offlineRouters").innerHTML = `<i class="fa fa-circle me-1"></i>${offlineCount} Offline`;

                const avgSpeed = speedCount > 0 ? totalSpeed / speedCount : 0;
                document.getElementById("statAvgSpeed").textContent = formatSpeed(avgSpeed * 1e6);
            }

            function updateSpeeds() {
                document.querySelectorAll("tr[data-router-id]").forEach(row => {
                    const routerId = row.dataset.routerId;
                    const badge = row.querySelector(".status-badge");
                    const lastSeen = row.querySelector(".last-seen");

                    fetch(`./test_speed.php?id=${routerId}`)
                        .then(res => res.json())
                        .then(data => {
                            // Update status badge
                            if (!data.online) {
                                badge.innerHTML = '<i class="fas fa-times-circle me-1"></i>Offline';
                                badge.className = "badge status-badge bg-danger";
                            } else if (data.idle) {
                                badge.innerHTML = '<i class="fas fa-pause-circle me-1"></i>Idle';
                                badge.className = "badge status-badge bg-warning text-dark";
                            } else {
                                badge.innerHTML = '<i class="fas fa-check-circle me-1"></i>Online';
                                badge.className = "badge status-badge bg-success";
                            }


                            lastSeen.innerHTML = `<small class="text-muted"><i class="fas fa-clock me-1"></i>${data.last_seen ?? '—'}</small>`;

                            const chart = charts[routerId];
                            if (!chart) return;

                            const now = new Date().toLocaleTimeString();
                            const speedValue = parseFloat(data.speed) || 0;

                            // Update chart
                            const down = parseFloat(data.download) || 0;
                            const up = parseFloat(data.upload) || 0;

                            chart.data.labels.push(now);
                            chart.data.datasets[0].data.push(down);
                            chart.data.datasets[1].data.push(up);

                            if (chart.data.labels.length > 20) {
                                chart.data.labels.shift();
                                chart.data.datasets.forEach(ds => ds.data.shift());
                            }




                            // Dynamic color based on speed
                            if (speedValue >= 50) {
                                chart.data.datasets[0].borderColor = '#00ba88';
                                chart.data.datasets[0].backgroundColor = 'rgba(0, 186, 136, 0.1)';
                            } else if (speedValue >= 20) {
                                chart.data.datasets[0].borderColor = '#f4a100';
                                chart.data.datasets[0].backgroundColor = 'rgba(244, 161, 0, 0.1)';
                            } else {
                                chart.data.datasets[0].borderColor = '#e81500';
                                chart.data.datasets[0].backgroundColor = 'rgba(232, 21, 0, 0.1)';
                            }

                            // Auto-scale Y axis
                            const maxSpeed = Math.max(
                                ...chart.data.datasets[0].data,
                                ...chart.data.datasets[1].data
                            );
                            chart.options.scales.y.suggestedMax = Math.max(maxSpeed * 1.3, 5);
                            chart.update();

                            // Update speed display with dynamic units
                            const currentSpeedEl = document.getElementById(`currentSpeed-${routerId}`);
                            const speedBps = speedValue * 1e6; // Convert Mbps to bps
                            currentSpeedEl.innerHTML = `<i class="fas fa-tachometer-alt me-1"></i>${formatSpeed(speedBps)}`;

                            // Update badge color based on speed
                            if (speedValue >= 50) {
                                currentSpeedEl.className = "badge bg-success-soft text-success";
                            } else if (speedValue >= 20) {
                                currentSpeedEl.className = "badge bg-warning-soft text-warning";
                            } else {
                                currentSpeedEl.className = "badge bg-danger-soft text-danger";
                            }

                            updateStats();
                        })
                        .catch(err => {
                            console.error(err);
                            badge.innerHTML = '<i class="fas fa-times-circle me-1"></i>Error';
                            badge.className = "badge status-badge bg-danger";
                            const currentSpeedEl = document.getElementById(`currentSpeed-${routerId}`);
                            currentSpeedEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Error';
                            currentSpeedEl.className = "badge bg-danger-soft text-danger";
                            updateStats();
                        });
                });
            }

            // Auto-refresh toggle
            autoRefreshToggle.addEventListener("change", function() {
                if (this.checked) {
                    refreshInterval = setInterval(updateSpeeds, 5000);
                } else {
                    clearInterval(refreshInterval);
                }
            });

            updateSpeeds();
            refreshInterval = setInterval(updateSpeeds, 5000);
        });
    </script>

    <!-- Action Buttons -->
    <script>
        document.addEventListener("click", e => {
            if (e.target.closest(".delete-router")) {
                const id = e.target.closest(".delete-router").dataset.id;
                if (confirm("Are you sure you want to delete this router?")) {
                    window.location.href = `delete.php?id=${id}`;
                }
            }
        });
    </script>

</body>

</html>

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
                                <strong>Note:</strong> Ensure the API service is enabled on your MikroTik router and the credentials have sufficient permissions.
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