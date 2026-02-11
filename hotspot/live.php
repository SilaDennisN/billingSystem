<?php
require_once "../partials/head.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}
?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php"; ?>
    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Enhanced Live Dashboard with Material Admin Pro components -->

                <header class="bg-primary">
                    <div class="container-xl px-1">
                        <div class="d-flex justify-content-between align-items-center py-3">
                            <h1 class="text-white mb-0 display-6">Live Router Dashboard</h1>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success-soft text-success" id="onlineCount">
                                    <i class="fa fa-circle me-1"></i>0 Online
                                </span>
                                <span class="badge bg-secondary-soft text-secondary" id="lastUpdate">
                                    Updated: --
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
                                            <div class="small text-muted">Total Users Online</div>
                                            <div class="h3 mb-0" id="statTotalUsers">0</div>
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
                                            <div class="small text-muted">Total Download</div>
                                            <div class="h3 mb-0" id="statTotalDownload">0 Mbps</div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fa fa-download fa-2x text-success opacity-50"></i>
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
                                            <div class="small text-muted">Total Upload</div>
                                            <div class="h3 mb-0" id="statTotalUpload">0 Mbps</div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fa fa-upload fa-2x text-info opacity-50"></i>
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
                                            <div class="small text-muted">Active Routers</div>
                                            <div class="h3 mb-0" id="statActiveRouters">0</div>
                                        </div>
                                        <div class="ms-3">
                                            <i class="fa fa-server fa-2x text-warning opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Table Card -->
                    <div class="card card-raised shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fa fa-signal me-2"></i>Live User Sessions
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
                                <table id="liveUsersTable" class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fa fa-router me-1"></i>Router</th>
                                            <th><i class="fa fa-user me-1"></i>User / Device</th>
                                            <th><i class="fa fa-network-wired me-1"></i>IP Address</th>
                                            <th><i class="fa fa-id-badge me-1"></i>Profile</th>
                                            <th><i class="fa fa-clock me-1"></i>Uptime</th>
                                            <th><i class="fa fa-tachometer-alt me-1"></i>Speed & Usage</th>
                                            <th class="text-center"><i class="fa fa-cog me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div id="emptyState" class="text-center py-5" style="display: none;">
                                <i class="fa fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No users currently online</p>
                            </div>
                        </div>
                    </div>

                </div>

                <script>
                    document.addEventListener("DOMContentLoaded", () => {
                        const charts = {};
                        const tableBody = document.querySelector("#liveUsersTable tbody");
                        const emptyState = document.getElementById("emptyState");
                        const autoRefreshToggle = document.getElementById("autoRefreshToggle");
                        let activeUsers = {};
                        let refreshInterval;
                        const intervalSec = 5;

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

                        function updateStats() {
                            const userCount = Object.keys(activeUsers).length;
                            const routerSet = new Set();
                            let totalDl = 0;
                            let totalUl = 0;

                            Object.values(activeUsers).forEach(u => {
                                routerSet.add(u.router_id);
                            });

                            // Calculate from chart data
                            Object.values(charts).forEach(chart => {
                                if (chart.data.datasets[0].data.length > 0) {
                                    totalDl += chart.data.datasets[0].data[chart.data.datasets[0].data.length - 1] || 0;
                                    totalUl += chart.data.datasets[1].data[chart.data.datasets[1].data.length - 1] || 0;
                                }
                            });

                            document.getElementById("statTotalUsers").textContent = userCount;
                            document.getElementById("statTotalDownload").textContent = formatSpeed(totalDl * 1e6);
                            document.getElementById("statTotalUpload").textContent = formatSpeed(totalUl * 1e6);
                            document.getElementById("statActiveRouters").textContent = routerSet.size;
                            document.getElementById("onlineCount").innerHTML = `<i class="fa fa-circle me-1"></i>${userCount} Online`;

                            const now = new Date().toLocaleTimeString();
                            document.getElementById("lastUpdate").textContent = `Updated: ${now}`;
                        }

                        async function fetchLiveUsers() {
                            try {
                                const res = await fetch("../core/get_live_users_json.php");
                                const data = await res.json();

                                if (!Array.isArray(data)) {
                                    console.warn("Live users response not array", data);
                                    return;
                                }

                                const newActiveUsers = {};

                                data.forEach(u => {
                                    const key = `${u.router_id}-${u.username}`;
                                    newActiveUsers[key] = u;

                                    let row = document.querySelector(`tr[data-key="${key}"]`);
                                    if (!row) {
                                        row = document.createElement("tr");
                                        row.dataset.key = key;
                                        row.classList.add("animate__animated", "animate__fadeIn");

                                        row.innerHTML = `
                        <td>
                            <div class="badge bg-primary-soft text-primary">${u.router_name}</div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm me-2">
                                    <div class="avatar-title bg-secondary-soft text-secondary rounded-circle">
                                        <i class="fa fa-user"></i>
                                    </div>
                                </div>
                                <strong>${u.username}</strong>
                            </div>
                        </td>
                        <td><code>${u.ip_address}</code></td>
                        <td><span class="badge bg-info-soft text-info">${u.profile || 'Default'}</span></td>
                        <td>
                            <i class="fa fa-clock text-muted me-1"></i>
                            <small>${u.uptime}</small>
                        </td>
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
                            <form method="POST" action="../hotspot/live/kick.php" style="display: inline;">
                                <input type="hidden" name="router_id" value="${u.router_id}">
                                <input type="hidden" name="username" value="${u.username}">
                                <button class="btn btn-sm btn-danger" title="Disconnect User">
                                    <i class="fa fa-times"></i>
                                </button>
                            </form>
                        </td>
                    `;

                                        tableBody.appendChild(row);

                                        const ctx = document.getElementById(`chart-${key}`).getContext("2d");
                                        charts[key] = new Chart(ctx, {
                                            type: "line",
                                            data: {
                                                labels: [],
                                                datasets: [{
                                                        label: "Download",
                                                        data: [],
                                                        borderColor: "#00ba88",
                                                        backgroundColor: "rgba(0, 186, 136, 0.1)",
                                                        tension: 0.4,
                                                        fill: true,
                                                        borderWidth: 2,
                                                        pointRadius: 0
                                                    },
                                                    {
                                                        label: "Upload",
                                                        data: [],
                                                        borderColor: "#0061f2",
                                                        backgroundColor: "rgba(0, 97, 242, 0.1)",
                                                        tension: 0.4,
                                                        fill: true,
                                                        borderWidth: 2,
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
                                                        suggestedMax: 10,
                                                        display: false
                                                    }
                                                }
                                            }
                                        });
                                    }
                                });

                                // Remove offline users
                                Object.keys(activeUsers).forEach(key => {
                                    if (!newActiveUsers[key]) {
                                        const row = document.querySelector(`tr[data-key="${key}"]`);
                                        if (row) {
                                            row.classList.add("animate__fadeOut");
                                            setTimeout(() => row.remove(), 500);
                                        }
                                        delete charts[key];
                                    }
                                });

                                activeUsers = newActiveUsers;

                                // Toggle empty state
                                if (Object.keys(activeUsers).length === 0) {
                                    tableBody.style.display = "none";
                                    emptyState.style.display = "block";
                                } else {
                                    tableBody.style.display = "";
                                    emptyState.style.display = "none";
                                }

                                updateStats();

                            } catch (err) {
                                console.error("fetchLiveUsers failed", err);
                            }
                        }

                        const previousBytes = {};

                        async function updateSpeeds() {
                            for (const key in activeUsers) {
                                const u = activeUsers[key];

                                const res = await fetch(
                                    `../routers/test_speed_user.php?router_id=${u.router_id}&username=${u.username}`
                                );
                                const data = await res.json();
                                if (data.status !== "Online") continue;

                                const prev = previousBytes[key] || {
                                    rx: data.rx_bytes,
                                    tx: data.tx_bytes
                                };

                                const rxDelta = data.rx_bytes - prev.rx;
                                const txDelta = data.tx_bytes - prev.tx;

                                previousBytes[key] = {
                                    rx: data.rx_bytes,
                                    tx: data.tx_bytes
                                };

                                const dlBps = (rxDelta * 8) / intervalSec;
                                const ulBps = (txDelta * 8) / intervalSec;

                                const dlMbps = dlBps / 1e6;
                                const ulMbps = ulBps / 1e6;

                                const chart = charts[key];
                                if (!chart) continue;

                                const now = new Date().toLocaleTimeString();
                                chart.data.labels.push(now);
                                chart.data.datasets[0].data.push(dlMbps);
                                chart.data.datasets[1].data.push(ulMbps);

                                if (chart.data.labels.length > 20) {
                                    chart.data.labels.shift();
                                    chart.data.datasets.forEach(ds => ds.data.shift());
                                }

                                const maxSpeed = Math.max(...chart.data.datasets[0].data, ...chart.data.datasets[1].data);
                                chart.options.scales.y.suggestedMax = Math.max(maxSpeed * 1.2, 1);

                                chart.update();

                                const dlElement = document.getElementById(`speed-dl-${key}`);
                                const ulElement = document.getElementById(`speed-ul-${key}`);

                                if (dlElement) {
                                    dlElement.innerHTML = `<i class="fa fa-arrow-down"></i> ${formatSpeed(dlBps)}`;
                                }
                                if (ulElement) {
                                    ulElement.innerHTML = `<i class="fa fa-arrow-up"></i> ${formatSpeed(ulBps)}`;
                                }
                            }

                            updateStats();
                        }

                        async function refresh() {
                            await fetchLiveUsers();
                            await updateSpeeds();
                        }

                        // Auto-refresh toggle
                        autoRefreshToggle.addEventListener("change", function() {
                            if (this.checked) {
                                refreshInterval = setInterval(refresh, intervalSec * 1000);
                            } else {
                                clearInterval(refreshInterval);
                            }
                        });

                        refresh();
                        refreshInterval = setInterval(refresh, intervalSec * 1000);
                    });
                </script>

                <!-- Add this to your CSS or include animate.css -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

            </main>
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
</body>

</html>