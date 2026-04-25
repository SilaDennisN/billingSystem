<?php
require_once __DIR__ . '/../core/db.php';
require_once "../core/auth.php";
require_once "../partials/head.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);

$user_id = $_SESSION['user']['id'];

$stmt = $pdo->prepare("
    SELECT r.router_id, r.name 
    FROM routers r
    INNER JOIN user_router_access ura ON ura.router_id = r.router_id
    WHERE r.status = 'active' AND ura.user_id = ?
    ORDER BY r.name
");
$stmt->execute([$user_id]);
$userRouters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.monitor-tab{cursor:pointer;padding:8px 18px;border-radius:8px;font-size:0.8rem;font-weight:600;color:#64748b;border:none;background:transparent;transition:all .2s;white-space:nowrap;}
.monitor-tab:hover{background:#f1f5f9;color:#1e293b;}
.monitor-tab.active{background:#3b82f6;color:#fff;}
.tab-pane{display:none;}
.tab-pane.active{display:block;}
.btn-action{border:none;border-radius:6px;font-size:0.72rem;font-weight:600;padding:3px 10px;cursor:pointer;transition:all .15s;}
.btn-kick{background:#fef2f2;color:#dc2626;}
.btn-kick:hover{background:#dc2626;color:#fff;}
.btn-block{background:#fef2f2;color:#dc2626;}
.btn-block:hover{background:#dc2626;color:#fff;}
.btn-unblock{background:#f0fdf4;color:#16a34a;}
.btn-unblock:hover{background:#16a34a;color:#fff;}
.btn-delete{background:#fef2f2;color:#dc2626;}
.btn-delete:hover{background:#dc2626;color:#fff;}
.btn-add{background:#3b82f6;color:#fff;border:none;border-radius:8px;padding:7px 18px;font-size:0.8rem;font-weight:600;cursor:pointer;}
.btn-add:hover{background:#2563eb;}
.badge-type{font-size:0.65rem;font-weight:700;padding:2px 7px;border-radius:20px;text-transform:uppercase;}
.badge-hotspot{background:#eff6ff;color:#1d4ed8;}
.badge-pppoe{background:#fffbeb;color:#b45309;}
.badge-bypassed{background:#f0fdf4;color:#16a34a;}
.badge-blocked{background:#fef2f2;color:#dc2626;}
.badge-regular{background:#f8fafc;color:#64748b;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:#fff;border-radius:14px;padding:28px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.2);}
.modal-box h5{font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 18px;}
.modal-box label{font-size:0.75rem;font-weight:600;color:#64748b;display:block;margin-bottom:4px;margin-top:12px;}
.modal-box input,.modal-box select{width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:0.85rem;color:#1e293b;outline:none;}
.modal-box input:focus,.modal-box select:focus{border-color:#3b82f6;}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
.btn-cancel{background:#f1f5f9;color:#64748b;border:none;border-radius:8px;padding:7px 18px;font-size:0.8rem;font-weight:600;cursor:pointer;}
.btn-save{background:#3b82f6;color:#fff;border:none;border-radius:8px;padding:7px 18px;font-size:0.8rem;font-weight:600;cursor:pointer;}
.toast-wrap{position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:8px;}
.toast{background:#1e293b;color:#fff;padding:10px 18px;border-radius:10px;font-size:0.8rem;font-weight:500;opacity:0;transform:translateY(8px);transition:all .25s;pointer-events:none;min-width:200px;}
.toast.show{opacity:1;transform:translateY(0);}
.toast.success{border-left:3px solid #22c55e;}
.toast.error{border-left:3px solid #ef4444;}
</style>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Header -->
                <header style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 60%,#0f3460 100%);border-bottom:3px solid #3b82f6;">
                    <div class="container-xl px-1 py-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:46px;height:46px;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.4);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fa fa-server" style="color:#60a5fa;font-size:1.2rem;"></i>
                                </div>
                                <div>
                                    <h1 class="text-white mb-0" style="font-size:1.4rem;font-weight:700;">Router Monitor</h1>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span id="statusDot" style="width:8px;height:8px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>
                                        <span id="statusText" style="color:rgba(255,255,255,0.5);font-size:0.75rem;">Connecting...</span>
                                        <span id="hostnameLabel" style="color:rgba(255,255,255,0.35);font-size:0.75rem;"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div style="text-align:right;">
                                    <div style="color:rgba(255,255,255,0.4);font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;">Router</div>
                                    <select id="routerSelect" class="form-select form-select-sm mt-1" style="background:#1e293b;color:#fff;border:1px solid rgba(255,255,255,0.2);border-radius:7px;min-width:180px;">
                                        <?php if (empty($userRouters)): ?>
                                            <option disabled>No routers assigned</option>
                                        <?php else: ?>
                                            <?php foreach ($userRouters as $r): ?>
                                                <option value="<?= (int)$r['router_id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div id="lastUpdated" style="color:rgba(255,255,255,0.35);font-size:0.72rem;text-align:right;"></div>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 py-3">

                    <!-- Tab nav -->
                    <div class="d-flex gap-2 mb-4 flex-wrap" style="background:#fff;border-radius:10px;padding:6px;box-shadow:0 1px 6px rgba(0,0,0,0.06);width:fit-content;">
                        <button class="monitor-tab active" data-tab="overview"><i class="fa fa-gauge-high me-1"></i> Overview</button>
                        <button class="monitor-tab" data-tab="sessions">
                            <i class="fa fa-users me-1"></i> Sessions
                            <span id="tabSessionCount" style="background:#e0f2fe;color:#0284c7;font-size:0.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:4px;">0</span>
                        </button>
                        <button class="monitor-tab" data-tab="bindings"><i class="fa fa-link me-1"></i> IP Bindings</button>
                        <button class="monitor-tab" data-tab="neighbors"><i class="fa fa-network-wired me-1"></i> Neighbors</button>
                    </div>

                    <!-- ══ TAB: OVERVIEW ══ -->
                    <div class="tab-pane active" id="tab-overview">

                        <div id="routerInfoBar" class="card border-0 mb-4" style="border-radius:12px;background:#1e293b;box-shadow:0 2px 12px rgba(0,0,0,0.15);display:none;">
                            <div class="card-body px-4 py-3">
                                <div class="d-flex flex-wrap gap-4 align-items-center">
                                    <div><div style="color:#64748b;font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;">Board</div><div id="infoBoard" style="color:#e2e8f0;font-weight:600;font-size:0.85rem;margin-top:1px;">—</div></div>
                                    <div><div style="color:#64748b;font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;">RouterOS</div><div id="infoVersion" style="color:#e2e8f0;font-weight:600;font-size:0.85rem;margin-top:1px;">—</div></div>
                                    <div><div style="color:#64748b;font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;">Platform</div><div id="infoPlatform" style="color:#e2e8f0;font-weight:600;font-size:0.85rem;margin-top:1px;">—</div></div>
                                    <div><div style="color:#64748b;font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;">Uptime</div><div id="infoUptime" style="color:#4ade80;font-weight:600;font-size:0.85rem;margin-top:1px;">—</div></div>
                                    <div class="ms-auto"><span style="font-size:0.75rem;font-weight:600;padding:4px 12px;border-radius:20px;background:rgba(34,197,94,0.15);color:#4ade80;border:1px solid rgba(34,197,94,0.3);">Online</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6 col-xl-3">
                                <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.7px;color:#94a3b8;">CPU Load</span>
                                            <div style="width:32px;height:32px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;"><i class="fa fa-microchip" style="color:#3b82f6;font-size:0.8rem;"></i></div>
                                        </div>
                                        <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><span id="cpu">0</span><span style="font-size:1rem;color:#94a3b8;">%</span></div>
                                        <div class="progress mt-2" style="height:4px;border-radius:2px;background:#f1f5f9;"><div id="cpuBar" class="progress-bar" style="width:0%;background:#3b82f6;border-radius:2px;transition:width .4s;"></div></div>
                                        <canvas id="cpuChart" height="45" class="mt-2"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-xl-3">
                                <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.7px;color:#94a3b8;">Memory</span>
                                            <div style="width:32px;height:32px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;"><i class="fa fa-memory" style="color:#22c55e;font-size:0.8rem;"></i></div>
                                        </div>
                                        <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><span id="memory">0</span><span style="font-size:1rem;color:#94a3b8;">%</span></div>
                                        <div id="memDetail" style="font-size:0.72rem;color:#94a3b8;margin-top:2px;"></div>
                                        <div class="progress mt-2" style="height:4px;border-radius:2px;background:#f1f5f9;"><div id="memBar" class="progress-bar" style="width:0%;background:#22c55e;border-radius:2px;transition:width .4s;"></div></div>
                                        <canvas id="memoryChart" height="45" class="mt-2"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-xl-3">
                                <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.7px;color:#94a3b8;">Disk</span>
                                            <div style="width:32px;height:32px;border-radius:8px;background:#fefce8;display:flex;align-items:center;justify-content:center;"><i class="fa fa-hard-drive" style="color:#eab308;font-size:0.8rem;"></i></div>
                                        </div>
                                        <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><span id="disk">0</span><span style="font-size:1rem;color:#94a3b8;">%</span></div>
                                        <div id="diskDetail" style="font-size:0.72rem;color:#94a3b8;margin-top:2px;"></div>
                                        <div class="progress mt-2" style="height:4px;border-radius:2px;background:#f1f5f9;"><div id="diskBar" class="progress-bar" style="width:0%;background:#eab308;border-radius:2px;transition:width .4s;"></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-xl-3">
                                <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.7px;color:#94a3b8;">Active Users</span>
                                            <div style="width:32px;height:32px;border-radius:8px;background:#fdf4ff;display:flex;align-items:center;justify-content:center;"><i class="fa fa-users" style="color:#a855f7;font-size:0.8rem;"></i></div>
                                        </div>
                                        <div style="font-size:2rem;font-weight:800;color:#1e293b;line-height:1;"><span id="users">0</span></div>
                                        <div class="d-flex gap-3 mt-2">
                                            <div style="font-size:0.72rem;color:#94a3b8;"><i class="fa fa-wifi" style="color:#0ea5e9;"></i> <span id="hotspotUsers">0</span> HS</div>
                                            <div style="font-size:0.72rem;color:#94a3b8;"><i class="fa fa-network-wired" style="color:#f59e0b;"></i> <span id="pppoeUsers">0</span> PPPoE</div>
                                            <div style="font-size:0.72rem;color:#94a3b8;"><i class="fa fa-ethernet" style="color:#6b7280;"></i> <span id="dhcpLeases">0</span> DHCP</div>
                                        </div>
                                        <canvas id="usersChart" height="45" class="mt-2"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.7px;color:#94a3b8;">Download (ether1)</span>
                                            <i class="fa fa-arrow-down" style="color:#3b82f6;"></i>
                                        </div>
                                        <div style="font-size:1.8rem;font-weight:800;color:#3b82f6;line-height:1;"><span id="rx">0</span> <span style="font-size:0.9rem;color:#94a3b8;">Mbps</span></div>
                                        <canvas id="rxChart" height="55" class="mt-3"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.7px;color:#94a3b8;">Upload (ether1)</span>
                                            <i class="fa fa-arrow-up" style="color:#ef4444;"></i>
                                        </div>
                                        <div style="font-size:1.8rem;font-weight:800;color:#ef4444;line-height:1;"><span id="tx">0</span> <span style="font-size:0.9rem;color:#94a3b8;">Mbps</span></div>
                                        <canvas id="txChart" height="55" class="mt-3"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);overflow:hidden;">
                            <div class="card-header py-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                                <span style="font-weight:700;color:#1e293b;font-size:0.9rem;"><i class="fa fa-ethernet me-2" style="color:#94a3b8;"></i>Interfaces</span>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0" style="font-size:0.8rem;">
                                    <thead style="background:#f8fafc;">
                                        <tr style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;">
                                            <th class="px-4 py-2">Name</th><th class="py-2">Type</th><th class="py-2">Status</th><th class="py-2">↓ / ↑</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ifaceTableBody">
                                        <tr><td colspan="4" class="text-center text-muted py-4">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div><!-- /overview -->

                    <!-- ══ TAB: SESSIONS ══ -->
                    <div class="tab-pane" id="tab-sessions">
                        <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);overflow:hidden;">
                            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                                <span style="font-weight:700;color:#1e293b;font-size:0.9rem;"><i class="fa fa-users me-2" style="color:#94a3b8;"></i>Active Sessions</span>
                                <span id="userCount" style="background:#eff6ff;color:#3b82f6;font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:20px;">0</span>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0" style="font-size:0.8rem;">
                                    <thead style="background:#f8fafc;position:sticky;top:0;z-index:1;">
                                        <tr style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;">
                                            <th class="px-4 py-2">User</th><th>Type</th><th>IP</th><th>MAC</th><th>Uptime</th><th>↓ / ↑</th><th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="usersTableBody">
                                        <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div><!-- /sessions -->

                    <!-- ══ TAB: IP BINDINGS ══ -->
                    <div class="tab-pane" id="tab-bindings">
                        <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);overflow:hidden;">
                            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                                <span style="font-weight:700;color:#1e293b;font-size:0.9rem;"><i class="fa fa-link me-2" style="color:#94a3b8;"></i>IP Bindings</span>
                                <button class="btn-add" onclick="openAddBinding()"><i class="fa fa-plus me-1"></i> Add Binding</button>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0" style="font-size:0.8rem;">
                                    <thead style="background:#f8fafc;position:sticky;top:0;z-index:1;">
                                        <tr style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;">
                                            <th class="px-4 py-2">MAC Address</th><th>IP Address</th><th>Server</th><th>Type</th><th>Comment</th><th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bindingsTableBody">
                                        <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div><!-- /bindings -->

                    <!-- ══ TAB: NEIGHBORS ══ -->
                    <div class="tab-pane" id="tab-neighbors">
                        <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);overflow:hidden;">
                            <div class="card-header d-flex align-items-center justify-content-between py-3 px-4" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                                <span style="font-weight:700;color:#1e293b;font-size:0.9rem;"><i class="fa fa-network-wired me-2" style="color:#94a3b8;"></i>IP Neighbors (ARP)</span>
                                <button class="btn-add" onclick="loadNeighbors()"><i class="fa fa-rotate me-1"></i> Refresh</button>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-hover align-middle mb-0" style="font-size:0.8rem;">
                                    <thead style="background:#f8fafc;position:sticky;top:0;z-index:1;">
                                        <tr style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;">
                                            <th class="px-4 py-2">IP Address</th><th>MAC Address</th><th>Interface</th><th>Identity</th><th>Platform</th><th>Version</th>
                                        </tr>
                                    </thead>
                                    <tbody id="neighborsTableBody">
                                        <tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div><!-- /neighbors -->

                </div>
            </main>
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <!-- Add Binding Modal -->
    <div class="modal-overlay" id="addBindingModal">
        <div class="modal-box">
            <h5><i class="fa fa-link me-2" style="color:#3b82f6;"></i>Add IP Binding</h5>
            <label>MAC Address <span style="color:#ef4444;">*</span></label>
            <input type="text" id="bindMac" placeholder="AA:BB:CC:DD:EE:FF">
            <label>IP Address <span style="color:#94a3b8;font-weight:400;">(optional)</span></label>
            <input type="text" id="bindIp" placeholder="192.168.1.100">
            <label>Server <span style="color:#94a3b8;font-weight:400;">(optional, default: all)</span></label>
            <input type="text" id="bindServer" placeholder="all">
            <label>Type</label>
            <select id="bindType">
                <option value="bypassed">Bypassed (skip login)</option>
                <option value="blocked">Blocked (deny access)</option>
                <option value="regular">Regular</option>
            </select>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal('addBindingModal')">Cancel</button>
                <button class="btn-save" onclick="submitAddBinding()">Add Binding</button>
            </div>
        </div>
    </div>

    <div class="toast-wrap" id="toastWrap"></div>

    <?php require_once "../partials/scripts.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    (function () {
        const MAX = 20;
        let ws       = null;
        let routerId = null;
        let lastRows = [];

        /* ── Toast ── */
        function toast(msg, type = 'success') {
            const wrap = document.getElementById('toastWrap');
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.textContent = msg;
            wrap.appendChild(t);
            requestAnimationFrame(() => t.classList.add('show'));
            setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3000);
        }

        /* ── Modal ── */
        window.openModal  = id => document.getElementById(id).classList.add('open');
        window.closeModal = id => document.getElementById(id).classList.remove('open');

        /* ── Charts ── */
        function mkChart(id, color, yMax) {
            return new Chart(document.getElementById(id), {
                type: 'line',
                data: { labels: [], datasets: [{ data: [], borderColor: color, borderWidth: 1.5, fill: true, backgroundColor: color + '18', tension: 0.4, pointRadius: 0 }] },
                options: { responsive: true, animation: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false, min: 0, ...(yMax ? { max: yMax } : { beginAtZero: true }) } } }
            });
        }

        const charts = {
            cpu:    mkChart('cpuChart',    '#3b82f6', 100),
            memory: mkChart('memoryChart', '#22c55e', 100),
            users:  mkChart('usersChart',  '#a855f7', null),
            rx:     mkChart('rxChart',     '#3b82f6', null),
            tx:     mkChart('txChart',     '#ef4444', null),
        };

        function push(chart, val) {
            const now = new Date().toLocaleTimeString();
            chart.data.labels.push(now);
            chart.data.datasets[0].data.push(val);
            if (chart.data.labels.length > MAX) { chart.data.labels.shift(); chart.data.datasets[0].data.shift(); }
            chart.update('none');
        }

        function resetCharts() {
            Object.values(charts).forEach(c => { c.data.labels = []; c.data.datasets[0].data = []; c.update('none'); });
        }

        /* ── Status ── */
        function setStatus(online) {
            const dot  = document.getElementById('statusDot');
            const text = document.getElementById('statusText');
            dot.style.background = online ? '#4ade80' : '#f87171';
            text.textContent     = online ? 'Online'  : 'Offline';
            text.style.color     = online ? '#4ade80' : '#f87171';
        }

        /* ── Action helper ── */
        function doAction(payload, onSuccess) {
            fetch('action.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ router_id: routerId, ...payload })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) { if (onSuccess) onSuccess(d); }
                else toast(d.message || 'Error', 'error');
            })
            .catch(() => toast('Request failed', 'error'));
        }

        /* ── Render sessions ── */
        function renderUsers(rows) {
            lastRows = rows;
            const count = rows.length;
            document.getElementById('userCount').textContent       = count;
            document.getElementById('tabSessionCount').textContent = count;

            const tbody = document.getElementById('usersTableBody');
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No active sessions</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map((u, idx) => `
                <tr>
                    <td class="px-4 py-2" style="font-weight:600;color:#1e293b;">${u.name || '—'}</td>
                    <td><span class="badge-type badge-${u.type}">${u.type}</span></td>
                    <td style="font-family:monospace;color:#475569;">${u.ip || '—'}</td>
                    <td style="font-family:monospace;font-size:0.72rem;color:#94a3b8;">${u.mac || '—'}</td>
                    <td style="color:#64748b;">${u.uptime || '—'}</td>
                    <td>${u.rx || '—'} / ${u.tx || '—'}</td>
                    <td><button class="btn-action btn-kick" onclick="kickUser(${idx})"><i class="fa fa-bolt me-1"></i>Kick</button></td>
                </tr>
            `).join('');
        }

        window.kickUser = function(idx) {
            const u = lastRows[idx];
            if (!u) return;
            if (!confirm(`Kick ${u.name || u.ip}?`)) return;
            doAction(
                { action: u.type === 'hotspot' ? 'kick_hotspot' : 'kick_pppoe', id: u.id, name: u.name },
                () => toast(`${u.name || u.ip} kicked`)
            );
        };

        /* ── Render interfaces ── */
        function renderIfaces(ifaces) {
            const tbody = document.getElementById('ifaceTableBody');
            if (!ifaces || !ifaces.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No interfaces</td></tr>';
                return;
            }
            tbody.innerHTML = ifaces.map(i => {
                const running = i.running && !i.disabled;
                const dot     = i.disabled ? '#94a3b8' : (running ? '#22c55e' : '#f87171');
                const label   = i.disabled ? 'Disabled' : (running ? 'Up' : 'Down');
                return `<tr>
                    <td class="px-4 py-2">${i.name}</td>
                    <td>${i.type || '—'}</td>
                    <td><span style="display:inline-flex;align-items:center;gap:5px;font-size:0.72rem;font-weight:600;color:${dot};"><span style="width:7px;height:7px;border-radius:50%;background:${dot};"></span>${label}</span></td>
                    <td>${i.rx_byte || '—'} / ${i.tx_byte || '—'}</td>
                </tr>`;
            }).join('');
        }

        /* ── Update UI from WS ── */
        function updateUI(d) {
            if (d.status === 'offline' || d.status === 'error') { setStatus(false); return; }
            setStatus(true);

            document.getElementById('cpu').textContent          = d.cpu;
            document.getElementById('memory').textContent       = d.memory;
            document.getElementById('disk').textContent         = d.disk;
            document.getElementById('users').textContent        = d.users;
            document.getElementById('hotspotUsers').textContent = d.hotspot_users;
            document.getElementById('pppoeUsers').textContent   = d.pppoe_users;
            document.getElementById('dhcpLeases').textContent   = d.dhcp_leases;
            document.getElementById('rx').textContent           = d.rx;
            document.getElementById('tx').textContent           = d.tx;

            document.getElementById('cpuBar').style.width  = (d.cpu    || 0) + '%';
            document.getElementById('memBar').style.width  = (d.memory || 0) + '%';
            document.getElementById('diskBar').style.width = (d.disk   || 0) + '%';

            document.getElementById('memDetail').textContent  = `${d.memory_used} / ${d.memory_total}`;
            document.getElementById('diskDetail').textContent = `${d.disk_used} / ${d.disk_total}`;

            document.getElementById('routerInfoBar').style.display = '';
            document.getElementById('infoBoard').textContent        = d.board;
            document.getElementById('infoVersion').textContent      = d.version;
            document.getElementById('infoPlatform').textContent     = d.platform;
            document.getElementById('infoUptime').textContent       = d.uptime;
            document.getElementById('hostnameLabel').textContent    = '· ' + d.hostname;
            document.getElementById('lastUpdated').textContent      = 'Updated ' + new Date().toLocaleTimeString();

            push(charts.cpu,    parseFloat(d.cpu)    || 0);
            push(charts.memory, parseFloat(d.memory) || 0);
            push(charts.users,  parseInt(d.users)    || 0);
            push(charts.rx,     parseFloat(d.rx)     || 0);
            push(charts.tx,     parseFloat(d.tx)     || 0);

            renderUsers(d.user_rows   || []);
            renderIfaces(d.interfaces || []);
        }

        /* ── Bindings ── */
        function loadBindings() {
            document.getElementById('bindingsTableBody').innerHTML =
                '<tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>';
            doAction({ action: 'get_bindings' }, d => renderBindings(d.data || []));
        }

        function renderBindings(rows) {
            const tbody = document.getElementById('bindingsTableBody');
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No bindings found</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(b => {
                const type    = b['type'] || 'regular';
                const blocked = type === 'blocked';
                const id      = b['.id'];
                return `<tr>
                    <td class="px-4 py-2" style="font-family:monospace;font-weight:600;color:#1e293b;">${b['mac-address'] || '—'}</td>
                    <td style="font-family:monospace;color:#475569;">${b['address'] || '—'}</td>
                    <td style="color:#64748b;">${b['server'] || 'all'}</td>
                    <td><span class="badge-type badge-${type}">${type}</span></td>
                    <td style="color:#94a3b8;font-size:0.75rem;">${b['comment'] || '—'}</td>
                    <td>
                        <div class="d-flex gap-1">
                            ${blocked
                                ? `<button class="btn-action btn-unblock" onclick="bindingAction('unblock_binding','${id}')"><i class="fa fa-unlock me-1"></i>Unblock</button>`
                                : `<button class="btn-action btn-block"   onclick="bindingAction('block_binding','${id}')"><i class="fa fa-ban me-1"></i>Block</button>`
                            }
                            <button class="btn-action btn-delete" onclick="bindingAction('delete_binding','${id}')"><i class="fa fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        window.bindingAction = function(action, id) {
            const labels = { delete_binding: 'Delete', block_binding: 'Block', unblock_binding: 'Unblock' };
            if (!confirm(`${labels[action]} this binding?`)) return;
            doAction({ action, id }, () => { toast(`${labels[action]}ed`); loadBindings(); });
        };

        window.openAddBinding = () => openModal('addBindingModal');

        window.submitAddBinding = function() {
            const mac    = document.getElementById('bindMac').value.trim();
            const ip     = document.getElementById('bindIp').value.trim();
            const server = document.getElementById('bindServer').value.trim();
            const type   = document.getElementById('bindType').value;
            if (!mac) { toast('MAC address is required', 'error'); return; }
            doAction({ action: 'add_binding', mac, ip, server, type }, () => {
                closeModal('addBindingModal');
                toast('Binding added');
                loadBindings();
                ['bindMac','bindIp','bindServer'].forEach(id => document.getElementById(id).value = '');
            });
        };

        /* ── Neighbors ── */
        window.loadNeighbors = function() {
            document.getElementById('neighborsTableBody').innerHTML =
                '<tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr>';
            doAction({ action: 'get_neighbors' }, d => renderNeighbors(d.data || []));
        };

        function renderNeighbors(rows) {
            const tbody = document.getElementById('neighborsTableBody');
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No neighbors found</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(n => `
                <tr>
                    <td class="px-4 py-2" style="font-family:monospace;font-weight:600;color:#1e293b;">${n['address'] || '—'}</td>
                    <td style="font-family:monospace;color:#475569;">${n['mac-address'] || '—'}</td>
                    <td style="color:#64748b;">${n['interface'] || '—'}</td>
                    <td style="color:#1e293b;font-weight:500;">${n['identity'] || '—'}</td>
                    <td style="color:#64748b;">${n['platform'] || '—'}</td>
                    <td style="color:#64748b;">${n['version'] || '—'}</td>
                </tr>
            `).join('');
        }

        /* ── Tabs ── */
        let bindingsLoaded  = false;
        let neighborsLoaded = false;

        document.querySelectorAll('.monitor-tab').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.monitor-tab').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');

                if (this.dataset.tab === 'bindings' && !bindingsLoaded) {
                    bindingsLoaded = true;
                    loadBindings();
                }
                if (this.dataset.tab === 'neighbors' && !neighborsLoaded) {
                    neighborsLoaded = true;
                    loadNeighbors();
                }
            });
        });

        /* ── WebSocket ── */
        function connectWS(id) {
            if (!id) return;
            routerId = id;
            if (ws) { ws.onclose = null; ws.close(); ws = null; }

            ws = new WebSocket('wss://billing.inovatech.co.ke/ws');

            ws.onopen = () => {
                setStatus(true);
                ws.send(JSON.stringify({ type: 'subscribe', router_id: id }));
            };
            ws.onmessage = event => { try { updateUI(JSON.parse(event.data)); } catch(e) {} };
            ws.onerror   = () => setStatus(false);
            ws.onclose   = () => { setStatus(false); ws = null; setTimeout(() => connectWS(id), 3000); };
        }

        /* ── Init ── */
        const select = document.getElementById('routerSelect');
        select.addEventListener('change', function() {
            resetCharts();
            bindingsLoaded  = false;
            neighborsLoaded = false;
            document.getElementById('routerInfoBar').style.display = 'none';
            connectWS(parseInt(this.value));
        });

        if (select.value) connectWS(parseInt(select.value));

    })();
    </script>

</body>
</html>