<?php
require_once __DIR__ . '/../core/db.php';
require_once "../core/auth.php";
require_once "../partials/head.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

$user_id = $_SESSION['user']['id'];
check_subscription_gate($pdo, $user_id);

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
/* ── WinBox CSS Variables ── */
:root {
  --wb-bg:#0d1117;
  --wb-panel:#161b22;
  --wb-border:#21262d;
  --wb-accent:#238636;
  --wb-blue:#1f6feb;
  --wb-text:#e6edf3;
  --wb-muted:#8b949e;
  --wb-hover:#1c2128;
  --wb-active:#2d333b;
  --wb-danger:#da3633;
  --wb-warn:#d29922;
  --wb-green:#3fb950;
  --wb-purple:#8957e5;
  --wb-cyan:#39d0d8;
  --wb-orange:#f0883e;
}

/* ── WinBox Shell inside Bootstrap layout ── */
#wb-shell {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 56px); /* subtract topnav height */
  background: var(--wb-bg);
  color: var(--wb-text);
  font-family: 'JetBrains Mono','Fira Code','Courier New',monospace;
  font-size: 12.5px;
  overflow: hidden;
}

/* ── Title bar ── */
#wb-titlebar {
  height: 38px;
  background: #1c2128;
  border-bottom: 1px solid var(--wb-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 12px;
  user-select: none;
  flex-shrink: 0;
}
#wb-titlebar .tb-left { display: flex; align-items: center; gap: 10px; }
#wb-titlebar .tb-icon { width: 20px; height: 20px; background: var(--wb-blue); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #fff; font-weight: 700; }
#wb-titlebar .tb-title { font-size: 12px; font-weight: 600; color: var(--wb-text); }
#wb-titlebar .tb-router-select { background: var(--wb-panel); border: 1px solid var(--wb-border); color: var(--wb-text); border-radius: 4px; padding: 2px 8px; font-family: inherit; font-size: 11px; cursor: pointer; }
#wb-titlebar .tb-status { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--wb-muted); }
#wb-titlebar .status-dot { width: 7px; height: 7px; border-radius: 50%; background: #555; flex-shrink: 0; }
#wb-titlebar .status-dot.online { background: var(--wb-green); box-shadow: 0 0 6px var(--wb-green); }
#wb-titlebar .status-dot.offline { background: var(--wb-danger); }

/* ── Menu bar ── */
#wb-menubar {
  height: 26px;
  background: #161b22;
  border-bottom: 1px solid var(--wb-border);
  display: flex;
  align-items: center;
  padding: 0 6px;
  gap: 2px;
  flex-shrink: 0;
}
.mb-item { padding: 2px 10px; font-size: 11px; color: var(--wb-muted); cursor: pointer; border-radius: 3px; transition: all .15s; white-space: nowrap; }
.mb-item:hover, .mb-item.active { background: var(--wb-hover); color: var(--wb-text); }
.mb-sep { width: 1px; height: 14px; background: var(--wb-border); margin: 0 4px; }
.mb-reboot { color: var(--wb-danger) !important; }
.mb-reboot:hover { background: rgba(218,54,51,0.15) !important; color: var(--wb-danger) !important; }

/* ── Main layout ── */
#wb-main { display: flex; flex: 1; overflow: hidden; }

/* ── Left sidebar ── */
#wb-sidebar {
  width: 180px;
  flex-shrink: 0;
  background: var(--wb-panel);
  border-right: 1px solid var(--wb-border);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}
#wb-sidebar::-webkit-scrollbar { width: 4px; }
#wb-sidebar::-webkit-scrollbar-thumb { background: var(--wb-border); }
.sb-section { margin-top: 6px; }
.sb-section-label { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: var(--wb-muted); padding: 6px 12px 3px; }
.sb-item { display: flex; align-items: center; gap: 8px; padding: 5px 12px; cursor: pointer; font-size: 11.5px; color: var(--wb-muted); transition: all .12s; border-left: 2px solid transparent; }
.sb-item:hover { background: var(--wb-hover); color: var(--wb-text); }
.sb-item.active { background: rgba(31,111,235,0.12); color: #58a6ff; border-left-color: #1f6feb; }
.sb-item .sb-icon { width: 14px; text-align: center; font-size: 11px; flex-shrink: 0; }
.sb-badge { margin-left: auto; font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 10px; background: rgba(31,111,235,0.2); color: #58a6ff; }
.sb-badge.red { background: rgba(218,54,51,0.2); color: #f85149; }

/* ── Content area ── */
#wb-content { flex: 1; overflow: hidden; display: flex; flex-direction: column; }

/* ── Toolbar ── */
.wb-toolbar { height: 34px; background: #1c2128; border-bottom: 1px solid var(--wb-border); display: flex; align-items: center; gap: 4px; padding: 0 8px; flex-shrink: 0; flex-wrap: nowrap; overflow-x: auto; }
.wb-toolbar::-webkit-scrollbar { height: 2px; }
.tb-btn { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; font-size: 11px; font-family: inherit; border: 1px solid var(--wb-border); border-radius: 4px; background: var(--wb-panel); color: var(--wb-text); cursor: pointer; transition: all .15s; white-space: nowrap; }
.tb-btn:hover { background: var(--wb-hover); border-color: #444c56; }
.tb-btn.primary { background: rgba(35,134,54,0.2); border-color: var(--wb-accent); color: var(--wb-green); }
.tb-btn.primary:hover { background: rgba(35,134,54,0.35); }
.tb-btn.danger { background: rgba(218,54,51,0.15); border-color: var(--wb-danger); color: #f85149; }
.tb-btn.danger:hover { background: rgba(218,54,51,0.3); }
.tb-sep { width: 1px; height: 18px; background: var(--wb-border); margin: 0 2px; }
.tb-search { margin-left: auto; background: var(--wb-bg); border: 1px solid var(--wb-border); color: var(--wb-text); border-radius: 4px; padding: 2px 8px; font-family: inherit; font-size: 11px; width: 150px; outline: none; }
.tb-search:focus { border-color: var(--wb-blue); }

/* ── Table ── */
.wb-scroll { flex: 1; overflow: auto; }
.wb-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
.wb-scroll::-webkit-scrollbar-thumb { background: var(--wb-border); border-radius: 3px; }
.wb-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
.wb-table th { position: sticky; top: 0; z-index: 2; background: #1c2128; text-align: left; padding: 5px 10px; font-weight: 600; font-size: 10.5px; color: var(--wb-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--wb-border); white-space: nowrap; }
.wb-table td { padding: 4px 10px; border-bottom: 1px solid rgba(33,38,45,0.6); color: var(--wb-text); white-space: nowrap; }
.wb-table tr:hover td { background: var(--wb-hover); }
.mono { font-family: 'JetBrains Mono','Fira Code','Courier New',monospace; }
.muted { color: var(--wb-muted); }
.dot-online { color: var(--wb-green); }
.dot-offline { color: var(--wb-danger); }
.empty-state { text-align: center; padding: 40px; color: var(--wb-muted); font-size: 12px; }

/* ── Status bar ── */
#wb-statusbar { height: 22px; background: #1c2128; border-top: 1px solid var(--wb-border); display: flex; align-items: center; padding: 0 10px; gap: 16px; font-size: 10.5px; color: var(--wb-muted); flex-shrink: 0; }
#wb-statusbar span { display: flex; align-items: center; gap: 5px; }

/* ── Tags / badges ── */
.tag { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 700; }
.tag-hs { background: rgba(31,111,235,0.2); color: #79c0ff; }
.tag-pppoe { background: rgba(210,153,34,0.2); color: var(--wb-warn); }
.tag-bypassed { background: rgba(63,185,80,0.2); color: var(--wb-green); }
.tag-blocked { background: rgba(218,54,51,0.2); color: #f85149; }
.tag-regular { background: var(--wb-active); color: var(--wb-muted); }
.tag-dynamic { background: rgba(57,208,216,0.15); color: var(--wb-cyan); }
.tag-static { background: rgba(137,87,229,0.2); color: #d2a8ff; }
.tag-disabled { background: var(--wb-active); color: var(--wb-muted); text-decoration: line-through; }
.tag-up { background: rgba(63,185,80,0.15); color: var(--wb-green); }
.tag-down { background: rgba(218,54,51,0.15); color: #f85149; }

/* ── Action buttons in table ── */
.act { display: inline-flex; align-items: center; gap: 3px; padding: 2px 7px; font-size: 10px; border-radius: 3px; border: 1px solid var(--wb-border); background: var(--wb-panel); color: var(--wb-muted); cursor: pointer; font-family: inherit; transition: all .12s; }
.act:hover { background: var(--wb-hover); color: var(--wb-text); }
.act.red:hover { background: rgba(218,54,51,0.2); border-color: var(--wb-danger); color: #f85149; }
.act.green:hover { background: rgba(63,185,80,0.2); border-color: var(--wb-green); color: var(--wb-green); }
.act.blue:hover { background: rgba(31,111,235,0.2); border-color: var(--wb-blue); color: #79c0ff; }

/* ── Modal ── */
.wb-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; }
.wb-modal-overlay.open { display: flex; }
.wb-modal { background: var(--wb-panel); border: 1px solid var(--wb-border); border-radius: 6px; width: 100%; max-width: 440px; box-shadow: 0 24px 64px rgba(0,0,0,0.5); font-family: 'JetBrains Mono','Fira Code',monospace; font-size: 12px; }
.wb-modal-header { padding: 10px 14px; border-bottom: 1px solid var(--wb-border); font-weight: 700; font-size: 12px; color: var(--wb-text); display: flex; justify-content: space-between; }
.wb-modal-header .close-btn { cursor: pointer; color: var(--wb-muted); font-size: 14px; }
.wb-modal-header .close-btn:hover { color: var(--wb-text); }
.wb-modal-body { padding: 16px; }
.wb-field { margin-bottom: 10px; }
.wb-field label { display: block; font-size: 10.5px; color: var(--wb-muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.wb-field input, .wb-field select { width: 100%; background: var(--wb-bg); border: 1px solid var(--wb-border); color: var(--wb-text); border-radius: 4px; padding: 5px 8px; font-family: inherit; font-size: 12px; outline: none; transition: border-color .15s; }
.wb-field input:focus, .wb-field select:focus { border-color: var(--wb-blue); }
.wb-modal-footer { padding: 10px 14px; border-top: 1px solid var(--wb-border); display: flex; justify-content: flex-end; gap: 6px; }

/* ── Log viewer ── */
#logContainer { background: var(--wb-bg); padding: 10px; font-size: 11px; line-height: 1.7; height: 100%; overflow-y: auto; }
#logContainer::-webkit-scrollbar { width: 6px; }
#logContainer::-webkit-scrollbar-thumb { background: var(--wb-border); }
.log-info { color: var(--wb-text); }
.log-warn { color: var(--wb-warn); }
.log-error { color: #f85149; }
.log-debug { color: var(--wb-muted); }

/* ── Stats cards ── */
.stat-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(160px,1fr)); gap: 8px; padding: 12px; }
.stat-card { background: var(--wb-panel); border: 1px solid var(--wb-border); border-radius: 5px; padding: 10px 12px; }
.stat-card .sc-label { font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.8px; color: var(--wb-muted); }
.stat-card .sc-value { font-size: 1.5rem; font-weight: 700; color: var(--wb-text); line-height: 1.2; margin-top: 2px; }
.stat-card .sc-sub { font-size: 10px; color: var(--wb-muted); margin-top: 2px; }
.stat-card .sc-bar { height: 3px; background: var(--wb-border); border-radius: 2px; margin-top: 6px; }
.stat-card .sc-bar-fill { height: 100%; border-radius: 2px; transition: width .4s; }

/* ── Interfaces grid ── */
.ov-wrap { overflow-y: auto; flex: 1; padding: 0 0 12px; }
.ov-wrap::-webkit-scrollbar { width: 5px; }
.ov-wrap::-webkit-scrollbar-thumb { background: var(--wb-border); }
.iface-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(200px,1fr)); gap: 6px; padding: 0 12px 12px; }
.iface-card { background: var(--wb-panel); border: 1px solid var(--wb-border); border-radius: 5px; padding: 8px 10px; display: flex; align-items: center; gap: 8px; }
.iface-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.iface-dot.up { background: var(--wb-green); box-shadow: 0 0 5px var(--wb-green); }
.iface-dot.down { background: var(--wb-danger); }
.iface-dot.disabled { background: #555; }

/* ── Pane visibility ── */
.wb-pane { display: none; flex-direction: column; height: 100%; }
.wb-pane.active { display: flex; }

/* ── Toast ── */
#wb-toasts { position: fixed; bottom: 28px; right: 16px; z-index: 99999; display: flex; flex-direction: column; gap: 6px; }
.wb-toast { background: #21262d; border: 1px solid var(--wb-border); color: var(--wb-text); padding: 7px 14px; border-radius: 5px; font-size: 11.5px; font-family: 'JetBrains Mono',monospace; opacity: 0; transform: translateY(6px); transition: all .2s; pointer-events: none; min-width: 220px; }
.wb-toast.show { opacity: 1; transform: translateY(0); }
.wb-toast.ok { border-left: 3px solid var(--wb-green); }
.wb-toast.err { border-left: 3px solid var(--wb-danger); }
.wb-toast.info { border-left: 3px solid var(--wb-blue); }

/* ── Splash screen ── */
#wb-splash { position: fixed; inset: 0; z-index: 999999; background: #0d1117; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: 'JetBrains Mono','Fira Code','Courier New',monospace; transition: opacity .5s ease; }
@keyframes splashPulse { 0% { transform: scale(1); opacity: .6; } 100% { transform: scale(1.9); opacity: 0; } }
</style>

<body class="nav-fixed bg-light nav-fixed bg-light drawer-toggled">
<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
    <?php require_once "../partials/sidebar.php"; ?>

    <div id="layoutDrawer_content">
        <main style="padding:0;overflow:hidden;">

            <!-- ══ WinBox Shell (sits inside Bootstrap layout_content) ══ -->
            <div id="wb-shell">

                <!-- ── Title Bar ── -->
                <div id="wb-titlebar">
                    <div class="tb-left">
                        <div class="tb-icon">MT</div>
                        <span class="tb-title">WinBox — Router Management</span>
                        <select id="routerSelect" class="tb-router-select">
                            <?php if (empty($userRouters)): ?>
                                <option disabled>No routers assigned</option>
                            <?php else: ?>
                                <?php foreach ($userRouters as $r): ?>
                                    <option value="<?= (int)$r['router_id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="tb-status">
                        <div class="status-dot" id="statusDot"></div>
                        <span id="statusText">Connecting…</span>
                        <span id="hostnameLabel" style="color:#444c56;"></span>
                        <span id="lastUpdated" style="margin-left:8px;"></span>
                    </div>
                </div>

                <!-- ── Menu Bar ── -->
                <div id="wb-menubar">
                    <span class="mb-item" onclick="navTo('overview')">Overview</span>
                    <span class="mb-sep"></span>
                    <span class="mb-item" onclick="navTo('hotspot-active')">IP / Hotspot</span>
                    <span class="mb-item" onclick="navTo('pppoe-active')">PPP</span>
                    <span class="mb-item" onclick="navTo('ip-arp')">IP</span>
                    <span class="mb-item" onclick="navTo('fw-address-list')">Firewall</span>
                    <span class="mb-item" onclick="navTo('interfaces')">Interfaces</span>
                    <span class="mb-sep"></span>
                    <span class="mb-item" onclick="navTo('tools-ping')">Tools</span>
                    <span class="mb-item" onclick="navTo('logs')">Log</span>
                    <span class="mb-sep"></span>
                    <span class="mb-item mb-reboot" onclick="openReboot()">⟳ Reboot</span>
                </div>

                <!-- ── Main ── -->
                <div id="wb-main">

                    <!-- Sidebar -->
                    <div id="wb-sidebar">
                        <div class="sb-section">
                            <div class="sb-section-label">Quick</div>
                            <div class="sb-item active" data-pane="overview" onclick="navTo('overview')"><span class="sb-icon">◈</span> Overview</div>
                        </div>
                        <div class="sb-section">
                            <div class="sb-section-label">IP / Hotspot</div>
                            <div class="sb-item" data-pane="hotspot-active" onclick="navTo('hotspot-active')"><span class="sb-icon">⚡</span> Active <span class="sb-badge" id="sb-hs-count">0</span></div>
                            <div class="sb-item" data-pane="hotspot-users" onclick="navTo('hotspot-users')"><span class="sb-icon">👤</span> Users</div>
                            <div class="sb-item" data-pane="hotspot-hosts" onclick="navTo('hotspot-hosts')"><span class="sb-icon">🖥</span> Hosts</div>
                            <div class="sb-item" data-pane="hotspot-profiles" onclick="navTo('hotspot-profiles')"><span class="sb-icon">📋</span> Profiles</div>
                            <div class="sb-item" data-pane="hotspot-walled" onclick="navTo('hotspot-walled')"><span class="sb-icon">🔓</span> Walled Garden</div>
                            <div class="sb-item" data-pane="ip-bindings" onclick="navTo('ip-bindings')"><span class="sb-icon">🔗</span> IP Bindings</div>
                        </div>
                        <div class="sb-section">
                            <div class="sb-section-label">PPP</div>
                            <div class="sb-item" data-pane="pppoe-active" onclick="navTo('pppoe-active')"><span class="sb-icon">⚡</span> Active <span class="sb-badge" id="sb-ppp-count">0</span></div>
                            <div class="sb-item" data-pane="pppoe-secrets" onclick="navTo('pppoe-secrets')"><span class="sb-icon">🔑</span> Secrets</div>
                            <div class="sb-item" data-pane="pppoe-profiles" onclick="navTo('pppoe-profiles')"><span class="sb-icon">📋</span> Profiles</div>
                        </div>
                        <div class="sb-section">
                            <div class="sb-section-label">IP</div>
                            <div class="sb-item" data-pane="ip-arp" onclick="navTo('ip-arp')"><span class="sb-icon">⟵</span> ARP</div>
                            <div class="sb-item" data-pane="ip-dhcp" onclick="navTo('ip-dhcp')"><span class="sb-icon">◎</span> DHCP Leases</div>
                            <div class="sb-item" data-pane="ip-neighbors" onclick="navTo('ip-neighbors')"><span class="sb-icon">📡</span> Neighbors</div>
                        </div>
                        <div class="sb-section">
                            <div class="sb-section-label">Firewall</div>
                            <div class="sb-item" data-pane="fw-address-list" onclick="navTo('fw-address-list')"><span class="sb-icon">📝</span> Address Lists</div>
                            <div class="sb-item" data-pane="fw-filter" onclick="navTo('fw-filter')"><span class="sb-icon">🔒</span> Filter Rules</div>
                            <div class="sb-item" data-pane="fw-nat" onclick="navTo('fw-nat')"><span class="sb-icon">↔</span> NAT</div>
                        </div>
                        <div class="sb-section">
                            <div class="sb-section-label">System</div>
                            <div class="sb-item" data-pane="interfaces" onclick="navTo('interfaces')"><span class="sb-icon">🔌</span> Interfaces</div>
                            <div class="sb-item" data-pane="logs" onclick="navTo('logs')"><span class="sb-icon">📃</span> Log</div>
                        </div>
                        <div class="sb-section">
                            <div class="sb-section-label">Tools</div>
                            <div class="sb-item" data-pane="tools-ping" onclick="navTo('tools-ping')"><span class="sb-icon">🏓</span> Ping</div>
                            <div class="sb-item" style="color:#f85149;" onclick="openReboot()"><span class="sb-icon">⟳</span> Reboot</div>
                        </div>
                    </div><!-- /wb-sidebar -->

                    <!-- Content -->
                    <div id="wb-content">

                        <!-- OVERVIEW -->
                        <div class="wb-pane active" id="pane-overview">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="navTo('overview')"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <span style="font-size:10.5px;color:var(--wb-muted);" id="ov-info-board">—</span>
                                <span style="font-size:10.5px;color:var(--wb-muted);margin-left:10px;" id="ov-info-version">—</span>
                                <span style="font-size:10.5px;color:var(--wb-muted);margin-left:10px;" id="ov-info-uptime">Uptime: —</span>
                            </div>
                            <div class="ov-wrap">
                                <div class="stat-grid">
                                    <div class="stat-card">
                                        <div class="sc-label">CPU Load</div>
                                        <div class="sc-value"><span id="ov-cpu">0</span><span style="font-size:0.9rem;color:var(--wb-muted);">%</span></div>
                                        <div class="sc-bar"><div class="sc-bar-fill" id="ov-cpu-bar" style="background:#1f6feb;width:0%"></div></div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="sc-label">Memory</div>
                                        <div class="sc-value"><span id="ov-mem">0</span><span style="font-size:0.9rem;color:var(--wb-muted);">%</span></div>
                                        <div class="sc-sub" id="ov-mem-detail">— / —</div>
                                        <div class="sc-bar"><div class="sc-bar-fill" id="ov-mem-bar" style="background:#3fb950;width:0%"></div></div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="sc-label">Disk</div>
                                        <div class="sc-value"><span id="ov-disk">0</span><span style="font-size:0.9rem;color:var(--wb-muted);">%</span></div>
                                        <div class="sc-sub" id="ov-disk-detail">— / —</div>
                                        <div class="sc-bar"><div class="sc-bar-fill" id="ov-disk-bar" style="background:#d29922;width:0%"></div></div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="sc-label">Active Users</div>
                                        <div class="sc-value" id="ov-users">0</div>
                                        <div class="sc-sub">HS: <span id="ov-hs">0</span> · PPPoE: <span id="ov-ppp">0</span> · DHCP: <span id="ov-dhcp-count">0</span></div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="sc-label">Download</div>
                                        <div class="sc-value" style="color:#58a6ff;"><span id="ov-rx">0</span> <span style="font-size:0.85rem;">Mbps</span></div>
                                        <canvas id="rxChart" height="35"></canvas>
                                    </div>
                                    <div class="stat-card">
                                        <div class="sc-label">Upload</div>
                                        <div class="sc-value" style="color:#f85149;"><span id="ov-tx">0</span> <span style="font-size:0.85rem;">Mbps</span></div>
                                        <canvas id="txChart" height="35"></canvas>
                                    </div>
                                </div>
                                <div style="padding:0 12px 8px;font-size:10.5px;text-transform:uppercase;letter-spacing:0.8px;color:var(--wb-muted);">Interfaces</div>
                                <div class="iface-grid" id="ifaceGrid"><div style="color:var(--wb-muted);padding:0 12px;">Loading…</div></div>
                            </div>
                        </div>

                        <!-- HOTSPOT ACTIVE -->
                        <div class="wb-pane" id="pane-hotspot-active">
                            <div class="wb-toolbar">
                                <button class="tb-btn danger" onclick="kickSelected('hotspot')"><i class="fa fa-bolt"></i> Kick</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" id="hsSearch" placeholder="Search…" oninput="filterTable('hsBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>User</th><th>IP Address</th><th>MAC Address</th><th>Uptime</th><th>RX / TX</th><th>Server</th><th></th></tr></thead>
                                    <tbody id="hsBody"><tr><td colspan="7" class="empty-state">Waiting for data…</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- HOTSPOT USERS -->
                        <div class="wb-pane" id="pane-hotspot-users">
                            <div class="wb-toolbar">
                                <button class="tb-btn primary" onclick="openModal('addHsUserModal')"><i class="fa fa-plus"></i> Add</button>
                                <button class="tb-btn" onclick="loadHsUsers()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('hsUsersBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>Name</th><th>Password</th><th>Profile</th><th>MAC</th><th>Limit Uptime</th><th>Comment</th><th>Status</th><th></th></tr></thead>
                                    <tbody id="hsUsersBody"><tr><td colspan="8" class="empty-state">Click Refresh to load users</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- HOTSPOT HOSTS -->
                        <div class="wb-pane" id="pane-hotspot-hosts">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadHsHosts()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('hsHostsBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>MAC Address</th><th>IP Address</th><th>Server</th><th>Hostname</th><th>Comment</th><th>Status</th></tr></thead>
                                    <tbody id="hsHostsBody"><tr><td colspan="6" class="empty-state">Click Refresh to load hosts</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- HOTSPOT PROFILES -->
                        <div class="wb-pane" id="pane-hotspot-profiles">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadHsProfiles()"><i class="fa fa-rotate"></i> Refresh</button>
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>Name</th><th>Rate Limit</th><th>Session Timeout</th><th>Idle Timeout</th><th>Shared Users</th></tr></thead>
                                    <tbody id="hsProfilesBody"><tr><td colspan="5" class="empty-state">Click Refresh to load profiles</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- WALLED GARDEN -->
                        <div class="wb-pane" id="pane-hotspot-walled">
                            <div class="wb-toolbar">
                                <button class="tb-btn primary" onclick="openModal('addWalledModal')"><i class="fa fa-plus"></i> Add</button>
                                <button class="tb-btn" onclick="loadWalledGarden()"><i class="fa fa-rotate"></i> Refresh</button>
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>Dst Host</th><th>Dst Port</th><th>Server</th><th>Comment</th><th></th></tr></thead>
                                    <tbody id="walledBody"><tr><td colspan="5" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- IP BINDINGS -->
                        <div class="wb-pane" id="pane-ip-bindings">
                            <div class="wb-toolbar">
                                <button class="tb-btn primary" onclick="openModal('addBindingModal')"><i class="fa fa-plus"></i> Add</button>
                                <button class="tb-btn" onclick="loadBindings()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('bindingsBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>MAC Address</th><th>IP Address</th><th>Server</th><th>Type</th><th>Comment</th><th></th></tr></thead>
                                    <tbody id="bindingsBody"><tr><td colspan="6" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- PPPoE ACTIVE -->
                        <div class="wb-pane" id="pane-pppoe-active">
                            <div class="wb-toolbar">
                                <button class="tb-btn danger" onclick="kickSelected('pppoe')"><i class="fa fa-bolt"></i> Kick</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('pppBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>Name</th><th>IP Address</th><th>MAC / Caller</th><th>Uptime</th><th>RX / TX</th><th>Service</th><th></th></tr></thead>
                                    <tbody id="pppBody"><tr><td colspan="7" class="empty-state">Waiting for data…</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- PPPoE SECRETS -->
                        <div class="wb-pane" id="pane-pppoe-secrets">
                            <div class="wb-toolbar">
                                <button class="tb-btn primary" onclick="openModal('addPppoeModal')"><i class="fa fa-plus"></i> Add</button>
                                <button class="tb-btn" onclick="loadPppoeSecrets()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('pppSecretsBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>Name</th><th>Password</th><th>Service</th><th>Profile</th><th>Local IP</th><th>Remote IP</th><th>Comment</th><th>Status</th><th></th></tr></thead>
                                    <tbody id="pppSecretsBody"><tr><td colspan="9" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- PPPoE PROFILES -->
                        <div class="wb-pane" id="pane-pppoe-profiles">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadPppoeProfiles()"><i class="fa fa-rotate"></i> Refresh</button>
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>Name</th><th>Local Address</th><th>Remote Address</th><th>Rate Limit</th><th>DNS Server</th><th>Session Timeout</th></tr></thead>
                                    <tbody id="pppProfilesBody"><tr><td colspan="6" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ARP -->
                        <div class="wb-pane" id="pane-ip-arp">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadARP()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('arpBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>IP Address</th><th>MAC Address</th><th>Interface</th><th>Status</th><th>Published</th></tr></thead>
                                    <tbody id="arpBody"><tr><td colspan="5" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- DHCP LEASES -->
                        <div class="wb-pane" id="pane-ip-dhcp">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadDHCP()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('dhcpBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>IP Address</th><th>MAC Address</th><th>Hostname</th><th>Server</th><th>Expires After</th><th>Type</th><th>Status</th><th></th></tr></thead>
                                    <tbody id="dhcpBody"><tr><td colspan="8" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- NEIGHBORS -->
                        <div class="wb-pane" id="pane-ip-neighbors">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadNeighbors()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('neighborsBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>IP Address</th><th>MAC Address</th><th>Interface</th><th>Identity</th><th>Platform</th><th>Version</th></tr></thead>
                                    <tbody id="neighborsBody"><tr><td colspan="6" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- FIREWALL ADDRESS LIST -->
                        <div class="wb-pane" id="pane-fw-address-list">
                            <div class="wb-toolbar">
                                <button class="tb-btn primary" onclick="openModal('addFwAddrModal')"><i class="fa fa-plus"></i> Add</button>
                                <button class="tb-btn" onclick="loadFwAddrList()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search list or address…" oninput="filterTable('fwAddrBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>List</th><th>Address</th><th>Timeout</th><th>Comment</th><th>Status</th><th></th></tr></thead>
                                    <tbody id="fwAddrBody"><tr><td colspan="6" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- FIREWALL FILTER -->
                        <div class="wb-pane" id="pane-fw-filter">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadFwFilter()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('fwFilterBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>#</th><th>Chain</th><th>Src Address</th><th>Dst Address</th><th>Protocol</th><th>Action</th><th>Comment</th><th>Status</th></tr></thead>
                                    <tbody id="fwFilterBody"><tr><td colspan="8" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- NAT -->
                        <div class="wb-pane" id="pane-fw-nat">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadNAT()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('natBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>#</th><th>Chain</th><th>Src</th><th>Dst</th><th>Protocol</th><th>Action</th><th>To-Address</th><th>Comment</th></tr></thead>
                                    <tbody id="natBody"><tr><td colspan="8" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- INTERFACES -->
                        <div class="wb-pane" id="pane-interfaces">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadInterfaces()"><i class="fa fa-rotate"></i> Refresh</button>
                                <div class="tb-sep"></div>
                                <input class="tb-search" placeholder="Search…" oninput="filterTable('ifaceBody', this.value)">
                            </div>
                            <div class="wb-scroll">
                                <table class="wb-table">
                                    <thead><tr><th>Name</th><th>Type</th><th>MTU</th><th>MAC Address</th><th>TX / RX Bytes</th><th>Status</th><th></th></tr></thead>
                                    <tbody id="ifaceBody"><tr><td colspan="7" class="empty-state">Click Refresh to load</td></tr></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- LOGS -->
                        <div class="wb-pane" id="pane-logs">
                            <div class="wb-toolbar">
                                <button class="tb-btn" onclick="loadLogs()"><i class="fa fa-rotate"></i> Refresh</button>
                                <select id="logTopicFilter" style="background:var(--wb-bg);border:1px solid var(--wb-border);color:var(--wb-text);border-radius:4px;padding:2px 8px;font-family:inherit;font-size:11px;">
                                    <option value="">All topics</option>
                                    <option value="hotspot">hotspot</option>
                                    <option value="pppoe">pppoe</option>
                                    <option value="dhcp">dhcp</option>
                                    <option value="firewall">firewall</option>
                                    <option value="system">system</option>
                                    <option value="error">error</option>
                                    <option value="warning">warning</option>
                                </select>
                                <div class="tb-sep"></div>
                                <label style="font-size:10.5px;color:var(--wb-muted);margin-right:4px;">Lines:</label>
                                <select id="logLimit" style="background:var(--wb-bg);border:1px solid var(--wb-border);color:var(--wb-text);border-radius:4px;padding:2px 8px;font-family:inherit;font-size:11px;">
                                    <option value="50">50</option>
                                    <option value="100" selected>100</option>
                                    <option value="200">200</option>
                                    <option value="500">500</option>
                                </select>
                                <button class="tb-btn" style="margin-left:4px;" onclick="clearLogView()">Clear View</button>
                                <label style="font-size:10.5px;color:var(--wb-muted);margin-left:8px;">
                                    <input type="checkbox" id="logAutoRefresh" onchange="toggleLogAuto()"> Auto (10s)
                                </label>
                            </div>
                            <div style="flex:1;overflow:hidden;">
                                <div id="logContainer">Logs will appear here…</div>
                            </div>
                        </div>

                        <!-- TOOLS — PING -->
                        <div class="wb-pane" id="pane-tools-ping">
                            <div class="wb-toolbar">
                                <button class="tb-btn primary" onclick="runPing()"><i class="fa fa-play"></i> Ping</button>
                                <button class="tb-btn" onclick="document.getElementById('pingOutput').textContent=''">Clear</button>
                            </div>
                            <div style="padding:16px;flex:1;overflow-y:auto;">
                                <div style="display:flex;gap:10px;align-items:flex-end;margin-bottom:14px;flex-wrap:wrap;">
                                    <div class="wb-field" style="flex:1;min-width:160px;">
                                        <label>Host / IP Address</label>
                                        <input type="text" id="pingHost" placeholder="8.8.8.8" style="width:100%;">
                                    </div>
                                    <div class="wb-field" style="width:80px;">
                                        <label>Count</label>
                                        <select id="pingCount" style="width:100%;">
                                            <option value="4">4</option>
                                            <option value="8">8</option>
                                            <option value="10">10</option>
                                        </select>
                                    </div>
                                </div>
                                <pre id="pingOutput" style="background:var(--wb-bg);border:1px solid var(--wb-border);border-radius:4px;padding:12px;font-size:11.5px;min-height:160px;color:var(--wb-green);line-height:1.8;overflow:auto;">Run a ping to see output here…</pre>
                            </div>
                        </div>

                    </div><!-- /wb-content -->
                </div><!-- /wb-main -->

                <!-- Status Bar -->
                <div id="wb-statusbar">
                    <span>🟢 <span id="sb-status">Connecting</span></span>
                    <span>HS: <b id="sb-hs">0</b></span>
                    <span>PPPoE: <b id="sb-ppp">0</b></span>
                    <span>DHCP: <b id="sb-dhcp">0</b></span>
                    <span style="margin-left:auto;" id="sb-time"></span>
                </div>

            </div><!-- /wb-shell -->

        </main>
        <?php require_once "../partials/footer.php"; ?>
    </div>
</div>

<!-- ══ MODALS ══ -->

<!-- Add Hotspot User -->
<div class="wb-modal-overlay" id="addHsUserModal">
    <div class="wb-modal">
        <div class="wb-modal-header">Add Hotspot User <span class="close-btn" onclick="closeModal('addHsUserModal')">✕</span></div>
        <div class="wb-modal-body">
            <div class="wb-field"><label>Username *</label><input type="text" id="hsuName"></div>
            <div class="wb-field"><label>Password</label><input type="text" id="hsuPass"></div>
            <div class="wb-field"><label>Profile</label><input type="text" id="hsuProfile" placeholder="default"></div>
            <div class="wb-field"><label>MAC Address (optional)</label><input type="text" id="hsuMac" placeholder="AA:BB:CC:DD:EE:FF"></div>
            <div class="wb-field"><label>Limit Uptime (optional)</label><input type="text" id="hsuUptime" placeholder="1d 2h"></div>
            <div class="wb-field"><label>Comment</label><input type="text" id="hsuComment"></div>
        </div>
        <div class="wb-modal-footer">
            <button class="tb-btn" onclick="closeModal('addHsUserModal')">Cancel</button>
            <button class="tb-btn primary" onclick="submitAddHsUser()">Add User</button>
        </div>
    </div>
</div>

<!-- Add Walled Garden -->
<div class="wb-modal-overlay" id="addWalledModal">
    <div class="wb-modal">
        <div class="wb-modal-header">Add Walled Garden Entry <span class="close-btn" onclick="closeModal('addWalledModal')">✕</span></div>
        <div class="wb-modal-body">
            <div class="wb-field"><label>Dst Host *</label><input type="text" id="wgHost" placeholder="*.example.com"></div>
            <div class="wb-field"><label>Dst Port (optional)</label><input type="text" id="wgPort" placeholder="80"></div>
            <div class="wb-field"><label>Server (optional)</label><input type="text" id="wgServer" placeholder="all"></div>
        </div>
        <div class="wb-modal-footer">
            <button class="tb-btn" onclick="closeModal('addWalledModal')">Cancel</button>
            <button class="tb-btn primary" onclick="submitAddWalled()">Add</button>
        </div>
    </div>
</div>

<!-- Add IP Binding -->
<div class="wb-modal-overlay" id="addBindingModal">
    <div class="wb-modal">
        <div class="wb-modal-header">Add IP Binding <span class="close-btn" onclick="closeModal('addBindingModal')">✕</span></div>
        <div class="wb-modal-body">
            <div class="wb-field"><label>MAC Address *</label><input type="text" id="bindMac" placeholder="AA:BB:CC:DD:EE:FF"></div>
            <div class="wb-field"><label>IP Address (optional)</label><input type="text" id="bindIp" placeholder="192.168.1.100"></div>
            <div class="wb-field"><label>Server (optional)</label><input type="text" id="bindServer" placeholder="all"></div>
            <div class="wb-field"><label>Type</label>
                <select id="bindType">
                    <option value="bypassed">Bypassed (skip login)</option>
                    <option value="blocked">Blocked (deny access)</option>
                    <option value="regular">Regular</option>
                </select>
            </div>
        </div>
        <div class="wb-modal-footer">
            <button class="tb-btn" onclick="closeModal('addBindingModal')">Cancel</button>
            <button class="tb-btn primary" onclick="submitAddBinding()">Add Binding</button>
        </div>
    </div>
</div>

<!-- Add PPPoE Secret -->
<div class="wb-modal-overlay" id="addPppoeModal">
    <div class="wb-modal">
        <div class="wb-modal-header">Add PPPoE Secret <span class="close-btn" onclick="closeModal('addPppoeModal')">✕</span></div>
        <div class="wb-modal-body">
            <div class="wb-field"><label>Username *</label><input type="text" id="pppName"></div>
            <div class="wb-field"><label>Password *</label><input type="text" id="pppPass"></div>
            <div class="wb-field"><label>Service</label>
                <select id="pppService">
                    <option value="pppoe">pppoe</option>
                    <option value="any">any</option>
                    <option value="pptp">pptp</option>
                    <option value="l2tp">l2tp</option>
                </select>
            </div>
            <div class="wb-field"><label>Profile</label><input type="text" id="pppProfile" placeholder="default"></div>
            <div class="wb-field"><label>Local IP (optional)</label><input type="text" id="pppLocalIp" placeholder="10.0.0.1"></div>
            <div class="wb-field"><label>Remote IP (optional)</label><input type="text" id="pppRemoteIp" placeholder="10.0.0.2"></div>
            <div class="wb-field"><label>Comment</label><input type="text" id="pppComment"></div>
        </div>
        <div class="wb-modal-footer">
            <button class="tb-btn" onclick="closeModal('addPppoeModal')">Cancel</button>
            <button class="tb-btn primary" onclick="submitAddPppoe()">Add Secret</button>
        </div>
    </div>
</div>

<!-- Add Firewall Address -->
<div class="wb-modal-overlay" id="addFwAddrModal">
    <div class="wb-modal">
        <div class="wb-modal-header">Add Address to List <span class="close-btn" onclick="closeModal('addFwAddrModal')">✕</span></div>
        <div class="wb-modal-body">
            <div class="wb-field"><label>List Name *</label><input type="text" id="fwList" placeholder="blacklist"></div>
            <div class="wb-field"><label>Address *</label><input type="text" id="fwAddress" placeholder="1.2.3.4 or 1.2.3.0/24"></div>
            <div class="wb-field"><label>Timeout (optional)</label><input type="text" id="fwTimeout" placeholder="1d 2h 3m"></div>
            <div class="wb-field"><label>Comment</label><input type="text" id="fwComment"></div>
        </div>
        <div class="wb-modal-footer">
            <button class="tb-btn" onclick="closeModal('addFwAddrModal')">Cancel</button>
            <button class="tb-btn primary" onclick="submitFwAddr()">Add</button>
        </div>
    </div>
</div>

<!-- Reboot confirm -->
<div class="wb-modal-overlay" id="rebootModal">
    <div class="wb-modal" style="max-width:340px;">
        <div class="wb-modal-header" style="color:#f85149;">⟳ System Reboot <span class="close-btn" onclick="closeModal('rebootModal')">✕</span></div>
        <div class="wb-modal-body" style="text-align:center;">
            <div style="font-size:2.5rem;margin-bottom:12px;">⚠️</div>
            <div style="color:var(--wb-warn);font-size:12px;margin-bottom:6px;">This will reboot the router.</div>
            <div style="color:var(--wb-muted);font-size:11px;">All active sessions will be disconnected.<br>The router will be unreachable for ~30 seconds.</div>
        </div>
        <div class="wb-modal-footer" style="justify-content:center;">
            <button class="tb-btn" onclick="closeModal('rebootModal')">Cancel</button>
            <button class="tb-btn danger" onclick="doReboot()">⟳ Reboot Now</button>
        </div>
    </div>
</div>

<!-- Splash -->
<div id="wb-splash">
    <div style="position:relative;width:64px;height:64px;margin-bottom:28px;">
        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:64px;height:64px;">
            <rect x="12" y="28" width="40" height="28" rx="5" fill="#1f6feb" opacity="0.15" stroke="#1f6feb" stroke-width="1.5"/>
            <path d="M20 28V20a12 12 0 0 1 24 0v8" stroke="#1f6feb" stroke-width="2" stroke-linecap="round" fill="none">
                <animate attributeName="stroke" values="#1f6feb;#3fb950;#1f6feb" dur="2s" repeatCount="indefinite"/>
            </path>
            <circle cx="32" cy="42" r="4" fill="#1f6feb">
                <animate attributeName="fill" values="#1f6feb;#3fb950;#1f6feb" dur="2s" repeatCount="indefinite"/>
            </circle>
            <line x1="32" y1="46" x2="32" y2="52" stroke="#1f6feb" stroke-width="2" stroke-linecap="round">
                <animate attributeName="stroke" values="#1f6feb;#3fb950;#1f6feb" dur="2s" repeatCount="indefinite"/>
            </line>
        </svg>
        <div style="position:absolute;inset:-10px;border-radius:50%;border:1px solid #1f6feb33;animation:splashPulse 1.8s ease-out infinite;"></div>
    </div>
    <div style="font-size:13px;font-weight:700;color:#e6edf3;letter-spacing:1px;margin-bottom:6px;">WinBox — Router Management</div>
    <div id="splash-msg" style="font-size:11px;color:#58a6ff;margin-bottom:24px;min-height:16px;letter-spacing:0.5px;">Initialising secure channel…</div>
    <div style="width:240px;height:2px;background:#21262d;border-radius:2px;overflow:hidden;">
        <div id="splash-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#1f6feb,#3fb950);border-radius:2px;transition:width .4s ease;"></div>
    </div>
    <div style="margin-top:18px;font-size:10px;color:#444c56;letter-spacing:0.5px;">End-to-end encrypted · RouterOS API</div>
</div>

<div id="wb-toasts"></div>

<?php require_once "../partials/scripts.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
'use strict';

/* ── State ── */
let routerId    = null;
let ws          = null;
let lastHsRows  = [];
let lastPppRows = [];
let logAutoTimer = null;
const loaded    = {};

/* ── Utils ── */
function toast(msg, type = 'ok') {
    const wrap = document.getElementById('wb-toasts');
    const t = document.createElement('div');
    t.className = `wb-toast ${type}`;
    t.textContent = msg;
    wrap.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 250); }, 2800);
}

window.openModal  = id => document.getElementById(id).classList.add('open');
window.closeModal = id => document.getElementById(id).classList.remove('open');

function val(id) { return (document.getElementById(id) || {}).value || ''; }

function doAction(payload, onSuccess, onFail) {
    fetch('action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ router_id: routerId, ...payload })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { if (onSuccess) onSuccess(d); }
        else { toast(d.message || 'Error', 'err'); if (onFail) onFail(d); }
    })
    .catch(() => toast('Request failed', 'err'));
}

window.filterTable = function (tbodyId, search) {
    const s = search.toLowerCase();
    (document.getElementById(tbodyId)?.querySelectorAll('tr') || []).forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(s) ? '' : 'none';
    });
};

/* ── Navigation ── */
window.navTo = function (pane) {
    document.querySelectorAll('.wb-pane').forEach(p => p.classList.remove('active'));
    const el = document.getElementById('pane-' + pane);
    if (el) el.classList.add('active');
    document.querySelectorAll('.sb-item').forEach(i => i.classList.toggle('active', i.dataset.pane === pane));

    if (!loaded[pane]) {
        loaded[pane] = true;
        switch (pane) {
            case 'hotspot-users':    loadHsUsers();       break;
            case 'hotspot-hosts':    loadHsHosts();       break;
            case 'hotspot-profiles': loadHsProfiles();    break;
            case 'hotspot-walled':   loadWalledGarden();  break;
            case 'ip-bindings':      loadBindings();      break;
            case 'pppoe-secrets':    loadPppoeSecrets();  break;
            case 'pppoe-profiles':   loadPppoeProfiles(); break;
            case 'ip-arp':           loadARP();           break;
            case 'ip-dhcp':          loadDHCP();          break;
            case 'ip-neighbors':     loadNeighbors();     break;
            case 'fw-address-list':  loadFwAddrList();    break;
            case 'fw-filter':        loadFwFilter();      break;
            case 'fw-nat':           loadNAT();           break;
            case 'interfaces':       loadInterfaces();    break;
            case 'logs':             loadLogs();          break;
        }
    }
};

/* ── Charts ── */
const MAX = 25;
function mkChart(id, color) {
    return new Chart(document.getElementById(id), {
        type: 'line',
        data: { labels: [], datasets: [{ data: [], borderColor: color, borderWidth: 1.5, fill: true, backgroundColor: color + '22', tension: 0.4, pointRadius: 0 }] },
        options: { responsive: true, animation: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false, min: 0, beginAtZero: true } } }
    });
}
const charts = {
    rx: mkChart('rxChart', '#1f6feb'),
    tx: mkChart('txChart', '#da3633'),
};
function pushChart(chart, v) {
    chart.data.labels.push(new Date().toLocaleTimeString());
    chart.data.datasets[0].data.push(v);
    if (chart.data.labels.length > MAX) { chart.data.labels.shift(); chart.data.datasets[0].data.shift(); }
    chart.update('none');
}

/* ── WebSocket ── */
function setStatus(online) {
    const dot  = document.getElementById('statusDot');
    const text = document.getElementById('statusText');
    const sb   = document.getElementById('sb-status');
    dot.className    = 'status-dot ' + (online ? 'online' : 'offline');
    text.textContent = online ? 'Connected' : 'Offline';
    if (sb) sb.textContent = online ? 'Connected' : 'Offline';
}

function updateUI(d) {
    if (d.status === 'offline' || d.status === 'error') { setStatus(false); return; }
    setStatus(true);

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v || '0'; };
    set('ov-cpu',        d.cpu);
    set('ov-mem',        d.memory);
    set('ov-disk',       d.disk);
    set('ov-users',      d.users);
    set('ov-hs',         d.hotspot_users);
    set('ov-ppp',        d.pppoe_users);
    set('ov-dhcp-count', d.dhcp_leases);
    set('ov-rx',         d.rx);
    set('ov-tx',         d.tx);
    set('sb-hs',         d.hotspot_users);
    set('sb-ppp',        d.pppoe_users);
    set('sb-dhcp',       d.dhcp_leases);

    const setW = (id, v) => { const el = document.getElementById(id); if (el) el.style.width = (v || 0) + '%'; };
    setW('ov-cpu-bar',  d.cpu);
    setW('ov-mem-bar',  d.memory);
    setW('ov-disk-bar', d.disk);

    const memD = document.getElementById('ov-mem-detail');
    if (memD) memD.textContent = `${d.memory_used || '—'} / ${d.memory_total || '—'}`;
    const diskD = document.getElementById('ov-disk-detail');
    if (diskD) diskD.textContent = `${d.disk_used || '—'} / ${d.disk_total || '—'}`;

    const hn = document.getElementById('hostnameLabel');
    if (hn) hn.textContent = d.hostname ? '· ' + d.hostname : '';
    const lu = document.getElementById('lastUpdated');
    if (lu) lu.textContent = 'Updated ' + new Date().toLocaleTimeString();

    const infoBoard = document.getElementById('ov-info-board');
    if (infoBoard) infoBoard.textContent = d.board || '—';
    const infoVer = document.getElementById('ov-info-version');
    if (infoVer) infoVer.textContent = 'RouterOS ' + (d.version || '—');
    const infoUpt = document.getElementById('ov-info-uptime');
    if (infoUpt) infoUpt.textContent = 'Uptime: ' + (d.uptime || '—');

    pushChart(charts.rx, parseFloat(d.rx) || 0);
    pushChart(charts.tx, parseFloat(d.tx) || 0);

    lastHsRows  = (d.user_rows || []).filter(u => u.type === 'hotspot');
    lastPppRows = (d.user_rows || []).filter(u => u.type === 'pppoe');
    renderHsActive(lastHsRows);
    renderPppActive(lastPppRows);

    const sbHs = document.getElementById('sb-hs-count');
    if (sbHs) sbHs.textContent = d.hotspot_users || 0;
    const sbPpp = document.getElementById('sb-ppp-count');
    if (sbPpp) sbPpp.textContent = d.pppoe_users || 0;

    renderIfaceGrid(d.interfaces || []);
}

function connectWS(id) {
    if (!id) return;
    routerId = id;
    if (ws) { ws.onclose = null; ws.close(); ws = null; }
    ws = new WebSocket('wss://billing.inovatech.co.ke/ws');
    ws.onopen    = () => { setStatus(true); ws.send(JSON.stringify({ type: 'subscribe', router_id: id })); };
    ws.onmessage = ev => { try { updateUI(JSON.parse(ev.data)); } catch (e) {} };
    ws.onerror   = () => setStatus(false);
    ws.onclose   = () => { setStatus(false); ws = null; setTimeout(() => connectWS(id), 3000); };
}

/* ── Overview interfaces grid ── */
function renderIfaceGrid(ifaces) {
    const grid = document.getElementById('ifaceGrid');
    if (!ifaces.length) { grid.innerHTML = '<div style="color:var(--wb-muted);padding:0 12px;">No interfaces</div>'; return; }
    grid.innerHTML = ifaces.map(i => {
        const disabled = i.disabled || i.disabled === 'true';
        const running  = (i.running === 'true' || i.running === true) && !disabled;
        const cls   = disabled ? 'disabled' : (running ? 'up' : 'down');
        const label = disabled ? 'disabled' : (running ? 'up' : 'down');
        return `<div class="iface-card">
            <div class="iface-dot ${cls}"></div>
            <div>
                <div style="font-size:11.5px;font-weight:600;">${i.name}</div>
                <div style="font-size:10px;color:var(--wb-muted);">${i.type || 'ether'} · <span class="${running ? 'dot-online' : 'dot-offline'}">${label}</span></div>
            </div>
        </div>`;
    }).join('');
}

/* ── Hotspot Active ── */
function renderHsActive(rows) {
    const tbody = document.getElementById('hsBody');
    if (!tbody) return;
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No active hotspot sessions</td></tr>'; return; }
    tbody.innerHTML = rows.map((u, i) => `<tr>
        <td class="mono" style="font-weight:600;">${u.name || '—'}</td>
        <td class="mono">${u.ip || '—'}</td>
        <td class="mono muted">${u.mac || '—'}</td>
        <td class="muted">${u.uptime || '—'}</td>
        <td>${u.rx || '—'} / ${u.tx || '—'}</td>
        <td class="muted">${u.server || '—'}</td>
        <td><button class="act red" onclick="kickHS(${i})">Kick</button></td>
    </tr>`).join('');
}

window.kickHS = function (idx) {
    const u = lastHsRows[idx];
    if (!u || !confirm(`Kick ${u.name || u.ip}?`)) return;
    doAction({ action: 'kick_hotspot', id: u.id, name: u.name }, () => toast(`${u.name} kicked`));
};

window.kickSelected = function (type) {
    if (type === 'hotspot') {
        if (!lastHsRows.length) { toast('No active hotspot sessions', 'err'); return; }
        if (!confirm('Kick ALL active hotspot users?')) return;
        lastHsRows.forEach(u => doAction({ action: 'kick_hotspot', id: u.id, name: u.name }));
        toast('Kick commands sent');
    } else {
        if (!lastPppRows.length) { toast('No active PPPoE sessions', 'err'); return; }
        if (!confirm('Kick ALL active PPPoE users?')) return;
        lastPppRows.forEach(u => doAction({ action: 'kick_pppoe', id: u.id }));
        toast('Kick commands sent');
    }
};

/* ── Hotspot Users ── */
window.loadHsUsers = function () {
    document.getElementById('hsUsersBody').innerHTML = '<tr><td colspan="8" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_hotspot_users' }, d => renderHsUsers(d.data || []));
};

function renderHsUsers(rows) {
    const tbody = document.getElementById('hsUsersBody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No users</td></tr>'; return; }
    tbody.innerHTML = rows.map(u => {
        const disabled = u.disabled === 'true' || u.disabled === true;
        return `<tr>
            <td class="mono" style="font-weight:600;">${u.name || '—'}</td>
            <td class="mono muted">${u.password || '—'}</td>
            <td class="muted">${u.profile || 'default'}</td>
            <td class="mono muted">${u['mac-address'] || '—'}</td>
            <td class="muted">${u['limit-uptime'] || '—'}</td>
            <td class="muted">${u.comment || '—'}</td>
            <td><span class="tag ${disabled ? 'tag-disabled' : 'tag-up'}">${disabled ? 'disabled' : 'active'}</span></td>
            <td><div style="display:flex;gap:3px;">
                ${disabled
                    ? `<button class="act green" onclick="hsUserToggle('${u['.id']}','enable')">Enable</button>`
                    : `<button class="act" onclick="hsUserToggle('${u['.id']}','disable')">Disable</button>`}
                <button class="act red" onclick="delHsUser('${u['.id']}')">Delete</button>
            </div></td>
        </tr>`;
    }).join('');
}

window.hsUserToggle = function (id, action) {
    doAction({ action: action + '_hotspot_user', id }, () => { toast('Done'); loadHsUsers(); });
};
window.delHsUser = function (id) {
    if (!confirm('Delete this hotspot user?')) return;
    doAction({ action: 'delete_hotspot_user', id }, () => { toast('Deleted'); loadHsUsers(); });
};
window.submitAddHsUser = function () {
    const name = val('hsuName');
    if (!name) { toast('Username required', 'err'); return; }
    doAction({ action: 'add_hotspot_user', name, password: val('hsuPass'), profile: val('hsuProfile'), mac: val('hsuMac'), limit_uptime: val('hsuUptime'), comment: val('hsuComment') }, () => {
        closeModal('addHsUserModal');
        toast('User added');
        loadHsUsers();
        ['hsuName','hsuPass','hsuProfile','hsuMac','hsuUptime','hsuComment'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    });
};

/* ── Hotspot Hosts ── */
window.loadHsHosts = function () {
    document.getElementById('hsHostsBody').innerHTML = '<tr><td colspan="6" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_hotspot_hosts' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('hsHostsBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No hosts</td></tr>'; return; }
        tbody.innerHTML = rows.map(h => {
            const auth = h.authorized === 'true' || h.authorized === true;
            return `<tr>
                <td class="mono" style="font-weight:600;">${h['mac-address'] || '—'}</td>
                <td class="mono">${h.address || '—'}</td>
                <td class="muted">${h.server || '—'}</td>
                <td>${h.hostname || '—'}</td>
                <td class="muted">${h.comment || '—'}</td>
                <td><span class="tag ${auth ? 'tag-up' : 'tag-regular'}">${auth ? 'authorized' : 'waiting'}</span></td>
            </tr>`;
        }).join('');
    });
};

/* ── Hotspot Profiles ── */
window.loadHsProfiles = function () {
    document.getElementById('hsProfilesBody').innerHTML = '<tr><td colspan="5" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_hotspot_profiles' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('hsProfilesBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No profiles</td></tr>'; return; }
        tbody.innerHTML = rows.map(p => `<tr>
            <td style="font-weight:600;">${p.name || '—'}</td>
            <td class="mono muted">${p['rate-limit'] || '—'}</td>
            <td class="muted">${p['session-timeout'] || '—'}</td>
            <td class="muted">${p['idle-timeout'] || '—'}</td>
            <td class="muted">${p['shared-users'] || '—'}</td>
        </tr>`).join('');
    });
};

/* ── Walled Garden ── */
window.loadWalledGarden = function () {
    document.getElementById('walledBody').innerHTML = '<tr><td colspan="5" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_walled_garden' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('walledBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No entries</td></tr>'; return; }
        tbody.innerHTML = rows.map(w => `<tr>
            <td>${w['dst-host'] || '—'}</td>
            <td class="muted">${w['dst-port'] || '—'}</td>
            <td class="muted">${w.server || 'all'}</td>
            <td class="muted">${w.comment || '—'}</td>
            <td><button class="act red" onclick="delWalled('${w['.id']}')">Delete</button></td>
        </tr>`).join('');
    });
};
window.delWalled = function (id) {
    if (!confirm('Delete this walled garden entry?')) return;
    doAction({ action: 'delete_walled_garden', id }, () => { toast('Deleted'); loadWalledGarden(); });
};
window.submitAddWalled = function () {
    const host = val('wgHost');
    if (!host) { toast('Dst Host required', 'err'); return; }
    doAction({ action: 'add_walled_garden', dst_host: host, dst_port: val('wgPort'), server: val('wgServer') }, () => {
        closeModal('addWalledModal');
        toast('Entry added');
        loadWalledGarden();
        ['wgHost','wgPort','wgServer'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    });
};

/* ── IP Bindings ── */
window.loadBindings = function () {
    document.getElementById('bindingsBody').innerHTML = '<tr><td colspan="6" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_bindings' }, d => renderBindings(d.data || []));
};
function renderBindings(rows) {
    const tbody = document.getElementById('bindingsBody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No bindings</td></tr>'; return; }
    tbody.innerHTML = rows.map(b => {
        const type = b.type || 'regular';
        const blocked = type === 'blocked';
        return `<tr>
            <td class="mono" style="font-weight:600;">${b['mac-address'] || '—'}</td>
            <td class="mono">${b.address || '—'}</td>
            <td class="muted">${b.server || 'all'}</td>
            <td><span class="tag tag-${type}">${type}</span></td>
            <td class="muted">${b.comment || '—'}</td>
            <td><div style="display:flex;gap:3px;">
                ${blocked
                    ? `<button class="act green" onclick="bindingAction('unblock_binding','${b['.id']}')">Unblock</button>`
                    : `<button class="act" onclick="bindingAction('block_binding','${b['.id']}')">Block</button>`}
                <button class="act red" onclick="bindingAction('delete_binding','${b['.id']}')">Delete</button>
            </div></td>
        </tr>`;
    }).join('');
}
window.bindingAction = function (action, id) {
    const labels = { delete_binding: 'Delete', block_binding: 'Block', unblock_binding: 'Unblock' };
    if (action === 'delete_binding' && !confirm('Delete binding?')) return;
    doAction({ action, id }, () => { toast(labels[action] + 'd'); loadBindings(); });
};
window.submitAddBinding = function () {
    const mac = val('bindMac');
    if (!mac) { toast('MAC address required', 'err'); return; }
    doAction({ action: 'add_binding', mac, ip: val('bindIp'), server: val('bindServer'), type: val('bindType') }, () => {
        closeModal('addBindingModal');
        toast('Binding added');
        loadBindings();
        ['bindMac','bindIp','bindServer'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    });
};

/* ── PPPoE Active ── */
function renderPppActive(rows) {
    const tbody = document.getElementById('pppBody');
    if (!tbody) return;
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No active PPPoE sessions</td></tr>'; return; }
    tbody.innerHTML = rows.map((u, i) => `<tr>
        <td class="mono" style="font-weight:600;">${u.name || '—'}</td>
        <td class="mono">${u.ip || '—'}</td>
        <td class="mono muted">${u.mac || u['caller-id'] || '—'}</td>
        <td class="muted">${u.uptime || '—'}</td>
        <td>${u.rx || '—'} / ${u.tx || '—'}</td>
        <td class="muted">${u.service || 'pppoe'}</td>
        <td><button class="act red" onclick="kickPPP(${i})">Kick</button></td>
    </tr>`).join('');
}
window.kickPPP = function (idx) {
    const u = lastPppRows[idx];
    if (!u || !confirm(`Kick ${u.name}?`)) return;
    doAction({ action: 'kick_pppoe', id: u.id }, () => toast(`${u.name} kicked`));
};

/* ── PPPoE Secrets ── */
window.loadPppoeSecrets = function () {
    document.getElementById('pppSecretsBody').innerHTML = '<tr><td colspan="9" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_pppoe_secrets' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('pppSecretsBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="9" class="empty-state">No secrets</td></tr>'; return; }
        tbody.innerHTML = rows.map(s => {
            const disabled = s.disabled === 'true' || s.disabled === true;
            return `<tr>
                <td class="mono" style="font-weight:600;">${s.name || '—'}</td>
                <td class="mono muted">${s.password || '—'}</td>
                <td class="muted">${s.service || '—'}</td>
                <td class="muted">${s.profile || 'default'}</td>
                <td class="mono muted">${s['local-address'] || '—'}</td>
                <td class="mono muted">${s['remote-address'] || '—'}</td>
                <td class="muted">${s.comment || '—'}</td>
                <td><span class="tag ${disabled ? 'tag-disabled' : 'tag-up'}">${disabled ? 'disabled' : 'active'}</span></td>
                <td><div style="display:flex;gap:3px;">
                    ${disabled
                        ? `<button class="act green" onclick="pppToggle('${s['.id']}','enable')">Enable</button>`
                        : `<button class="act" onclick="pppToggle('${s['.id']}','disable')">Disable</button>`}
                    <button class="act red" onclick="delPppSecret('${s['.id']}')">Delete</button>
                </div></td>
            </tr>`;
        }).join('');
    });
};
window.pppToggle = function (id, action) {
    doAction({ action: action + '_pppoe_secret', id }, () => { toast('Done'); loadPppoeSecrets(); });
};
window.delPppSecret = function (id) {
    if (!confirm('Delete this secret?')) return;
    doAction({ action: 'delete_pppoe_secret', id }, () => { toast('Deleted'); loadPppoeSecrets(); });
};
window.submitAddPppoe = function () {
    const name = val('pppName'), pass = val('pppPass');
    if (!name || !pass) { toast('Username and password required', 'err'); return; }
    doAction({ action: 'add_pppoe_secret', name, password: pass, service: val('pppService'), profile: val('pppProfile'), local_ip: val('pppLocalIp'), remote_ip: val('pppRemoteIp'), comment: val('pppComment') }, () => {
        closeModal('addPppoeModal');
        toast('Secret added');
        loadPppoeSecrets();
        ['pppName','pppPass','pppProfile','pppLocalIp','pppRemoteIp','pppComment'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    });
};

/* ── PPPoE Profiles ── */
window.loadPppoeProfiles = function () {
    document.getElementById('pppProfilesBody').innerHTML = '<tr><td colspan="6" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_pppoe_profiles' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('pppProfilesBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No profiles</td></tr>'; return; }
        tbody.innerHTML = rows.map(p => `<tr>
            <td style="font-weight:600;">${p.name || '—'}</td>
            <td class="mono muted">${p['local-address'] || '—'}</td>
            <td class="mono muted">${p['remote-address'] || '—'}</td>
            <td class="mono muted">${p['rate-limit'] || '—'}</td>
            <td class="muted">${p['dns-server'] || '—'}</td>
            <td class="muted">${p['session-timeout'] || '—'}</td>
        </tr>`).join('');
    });
};

/* ── ARP ── */
window.loadARP = function () {
    document.getElementById('arpBody').innerHTML = '<tr><td colspan="5" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_arp' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('arpBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No ARP entries</td></tr>'; return; }
        tbody.innerHTML = rows.map(a => `<tr>
            <td class="mono" style="font-weight:600;">${a.address || '—'}</td>
            <td class="mono muted">${a['mac-address'] || '—'}</td>
            <td class="muted">${a.interface || '—'}</td>
            <td><span class="tag ${a.dynamic === 'true' ? 'tag-dynamic' : 'tag-static'}">${a.dynamic === 'true' ? 'dynamic' : 'static'}</span></td>
            <td class="muted">${a.published === 'true' ? 'yes' : 'no'}</td>
        </tr>`).join('');
    });
};

/* ── DHCP Leases ── */
window.loadDHCP = function () {
    document.getElementById('dhcpBody').innerHTML = '<tr><td colspan="8" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_dhcp_leases' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('dhcpBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No leases</td></tr>'; return; }
        tbody.innerHTML = rows.map(l => {
            const dyn = l.dynamic === 'true' || l.dynamic === true;
            return `<tr>
                <td class="mono" style="font-weight:600;">${l.address || '—'}</td>
                <td class="mono muted">${l['mac-address'] || '—'}</td>
                <td>${l.hostname || l['host-name'] || '—'}</td>
                <td class="muted">${l.server || '—'}</td>
                <td class="muted">${l['expires-after'] || '—'}</td>
                <td><span class="tag ${dyn ? 'tag-dynamic' : 'tag-static'}">${dyn ? 'dynamic' : 'static'}</span></td>
                <td><span class="tag tag-up">bound</span></td>
                <td><div style="display:flex;gap:3px;">
                    ${dyn ? `<button class="act blue" onclick="makeStatic('${l['.id']}')">Make Static</button>` : ''}
                    <button class="act red" onclick="deleteLease('${l['.id']}')">Delete</button>
                </div></td>
            </tr>`;
        }).join('');
    });
};
window.makeStatic  = function (id) { doAction({ action: 'make_static_lease', id }, () => { toast('Made static'); loadDHCP(); }); };
window.deleteLease = function (id) {
    if (!confirm('Delete this DHCP lease?')) return;
    doAction({ action: 'delete_dhcp_lease', id }, () => { toast('Deleted'); loadDHCP(); });
};

/* ── Neighbors ── */
window.loadNeighbors = function () {
    document.getElementById('neighborsBody').innerHTML = '<tr><td colspan="6" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_neighbors' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('neighborsBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No neighbors</td></tr>'; return; }
        tbody.innerHTML = rows.map(n => `<tr>
            <td class="mono" style="font-weight:600;">${n.address || '—'}</td>
            <td class="mono muted">${n['mac-address'] || '—'}</td>
            <td class="muted">${n.interface || '—'}</td>
            <td>${n.identity || '—'}</td>
            <td class="muted">${n.platform || '—'}</td>
            <td class="muted">${n.version || '—'}</td>
        </tr>`).join('');
    });
};

/* ── Firewall Address List ── */
window.loadFwAddrList = function () {
    document.getElementById('fwAddrBody').innerHTML = '<tr><td colspan="6" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_firewall_address_lists' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('fwAddrBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No entries</td></tr>'; return; }
        tbody.innerHTML = rows.map(a => {
            const disabled = a.disabled === 'true' || a.disabled === true;
            return `<tr>
                <td style="font-weight:600;color:#79c0ff;">${a.list || '—'}</td>
                <td class="mono">${a.address || '—'}</td>
                <td class="muted">${a.timeout || '—'}</td>
                <td class="muted">${a.comment || '—'}</td>
                <td><span class="tag ${disabled ? 'tag-disabled' : 'tag-up'}">${disabled ? 'disabled' : 'active'}</span></td>
                <td><div style="display:flex;gap:3px;">
                    ${disabled
                        ? `<button class="act green" onclick="fwAddrToggle('${a['.id']}','enable')">Enable</button>`
                        : `<button class="act" onclick="fwAddrToggle('${a['.id']}','disable')">Disable</button>`}
                    <button class="act red" onclick="delFwAddr('${a['.id']}')">Delete</button>
                </div></td>
            </tr>`;
        }).join('');
    });
};
window.fwAddrToggle = function (id, action) {
    doAction({ action: action + '_firewall_address', id }, () => { toast('Done'); loadFwAddrList(); });
};
window.delFwAddr = function (id) {
    if (!confirm('Delete this address?')) return;
    doAction({ action: 'delete_firewall_address', id }, () => { toast('Deleted'); loadFwAddrList(); });
};
window.submitFwAddr = function () {
    const list = val('fwList'), address = val('fwAddress');
    if (!list || !address) { toast('List and address required', 'err'); return; }
    doAction({ action: 'add_firewall_address', list, address, timeout: val('fwTimeout'), comment: val('fwComment') }, () => {
        closeModal('addFwAddrModal');
        toast('Address added');
        loadFwAddrList();
        ['fwList','fwAddress','fwTimeout','fwComment'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    });
};

/* ── Firewall Filter ── */
window.loadFwFilter = function () {
    document.getElementById('fwFilterBody').innerHTML = '<tr><td colspan="8" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_firewall_rules' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('fwFilterBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No rules</td></tr>'; return; }
        tbody.innerHTML = rows.map((r, i) => {
            const disabled = r.disabled === 'true';
            const actionColor = { accept: 'tag-up', drop: 'tag-blocked', reject: 'tag-blocked', passthrough: 'tag-dynamic' }[r.action] || 'tag-regular';
            return `<tr style="${disabled ? 'opacity:0.45;' : ''}">
                <td class="muted">${i + 1}</td>
                <td class="mono">${r.chain || '—'}</td>
                <td class="mono muted">${r['src-address'] || '—'}</td>
                <td class="mono muted">${r['dst-address'] || '—'}</td>
                <td class="muted">${r.protocol || '—'}</td>
                <td><span class="tag ${actionColor}">${r.action || '—'}</span></td>
                <td class="muted">${r.comment || '—'}</td>
                <td><span class="tag ${disabled ? 'tag-disabled' : 'tag-up'}">${disabled ? 'disabled' : 'enabled'}</span></td>
            </tr>`;
        }).join('');
    });
};

/* ── NAT ── */
window.loadNAT = function () {
    document.getElementById('natBody').innerHTML = '<tr><td colspan="8" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_nat_rules' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('natBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No NAT rules</td></tr>'; return; }
        tbody.innerHTML = rows.map((r, i) => `<tr>
            <td class="muted">${i + 1}</td>
            <td class="mono">${r.chain || '—'}</td>
            <td class="mono muted">${r['src-address'] || '—'}</td>
            <td class="mono muted">${r['dst-address'] || '—'}</td>
            <td class="muted">${r.protocol || '—'}</td>
            <td><span class="tag tag-dynamic">${r.action || '—'}</span></td>
            <td class="mono muted">${r['to-addresses'] || '—'}</td>
            <td class="muted">${r.comment || '—'}</td>
        </tr>`).join('');
    });
};

/* ── Interfaces ── */
window.loadInterfaces = function () {
    document.getElementById('ifaceBody').innerHTML = '<tr><td colspan="7" class="empty-state">Loading…</td></tr>';
    doAction({ action: 'get_interfaces' }, d => {
        const rows = d.data || [];
        const tbody = document.getElementById('ifaceBody');
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No interfaces</td></tr>'; return; }
        tbody.innerHTML = rows.map(i => {
            const disabled = i.disabled === 'true' || i.disabled === true;
            const running  = (i.running === 'true' || i.running === true) && !disabled;
            return `<tr>
                <td style="font-weight:600;">${i.name || '—'}</td>
                <td class="muted">${i.type || 'ether'}</td>
                <td class="muted">${i.mtu || '—'}</td>
                <td class="mono muted">${i['mac-address'] || '—'}</td>
                <td class="mono muted">${i['tx-byte'] || '0'} / ${i['rx-byte'] || '0'}</td>
                <td><span class="tag ${disabled ? 'tag-disabled' : (running ? 'tag-up' : 'tag-down')}">${disabled ? 'disabled' : (running ? 'up' : 'down')}</span></td>
                <td>${disabled
                    ? `<button class="act green" onclick="toggleIface('${i['.id']}','enable')">Enable</button>`
                    : `<button class="act" onclick="toggleIface('${i['.id']}','disable')">Disable</button>`}</td>
            </tr>`;
        }).join('');
    });
};
window.toggleIface = function (id, action) {
    if (!confirm(`${action} this interface?`)) return;
    doAction({ action: action + '_interface', id }, () => { toast('Done'); loadInterfaces(); });
};

/* ── Logs ── */
window.loadLogs = function () {
    const logEl = document.getElementById('logContainer');
    logEl.textContent = 'Loading…';
    const limit = val('logLimit') || 100;
    doAction({ action: 'get_logs', limit: parseInt(limit) }, d => {
        const rows = d.data || [];
        const topicFilter = val('logTopicFilter').toLowerCase();
        const filtered = topicFilter ? rows.filter(l => (l.topics || '').toLowerCase().includes(topicFilter)) : rows;
        if (!filtered.length) { logEl.textContent = 'No log entries found'; return; }
        logEl.innerHTML = filtered.map(l => {
            const topics = (l.topics || '').toLowerCase();
            let cls = 'log-info';
            if (topics.includes('error') || topics.includes('critical')) cls = 'log-error';
            else if (topics.includes('warning') || topics.includes('warn')) cls = 'log-warn';
            else if (topics.includes('debug')) cls = 'log-debug';
            return `<div class="${cls}"><span style="color:var(--wb-muted);">${l.time || ''}</span> <span style="color:#58a6ff;font-size:10px;">[${l.topics || ''}]</span> ${(l.message || '').replace(/</g, '&lt;')}</div>`;
        }).join('');
        logEl.scrollTop = logEl.scrollHeight;
    });
};
window.clearLogView  = function () { document.getElementById('logContainer').textContent = 'Cleared. Press Refresh to reload.'; };
window.toggleLogAuto = function () {
    if (document.getElementById('logAutoRefresh').checked) {
        logAutoTimer = setInterval(loadLogs, 10000);
    } else {
        clearInterval(logAutoTimer);
    }
};

/* ── Ping ── */
window.runPing = function () {
    const host = val('pingHost').trim();
    const count = val('pingCount');
    if (!host) { toast('Enter a host or IP', 'err'); return; }
    const out = document.getElementById('pingOutput');
    out.textContent = `PING ${host} (${count} packets)…\n`;
    doAction({ action: 'ping', host, count: parseInt(count) }, d => {
        const rows = d.data || [];
        if (!rows.length) { out.textContent += 'No response or unsupported.\n'; return; }
        rows.forEach(r => {
            if (r.status === 'timeout') out.textContent += `Request timeout\n`;
            else out.textContent += `Reply from ${r.host || host}: time=${r['response-time'] || r.time || '?'}ms ttl=${r.ttl || '?'}\n`;
        });
        const sent = rows.length;
        const recv = rows.filter(r => r.status !== 'timeout').length;
        out.textContent += `\n--- ${host} ping statistics ---\n${sent} packets, ${recv} received, ${Math.round((sent - recv) / sent * 100)}% loss`;
    });
};

/* ── Reboot ── */
window.openReboot = function () { openModal('rebootModal'); };
window.doReboot   = function () {
    closeModal('rebootModal');
    toast('Reboot command sent — router going down…', 'info');
    doAction({ action: 'reboot' }, () => {
        toast('Router rebooting. Reconnecting in 30s…', 'info');
        setTimeout(() => connectWS(routerId), 30000);
    });
};

/* ── Clock ── */
setInterval(() => { const el = document.getElementById('sb-time'); if (el) el.textContent = new Date().toLocaleTimeString(); }, 1000);

/* ── Init ── */
const routerSelect = document.getElementById('routerSelect');
routerSelect.addEventListener('change', function () {
    Object.keys(loaded).forEach(k => delete loaded[k]);
    connectWS(parseInt(this.value));
    navTo('overview');
});
if (routerSelect.value) connectWS(parseInt(routerSelect.value));

/* ── Splash ── */
(function () {
    const splash = document.getElementById('wb-splash');
    const bar    = document.getElementById('splash-bar');
    const msg    = document.getElementById('splash-msg');
    if (!splash) return;
    const steps = [[5,'Connecting to secure tunnel…'],[20,'Authenticating session…'],[40,'Establishing RouterOS channel…'],[60,'Handshaking with router…'],[80,'Loading router data…'],[95,'Almost ready…']];
    let stepIdx = 0;
    const advance = setInterval(() => {
        if (stepIdx < steps.length) { bar.style.width = steps[stepIdx][0] + '%'; msg.textContent = steps[stepIdx][1]; stepIdx++; }
    }, 420);
    const observer = new MutationObserver(() => {
        const dot = document.getElementById('statusDot');
        if (dot && dot.classList.contains('online')) {
            clearInterval(advance);
            bar.style.width  = '100%';
            msg.textContent  = 'Secure tunnel established ✓';
            setTimeout(() => { splash.style.opacity = '0'; setTimeout(() => { splash.style.display = 'none'; }, 520); }, 480);
            observer.disconnect();
        }
    });
    observer.observe(document.getElementById('statusDot'), { attributes: true, attributeFilter: ['class'] });
    setTimeout(() => { clearInterval(advance); splash.style.opacity = '0'; setTimeout(() => { splash.style.display = 'none'; }, 520); observer.disconnect(); }, 12000);
})();

})();
</script>

</body>
</html>