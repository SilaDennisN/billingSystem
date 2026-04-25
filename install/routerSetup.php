<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

$router_id = $_GET['router_id'] ?? null;
if (!$router_id) {
    $_SESSION['error'] = "No router selected";
    header("Location: ../routers");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();
if (!$router) {
    $_SESSION['error'] = "Router not found";
    header("Location: ../routers");
    exit;
}

$base_url      = "https://billing.inovatech.co.ke";
$bootstrap_url = "{$base_url}/install/bootstrap.php?router_id={$router['router_id']}";

$cmd_fetch  = '/tool fetch mode=https url="' . $bootstrap_url . '" dst-path=bootstrap.rsc';
$cmd_import = '/import bootstrap.rsc';

// ── Manual terminal commands ───────────────────────────────────────────────
//
// Single-bridge topology:
//   ether1              → WAN (DHCP client, NAT masquerade)
//   bridge (ether2–5 + wlan1)  → all clients, same L2
//     192.168.50.1/24    router IP, hotspot gateway
//
// Hotspot (WiFi + wired pre-auth) and PPPoE (wired subscribers) both bind
// to the same bridge. Different pools separate their IP ranges.
//   Hotspot pool  → 192.168.50.10–192.168.50.150
//   PPPoE pool    → 192.168.50.200–192.168.50.250
//
// WireGuard interface → wg-billing
//
// These commands must be run locally in WinBox terminal — touching bridge
// ports and IPs over the API disconnects the session.

$lan_commands = implode("\n", [
    '# ── 0. Clear default config (keeps WireGuard intact) ──────────────',
    '# Remove wlan1 from any factory bridge so we can add it to ours cleanly.',
    '/interface bridge port remove [find interface=wlan1]',
    '# Disable any factory DHCP servers so they stop handing out 192.168.88.x.',
    '/ip dhcp-server disable [find]',
    '# Remove the factory 192.168.88.1 address to avoid IP conflicts.',
    '/ip address remove [find address~"192.168.88"]',
    '',
    '# ── 1. Single bridge — wired ports + WiFi, all on one L2 ───────────',
    '/interface bridge add name=bridge comment="inovatech-main"',
    '/interface bridge port add interface=ether2 bridge=bridge comment="inovatech-lan"',
    '/interface bridge port add interface=ether3 bridge=bridge comment="inovatech-lan"',
    '/interface bridge port add interface=ether4 bridge=bridge comment="inovatech-lan"',
    '/interface bridge port add interface=ether5 bridge=bridge comment="inovatech-lan"',
    '/interface bridge port add interface=wlan1  bridge=bridge comment="inovatech-lan"',
    '',
    '# ── 2. Router IP on bridge ───────────────────────────────────────────',
    '# This is also the hotspot gateway and PPPoE local address.',
    '/ip address add address=192.168.50.1/24 interface=bridge comment="inovatech-lan"',
    '',
    '# ── 3. WAN — DHCP client on ether1 ─────────────────────────────────',
    '/ip dhcp-client add interface=ether1 disabled=no add-default-route=yes use-peer-dns=yes comment="inovatech-wan"',
    '',
    '# ── 4. NAT masquerade ────────────────────────────────────────────────',
    '# All LAN/hotspot/PPPoE traffic exits via ether1 with NAT.',
    '/ip firewall nat add chain=srcnat action=masquerade out-interface=ether1 comment="inovatech-masquerade"',
    '',
    '# ── 5. Allow WireGuard VPN — REQUIRED before Step 5 ─────────────────',
    '# The default MikroTik firewall drops all input not from established',
    '# connections. This rule opens the VPN interface so the billing server',
    '# can reach the router API in Step 5. place-before=0 puts it at the top.',
    '/ip firewall filter add chain=input in-interface=wg-billing action=accept place-before=0 comment="inovatech-vpn"',
]);

// ── Post-provisioning fix commands ────────────────────────────────────────
//
// Run these AFTER the API provisioning completes successfully.
// They fix issues that the API cannot handle reliably across all RouterOS
// versions — specifically wlan1 mode/state and the hotspot server.
//
// Fix A: Release wlan1 from CAPsMAN if it was managed (XS flags).
//   If wlan1 shows XS flags in Interfaces, CAPsMAN has claimed it.
//   These two commands disable CAP client and CAPsMAN manager.
//
// Fix B: Set wlan1 to AP-bridge mode and enable it.
//   Factory default is often mode=station (client mode) which cannot
//   broadcast. This forces it into access-point mode.
//
// Fix C: Create the hotspot server manually.
//   On some RouterOS versions the /ip/hotspot API path returns session
//   data instead of server list, causing the add to silently fail.
//   This command creates it directly in the terminal.

$fix_commands = implode("\n", [
    '# ── Fix A: Release wlan1 from CAPsMAN (only needed if wlan1 shows XS flags) ──',
    '# Run /interface wireless print first — if you see X and S flags on wlan1,',
    '# CAPsMAN is managing it. These two commands release it back to local control.',
    '/interface wireless cap set enabled=no',
    '/caps-man manager set enabled=no',
    '',
    '# ── Fix B: Set wlan1 to AP mode and enable it ────────────────────────────────',
    '# Factory default is mode=station (WiFi client). This sets it to access-point',
    '# mode so it can broadcast the hotspot SSID. Run this even if Fix A was not needed.',
    '/interface wireless set wlan1 mode=ap-bridge disabled=no ssid="Inovatech WiFi"',
    '',
    '# Verify — should show R (running) flag and mode=ap-bridge with no X flag.',
    '/interface wireless print where name=wlan1',
    '',
    '# ── Fix C: Create hotspot server (if IP → Hotspot shows no server) ───────────',
    '# On some RouterOS versions the API cannot create the hotspot server reliably.',
    '# If IP → Hotspot is empty after provisioning, run this command manually.',
    '/ip hotspot add name=hotspot1 interface=bridge address-pool=hotspot_pool profile=inovatech-profile disabled=no',
    '',
    '# Verify — should show hotspot1 listed and running.',
    '/ip hotspot print',
]);

?>
<?php require_once "../partials/head.php"; ?>
<style>
/* ── Provisioning page styles ─────────────────────────────────────────── */
.step-card {
    border-radius: 10px;
    border: 1px solid #dee2e6;
    margin-bottom: 1rem;
    overflow: hidden;
    transition: box-shadow .15s;
}
.step-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.08); }

.step-header {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1.25rem;
    font-weight: 600;
    font-size: .95rem;
}
.step-header .step-num {
    width: 28px; height: 28px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem; font-weight: 700; flex-shrink: 0;
}
.step-body { padding: 1.1rem 1.25rem; background: #fff; }

/* colour themes per step */
.step-neutral  .step-header { background: #f8f9fa; color: #212529; }
.step-neutral  .step-num    { background: #6c757d; color: #fff; }
.step-blue     .step-header { background: #e7f1ff; color: #0a58ca; }
.step-blue     .step-num    { background: #0d6efd; color: #fff; }
.step-vpn      .step-header { background: #e8f5e9; color: #146c43; border-bottom: 2px solid #198754; }
.step-vpn      .step-num    { background: #198754; color: #fff; }
.step-warning  .step-header { background: #fff8e1; color: #664d03; border-bottom: 2px solid #ffc107; }
.step-warning  .step-num    { background: #ffc107; color: #212529; }
.step-success  .step-header { background: #d1e7dd; color: #0a3622; border-bottom: 2px solid #198754; }
.step-success  .step-num    { background: #198754; color: #fff; }
.step-danger   .step-header { background: #fff3cd; color: #664d03; border-bottom: 2px solid #fd7e14; }
.step-danger   .step-num    { background: #fd7e14; color: #fff; }
.step-upload   .step-header { background: #e7f1ff; color: #0a58ca; }
.step-upload   .step-num    { background: #0d6efd; color: #fff; }

/* terminal blocks */
.terminal-block {
    background: #1e1e2e;
    border-radius: 7px;
    overflow: hidden;
    font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
}
.terminal-topbar {
    background: #2a2a3d;
    padding: .35rem .75rem;
    display: flex;
    align-items: center;
    gap: .4rem;
}
.terminal-topbar .dot {
    width: 10px; height: 10px; border-radius: 50%;
}
.dot-red    { background: #ff5f57; }
.dot-yellow { background: #febc2e; }
.dot-green  { background: #28c840; }
.terminal-label {
    font-size: .7rem;
    color: #888;
    margin-left: auto;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.terminal-body {
    padding: .85rem 1rem;
    color: #cdd6f4;
    font-size: .78rem;
    line-height: 1.75;
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 380px;
    overflow-y: auto;
}
.terminal-body .cmd-comment { color: #6c7086; }
.terminal-body .cmd-prompt  { color: #a6e3a1; }

.copy-overlay-btn {
    position: absolute; top: .5rem; right: .5rem;
    z-index: 2;
}

/* topology badges */
.topo-badge {
    display: flex; align-items: center; gap: .5rem;
    padding: .5rem .85rem;
    border-radius: 8px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    font-size: .78rem;
}
.topo-badge .material-icons { font-size: 1.1rem; }

/* provision step cards */
.prov-step {
    display: flex; align-items: flex-start; gap: .6rem;
    padding: .65rem .85rem;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
    transition: background .2s;
}
.prov-step.running { background: #fff8e1; border-color: #ffc107; }
.prov-step.ok      { background: #d1e7dd; border-color: #198754; }
.prov-step.error   { background: #f8d7da; border-color: #dc3545; }

/* fix-command section tags */
.fix-tag {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .7rem; font-weight: 600;
    padding: .15rem .5rem; border-radius: 4px;
    text-transform: uppercase; letter-spacing: .05em;
    margin-bottom: .4rem;
}
.fix-tag-a { background: #fff3cd; color: #664d03; border: 1px solid #ffc107; }
.fix-tag-b { background: #cfe2ff; color: #084298; border: 1px solid #9ec5fe; }
.fix-tag-c { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }

.fix-cmd-block { margin-bottom: 1.25rem; }
.fix-cmd-block:last-child { margin-bottom: 0; }
</style>

<body class="nav-fixed bg-light">
<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
    <?php require_once "../partials/sidebar.php"; ?>
    <div id="layoutDrawer_content">
        <main>
            <header class="bg-dark">
                <div class="container-xl px-5 py-3 d-flex align-items-center gap-3">
                    <a href="../routers" class="btn btn-sm btn-outline-light">
                        <i class="material-icons align-middle" style="font-size:.95rem">arrow_back</i>
                    </a>
                    <div>
                        <h1 class="text-white mb-0" style="font-size:1.35rem;font-weight:700">Router Provisioning</h1>
                        <div class="text-white-50 small"><?= htmlspecialchars($router['name']) ?> &mdash; <?= htmlspecialchars($router['vpn_ip'] ?? '') ?></div>
                    </div>
                </div>
            </header>

            <div class="container-xl px-5 mt-4 pb-5">

                <!-- ── Network topology summary ──────────────────────────── -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body py-3">
                        <p class="fw-semibold mb-2 small text-muted text-uppercase" style="letter-spacing:.06em">
                            <i class="material-icons align-middle me-1" style="font-size:.9rem">account_tree</i> Network Topology
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="topo-badge">
                                <i class="material-icons text-primary">router</i>
                                <div><div class="fw-semibold">ether1</div><div class="text-muted">WAN — DHCP client + NAT</div></div>
                            </div>
                            <div class="topo-badge">
                                <i class="material-icons text-success">device_hub</i>
                                <div><div class="fw-semibold">bridge (ether2–5 + wlan1)</div><div class="text-muted">192.168.50.1/24 — all clients</div></div>
                            </div>
                            <div class="topo-badge">
                                <i class="material-icons text-warning">wifi</i>
                                <div><div class="fw-semibold">Hotspot pool</div><div class="text-muted">.10–.150 → captive portal</div></div>
                            </div>
                            <div class="topo-badge">
                                <i class="material-icons text-info">cable</i>
                                <div><div class="fw-semibold">PPPoE pool</div><div class="text-muted">.200–.250 → dial-in</div></div>
                            </div>
                            <div class="topo-badge">
                                <i class="material-icons text-secondary">vpn_lock</i>
                                <div><div class="fw-semibold">wg-billing</div><div class="text-muted">WireGuard → billing VPS</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     STEP 1 — Internet
                ════════════════════════════════════════════════════════ -->
                <div class="step-card step-neutral">
                    <div class="step-header">
                        <span class="step-num">1</span>
                        <i class="material-icons text-muted" style="font-size:1.1rem">power</i>
                        Connect Router to Internet
                    </div>
                    <div class="step-body">
                        <p class="mb-0 text-muted small">
                            Plug <strong class="text-dark">ether1</strong> into your internet source (ONT, modem, or uplink) and power on the router.
                            Wait for the status LED to stabilise before proceeding.
                        </p>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     STEP 2 — WinBox
                ════════════════════════════════════════════════════════ -->
                <div class="step-card step-blue">
                    <div class="step-header">
                        <span class="step-num">2</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#0d6efd">computer</i>
                        Open WinBox — Connect to <code style="font-size:.85em">192.168.88.1</code>
                    </div>
                    <div class="step-body">
                        <p class="small text-muted mb-3">Username <code>admin</code>, no password. Open <strong>New Terminal</strong> once inside.</p>
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-sm-3">
                                <a href="https://mt.lv/winbox" target="_blank" class="btn btn-outline-primary w-100 btn-sm">
                                    <i class="material-icons align-middle me-1" style="font-size:.9rem">computer</i> WinBox 32-bit
                                </a>
                            </div>
                            <div class="col-6 col-sm-3">
                                <a href="https://mt.lv/winbox64" target="_blank" class="btn btn-outline-primary w-100 btn-sm">
                                    <i class="material-icons align-middle me-1" style="font-size:.9rem">computer</i> WinBox 64-bit
                                </a>
                            </div>
                            <div class="col-6 col-sm-3">
                                <a href="https://apps.apple.com/app/winbox-for-mikrotik/id1533843974" target="_blank" class="btn btn-outline-secondary w-100 btn-sm">
                                    <i class="material-icons align-middle me-1" style="font-size:.9rem">phone_iphone</i> iOS
                                </a>
                            </div>
                            <div class="col-6 col-sm-3">
                                <a href="https://play.google.com/store/apps/details?id=com.mikrotik.android.tikapp" target="_blank" class="btn btn-outline-secondary w-100 btn-sm">
                                    <i class="material-icons align-middle me-1" style="font-size:.9rem">android</i> Android
                                </a>
                            </div>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-dark text-white border-dark"><i class="material-icons" style="font-size:1rem">terminal</i></span>
                            <input type="text" class="form-control font-monospace bg-dark text-white border-dark" value="ssh admin@192.168.88.1" readonly id="ssh-cmd">
                            <button class="btn btn-outline-secondary" onclick="copyText('ssh-cmd', this)" title="Copy">
                                <i class="material-icons" style="font-size:1rem">content_copy</i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     STEP 3 — WireGuard VPN
                ════════════════════════════════════════════════════════ -->
                <div class="step-card step-vpn">
                    <div class="step-header">
                        <span class="step-num">3</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#198754">vpn_lock</i>
                        Run 2 Commands — Sets Up WireGuard VPN Only
                    </div>
                    <div class="step-body">
                        <p class="text-muted small mb-3">
                            These commands create the WireGuard interface and establish the VPN tunnel.
                            <strong>Your network config is not touched.</strong>
                        </p>

                        <p class="small fw-semibold mb-1 text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">CMD 1 — Download VPN script</p>
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-dark font-monospace small">$</span>
                            <input type="text" class="form-control font-monospace bg-dark text-white border-dark" style="font-size:.78rem" value="<?= htmlspecialchars($cmd_fetch) ?>" readonly id="cmd1">
                            <button class="btn btn-outline-secondary" onclick="copyText('cmd1',this)" title="Copy">
                                <i class="material-icons" style="font-size:1rem">content_copy</i>
                            </button>
                        </div>

                        <p class="small fw-semibold mb-1 text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em">CMD 2 — Run VPN script</p>
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-dark text-white border-dark font-monospace small">$</span>
                            <input type="text" class="form-control font-monospace bg-dark text-white border-dark" style="font-size:.78rem" value="<?= htmlspecialchars($cmd_import) ?>" readonly id="cmd2">
                            <button class="btn btn-outline-secondary" onclick="copyText('cmd2',this)" title="Copy">
                                <i class="material-icons" style="font-size:1rem">content_copy</i>
                            </button>
                        </div>

                        <div class="d-flex gap-3 align-items-center flex-wrap">
                            <div class="alert alert-success d-flex align-items-start gap-2 py-2 mb-0 flex-grow-1">
                                <i class="material-icons mt-1" style="font-size:1rem">info</i>
                                <div class="small">
                                    Watch <strong>WinBox → Log</strong> for <strong>"WireGuard DONE — VPN tunnel is up"</strong>, then continue to Step 4.
                                </div>
                            </div>
                            <div class="text-center">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?= urlencode($cmd_fetch . "\n" . $cmd_import) ?>&size=90x90&margin=4"
                                    class="rounded border" alt="QR — VPN commands" title="Scan on mobile WinBox">
                                <div class="text-muted" style="font-size:.65rem;margin-top:.3rem">Mobile scan</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     STEP 4 — LAN commands
                ════════════════════════════════════════════════════════ -->
                <div class="step-card step-warning">
                    <div class="step-header">
                        <span class="step-num">4</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#997404">terminal</i>
                        Paste LAN Commands in WinBox Terminal
                    </div>
                    <div class="step-body">
                        <p class="text-muted small mb-2">
                            Clears factory config, creates the single bridge with all ports (including WiFi),
                            sets the router IP, configures NAT, and <strong class="text-danger">opens the firewall for the VPN</strong>.
                            <strong>Select All → Copy → Paste into WinBox terminal → Enter.</strong>
                        </p>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-secondary fw-normal">Clears factory 192.168.88.x</span>
                            <span class="badge bg-success fw-normal">bridge: ether2–5 + wlan1</span>
                            <span class="badge bg-primary fw-normal">192.168.50.1/24 on bridge</span>
                            <span class="badge bg-primary fw-normal">WAN + NAT on ether1</span>
                            <span class="badge bg-danger fw-normal">🔒 Firewall: allow VPN</span>
                        </div>

                        <div class="position-relative">
                            <div class="terminal-block">
                                <div class="terminal-topbar">
                                    <span class="dot dot-red"></span>
                                    <span class="dot dot-yellow"></span>
                                    <span class="dot dot-green"></span>
                                    <span class="terminal-label">WinBox Terminal — LAN Setup</span>
                                </div>
                                <div class="terminal-body" id="lan-commands-display"><?php
                                    foreach (explode("\n", $lan_commands) as $line) {
                                        if (str_starts_with(trim($line), '#')) {
                                            echo '<span class="cmd-comment">' . htmlspecialchars($line) . '</span>' . "\n";
                                        } elseif (trim($line) === '') {
                                            echo "\n";
                                        } else {
                                            echo '<span class="cmd-prompt">$ </span>' . htmlspecialchars($line) . "\n";
                                        }
                                    }
                                ?></div>
                            </div>
                            <textarea id="lan-commands" class="d-none" readonly><?= htmlspecialchars($lan_commands) ?></textarea>
                            <button class="btn btn-warning btn-sm copy-overlay-btn" onclick="copyText('lan-commands', this)">
                                <i class="material-icons align-middle" style="font-size:.9rem">content_copy</i> Copy All
                            </button>
                        </div>

                        <div class="alert alert-warning d-flex align-items-start gap-2 py-2 mt-3 mb-0">
                            <i class="material-icons mt-1" style="font-size:1rem">warning</i>
                            <div class="small">
                                After pasting, your WinBox session on <code>192.168.88.1</code> will disconnect.
                                Reconnect via <code>192.168.50.1</code> if needed, then continue to Step 5.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     STEP 5 — API Provision
                ════════════════════════════════════════════════════════ -->
                <div class="step-card step-success">
                    <div class="step-header">
                        <span class="step-num">5</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#198754">rocket_launch</i>
                        Provision — Hotspot, PPPoE, Firewall, DNS
                    </div>
                    <div class="step-body">
                        <p class="text-muted small mb-3">
                            Once LAN commands are pasted and the VPN is up, click below.
                            The billing server will apply the remaining config over the VPN tunnel.
                        </p>

                        <div class="row g-2 mb-4">
                            <?php
                            $prov_steps = [
                                ['icon' => 'wifi',     'label' => 'Hotspot + Captive Portal', 'desc' => 'SSID, DHCP, hotspot server on bridge, walled garden'],
                                ['icon' => 'cable',    'label' => 'PPPoE Server',              'desc' => 'Pool, profile, and server on same bridge'],
                                ['icon' => 'security', 'label' => 'Firewall, NAT + DNS',        'desc' => 'Filter rules, masquerade, DNS resolver, identity'],
                            ];
                            foreach ($prov_steps as $i => $s): ?>
                            <div class="col-12 col-md-6">
                                <div class="prov-step" id="prov-step-<?= $i ?>">
                                    <span class="badge bg-secondary rounded-pill mt-1 flex-shrink-0" id="step-badge-<?= $i ?>"><?= $i+1 ?></span>
                                    <i class="material-icons text-muted mt-1 flex-shrink-0" style="font-size:1.1rem"><?= $s['icon'] ?></i>
                                    <div>
                                        <div class="fw-semibold small"><?= $s['label'] ?></div>
                                        <div class="text-muted" style="font-size:.73rem"><?= $s['desc'] ?></div>
                                        <div class="small mt-1 d-none" id="step-msg-<?= $i ?>"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <button class="btn btn-success btn-lg px-4" id="provisionBtn" onclick="runProvision()">
                            <i class="material-icons align-middle me-2">rocket_launch</i>
                            Provision Router
                        </button>

                        <div id="provisionStatus" class="mt-3" style="display:none"></div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     STEP 6 — Post-provisioning fixes  ★ NEW
                ════════════════════════════════════════════════════════ -->
                <div class="step-card step-danger">
                    <div class="step-header">
                        <span class="step-num">6</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#fd7e14">build</i>
                        Post-Provisioning Fixes
                        <span class="badge bg-warning text-dark ms-auto fw-normal" style="font-size:.7rem">Run after Step 5 succeeds</span>
                    </div>
                    <div class="step-body">
                        <p class="text-muted small mb-4">
                            Some RouterOS versions require these manual fixes after provisioning.
                            Apply only the fixes relevant to your situation — each is labelled with when it is needed.
                        </p>

                        <!-- Fix A -->
                        <div class="fix-cmd-block">
                            <span class="fix-tag fix-tag-a">
                                <i class="material-icons" style="font-size:.8rem">wifi_off</i>
                                Fix A — wlan1 shows XS flags (CAPsMAN)
                            </span>
                            <p class="small text-muted mb-2">
                                In <strong>Interfaces</strong>, if wlan1 has <code>X</code> (disabled) and <code>S</code> (slave/CAPsMAN) flags,
                                run these two commands to release it back to local control.
                            </p>
                            <div class="terminal-block position-relative">
                                <div class="terminal-topbar">
                                    <span class="dot dot-red"></span><span class="dot dot-yellow"></span><span class="dot dot-green"></span>
                                    <span class="terminal-label">Release wlan1 from CAPsMAN</span>
                                </div>
                                <div class="terminal-body"><?php
                                    $fixA = "/interface wireless cap set enabled=no\n/caps-man manager set enabled=no";
                                    foreach (explode("\n", $fixA) as $l) {
                                        echo '<span class="cmd-prompt">$ </span>' . htmlspecialchars($l) . "\n";
                                    }
                                ?></div>
                            </div>
                            <textarea id="fix-a" class="d-none" readonly>/interface wireless cap set enabled=no
/caps-man manager set enabled=no</textarea>
                            <button class="btn btn-sm btn-outline-warning mt-2" onclick="copyText('fix-a', this)">
                                <i class="material-icons align-middle" style="font-size:.85rem">content_copy</i> Copy Fix A
                            </button>
                        </div>

                        <!-- Fix B -->
                        <div class="fix-cmd-block">
                            <span class="fix-tag fix-tag-b">
                                <i class="material-icons" style="font-size:.8rem">wifi</i>
                                Fix B — WiFi not broadcasting / wlan1 disabled
                            </span>
                            <p class="small text-muted mb-2">
                                Factory default is often <code>mode=station</code> (client mode). This sets wlan1 to access-point mode
                                and enables it. <strong>Always run this</strong> even if Fix A was not needed.
                                The verify command should show <code>R</code> (running) with no <code>X</code> flag.
                            </p>
                            <div class="terminal-block position-relative">
                                <div class="terminal-topbar">
                                    <span class="dot dot-red"></span><span class="dot dot-yellow"></span><span class="dot dot-green"></span>
                                    <span class="terminal-label">Set wlan1 to AP mode</span>
                                </div>
                                <div class="terminal-body"><?php
                                    $fixB = '/interface wireless set wlan1 mode=ap-bridge disabled=no ssid="Inovatech WiFi"' . "\n\n" .
                                            '# Verify — should show R (running), mode=ap-bridge, no X flag' . "\n" .
                                            '/interface wireless print where name=wlan1';
                                    foreach (explode("\n", $fixB) as $l) {
                                        if (str_starts_with(trim($l), '#')) {
                                            echo '<span class="cmd-comment">' . htmlspecialchars($l) . '</span>' . "\n";
                                        } elseif (trim($l) === '') {
                                            echo "\n";
                                        } else {
                                            echo '<span class="cmd-prompt">$ </span>' . htmlspecialchars($l) . "\n";
                                        }
                                    }
                                ?></div>
                            </div>
                            <textarea id="fix-b" class="d-none" readonly>/interface wireless set wlan1 mode=ap-bridge disabled=no ssid="Inovatech WiFi"
/interface wireless print where name=wlan1</textarea>
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyText('fix-b', this)">
                                <i class="material-icons align-middle" style="font-size:.85rem">content_copy</i> Copy Fix B
                            </button>
                        </div>

                        <!-- Fix C -->
                                                <!-- Fix C -->
                        <div class="fix-cmd-block">
                            <span class="fix-tag fix-tag-c">
                                <i class="material-icons" style="font-size:.8rem">dns</i>
                                Fix C — IP → Hotspot shows no server or profile
                            </span>
                            <p class="small text-muted mb-2">
                                Run this complete fix if hotspot is not working after provisioning.
                                It creates both the profile and server in the correct order.
                            </p>
                            <div class="terminal-block position-relative">
                                <div class="terminal-topbar">
                                    <span class="dot dot-red"></span><span class="dot dot-yellow"></span><span class="dot dot-green"></span>
                                    <span class="terminal-label">Complete Hotspot Fix (Profile + Server)</span>
                                </div>
                                <div class="terminal-body" id="fix-c-display"><?php
                                    $fixCComplete = '# === COMPLETE HOTSPOT FIX ===
# Run this if hotspot is not working after provisioning

# 1. Create profile if missing
:local profileExists [/ip hotspot profile find name="inovatech-profile"]
:if ([:len $profileExists] = 0) do={
    /ip hotspot profile add name=inovatech-profile hotspot-address=192.168.50.1 login-by=http-chap,cookie
    /ip hotspot profile set [find name=inovatech-profile] dns-name=hotspot.inovatech.co.ke http-cookie-lifetime=1d comment="inovatech-hotspot"
    :put "✓ Profile created"
} else={
    :put "✓ Profile already exists"
}

# 2. Create hotspot server if missing
:local serverExists [/ip hotspot find name="hotspot1"]
:if ([:len $serverExists] = 0) do={
    /ip hotspot add name=hotspot1 interface=bridge address-pool=hotspot_pool profile=inovatech-profile disabled=no
    :put "✓ Hotspot server created"
} else={
    # Ensure correct settings
    /ip hotspot set [find name=hotspot1] address-pool=hotspot_pool profile=inovatech-profile disabled=no
    :put "✓ Hotspot server updated"
}

# 3. Verify
:put "=== VERIFICATION ==="
/ip hotspot print
/ip hotspot profile print where name=inovatech-profile';
                                    foreach (explode("\n", $fixCComplete) as $l) {
                                        if (str_starts_with(trim($l), '#')) {
                                            echo '<span class="cmd-comment">' . htmlspecialchars($l) . '</span>' . "\n";
                                        } elseif (trim($l) === '') {
                                            echo "\n";
                                        } elseif (str_starts_with(trim($l), ':')) {
                                            echo '<span class="cmd-prompt">$ </span><span style="color:#cba6f7">' . htmlspecialchars($l) . '</span>' . "\n";
                                        } else {
                                            echo '<span class="cmd-prompt">$ </span>' . htmlspecialchars($l) . "\n";
                                        }
                                    }
                                ?></div>
                            </div>
                            <textarea id="fix-c-complete" class="d-none" readonly><?php 
                                $fixCComplete = '/ip hotspot profile add name=inovatech-profile hotspot-address=192.168.50.1 login-by=http-chap,cookie
/ip hotspot profile set [find name=inovatech-profile] dns-name=hotspot.inovatech.co.ke http-cookie-lifetime=1d comment="inovatech-hotspot"
/ip hotspot add name=hotspot1 interface=bridge address-pool=hotspot_pool profile=inovatech-profile disabled=no
/ip hotspot print
/ip hotspot profile print where name=inovatech-profile';
                                echo htmlspecialchars($fixCComplete);
                            ?></textarea>
                            <button class="btn btn-sm btn-outline-success mt-2" onclick="copyText('fix-c-complete', this)">
                                <i class="material-icons align-middle" style="font-size:.85rem">content_copy</i> Copy Complete Fix
                            </button>
                        </div>

                        <div class="alert alert-secondary d-flex align-items-start gap-2 py-2 mb-0">
                            <i class="material-icons mt-1" style="font-size:1rem">lightbulb</i>
                            <div class="small">
                                <strong>Tip:</strong> After applying fixes, test by connecting a device to <strong>Inovatech WiFi</strong> — 
                                it should redirect to the captive portal on first HTTP request. 
                                Wired LAN clients (ether2–5) should receive a <code>192.168.50.x</code> IP and have internet.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════════
                     STEP 7 — Upload hotspot login page
                ════════════════════════════════════════════════════════ -->
                <div class="step-card step-upload" id="uploadCard" style="opacity:.5;pointer-events:none">
                    <div class="step-header">
                        <span class="step-num">7</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#0d6efd">upload</i>
                        Upload Hotspot Login Page
                    </div>
                    <div class="step-body">
                        <p class="mb-2 small text-muted">
                            Unique to this router — contains Router ID <code><?= $router['router_id'] ?></code>.
                            <strong class="text-danger">Do not use on any other router.</strong>
                        </p>
                        <button class="btn btn-primary" id="uploadBtn" onclick="uploadHotspot()">
                            <i class="material-icons align-middle me-1" style="font-size:1rem">upload</i>
                            Upload login.html to Router
                        </button>
                        <div id="uploadStatus" class="mt-3" style="display:none"></div>
                    </div>
                </div>

                <!-- ── Footer actions ─────────────────────────────────── -->
                <div class="d-flex gap-2 mt-3 mb-5">
                    <a href="../routers" class="btn btn-primary">
                        <i class="material-icons align-middle me-1" style="font-size:1rem">arrow_back</i>
                        Back to Routers
                    </a>
                    <a href="?router_id=<?= $router['router_id'] ?>" class="btn btn-outline-secondary">
                        <i class="material-icons align-middle me-1" style="font-size:1rem">refresh</i>
                        Reload
                    </a>
                </div>

            </div>
        </main>
        <?php require_once "../partials/footer.php"; ?>
    </div>
</div>

<?php require_once "../partials/scripts.php"; ?>
<script>
const ROUTER_ID = '<?= $router['router_id'] ?>';
const stepMap   = { hotspot: 0, pppoe: 1, firewall: 2 };

function runProvision() {
    const btn    = document.getElementById('provisionBtn');
    const status = document.getElementById('provisionStatus');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Provisioning...';
    status.style.display = 'none';

    Object.keys(stepMap).forEach((_, i) => {
        const card = document.getElementById('prov-step-' + i);
        if (card) card.className = 'prov-step';
        document.getElementById('step-badge-' + i).className = 'badge bg-secondary rounded-pill mt-1 flex-shrink-0';
        const msg = document.getElementById('step-msg-' + i);
        msg.className = 'small mt-1 d-none';
        msg.textContent = '';
    });

    const fd = new FormData();
    fd.append('router_id', ROUTER_ID);

    fetch('../install/provision_api.php', { method: 'POST', body: fd })
        .then(res => {
            const reader  = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer    = '';
            function pump() {
                reader.read().then(({ done, value }) => {
                    if (done) return;
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop();
                    lines.forEach(line => {
                        if (!line.startsWith('data: ')) return;
                        try { handleEvent(JSON.parse(line.slice(6))); } catch(e) {}
                    });
                    pump();
                });
            }
            pump();
        })
        .catch(err => {
            showStatus('danger', 'Connection failed: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="material-icons align-middle me-2">rocket_launch</i>Retry';
        });
}

function handleEvent(d) {
    const btn = document.getElementById('provisionBtn');

    if (d.step === 'connect') {
        if (d.status === 'error') {
            showStatus('danger', '❌ ' + d.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="material-icons align-middle me-2">rocket_launch</i>Retry';
        }
        return;
    }
    if (d.step === 'done') {
        showStatus('success', d.message);
        btn.innerHTML = '<i class="material-icons align-middle me-2">check_circle</i>Provisioned';
        const upload = document.getElementById('uploadCard');
        upload.style.opacity = '1';
        upload.style.pointerEvents = 'auto';
        return;
    }
    if (d.step === 'error') {
        showStatus('danger', '❌ ' + d.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="material-icons align-middle me-2">rocket_launch</i>Retry';
        return;
    }

    const idx = stepMap[d.step];
    if (idx === undefined) return;

    const badge = document.getElementById('step-badge-' + idx);
    const msg   = document.getElementById('step-msg-'   + idx);
    const card  = document.getElementById('prov-step-'  + idx);
    msg.classList.remove('d-none');
    msg.textContent = d.message;

    if (d.status === 'running') {
        badge.className = 'badge bg-warning text-dark rounded-pill mt-1 flex-shrink-0';
        badge.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.6rem;height:.6rem"></span>';
        if (card) card.className = 'prov-step running';
    } else if (d.status === 'ok') {
        badge.className = 'badge bg-success rounded-pill mt-1 flex-shrink-0';
        badge.innerHTML = '<i class="material-icons" style="font-size:.7rem">check</i>';
        msg.className   = 'small mt-1 text-success';
        if (card) card.className = 'prov-step ok';
    } else if (d.status === 'error') {
        badge.className = 'badge bg-danger rounded-pill mt-1 flex-shrink-0';
        badge.innerHTML = '<i class="material-icons" style="font-size:.7rem">close</i>';
        msg.className   = 'small mt-1 text-danger';
        if (card) card.className = 'prov-step error';
    }
}

function showStatus(type, message) {
    const el = document.getElementById('provisionStatus');
    el.className = `alert alert-${type} d-flex align-items-center gap-2 py-2`;
    el.innerHTML = `<i class="material-icons" style="font-size:1rem">${type === 'success' ? 'check_circle' : 'error'}</i><div>${message}</div>`;
    el.style.display = 'flex';
}

function uploadHotspot() {
    const btn    = document.getElementById('uploadBtn');
    const status = document.getElementById('uploadStatus');
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons align-middle me-1" style="font-size:1rem">hourglass_top</i> Uploading...';
    const fd = new FormData();
    fd.append('router_id', ROUTER_ID);
    fetch('../install/upload_hotspot.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.success) {
                btn.innerHTML = '<i class="material-icons align-middle me-1" style="font-size:1rem">check_circle</i> Uploaded';
                btn.classList.replace('btn-primary', 'btn-outline-success');
                status.className = 'alert alert-success d-flex gap-2 py-2 mt-3';
                status.innerHTML = '<i class="material-icons" style="font-size:1rem">check_circle</i><div>' + data.message + '</div>';
            } else {
                btn.innerHTML = '<i class="material-icons align-middle me-1" style="font-size:1rem">upload</i> Retry Upload';
                status.className = 'alert alert-danger d-flex gap-2 py-2 mt-3';
                status.innerHTML = '<i class="material-icons" style="font-size:1rem">error</i><div><strong>Failed:</strong> ' + data.message + '</div>';
            }
            status.style.display = 'flex';
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="material-icons align-middle me-1" style="font-size:1rem">upload</i> Retry Upload';
            status.className = 'alert alert-danger d-flex gap-2 py-2 mt-3';
            status.innerHTML = '<i class="material-icons" style="font-size:1rem">error</i><div>Request failed.</div>';
            status.style.display = 'flex';
        });
}

function copyText(id, btn) {
    const el   = document.getElementById(id);
    const text = el.value || el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const icon = btn.querySelector('i');
        const orig = icon.textContent;
        icon.textContent = 'check';
        btn.classList.add('btn-success');
        setTimeout(() => {
            icon.textContent = orig;
            btn.classList.remove('btn-success');
        }, 2000);
    });
}
</script>
</body>
</html>