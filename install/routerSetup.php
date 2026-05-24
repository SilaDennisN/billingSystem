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

// ── STEP 3 — Full base config (terminal, paste-and-run) ───────────────────
//
// Topology (matches live export):
//   ether1-ISP        → WAN (DHCP client, NAT masquerade)
//   ether2-LAN        → disabled (enable + add to bridge-LAN if needed)
//   ether3-AP         → bridge-LAN
//   ether4-PPPoE      → bridge-PPPoE  (PPPoE subscribers, pool 10.10.10.x)
//   ether5-HS         → bridge-HS     (hotspot wired)
//   wlan1             → bridge-HS     (hotspot WiFi)
//
// IPs:
//   bridge-LAN        → 192.168.88.1/24
//   bridge-HS         → 192.168.50.1/24
//   bridge-PPPoE      → 10.10.10.1/24
//
// Profiles, users, hotspot packages are set manually per router.

$base_commands = implode("\n", [
    '# ════════════════════════════════════════════════════════════════',
    '# Inovatech Base Config — paste entire block, press Enter',
    '# Profiles / users / hotspot packages are set manually afterwards',
    '# ════════════════════════════════════════════════════════════════',
    '',
    '# ── 1. Rename interfaces ─────────────────────────────────────────',
    '/interface ethernet set [find default-name=ether1] name=ether1-ISP',
    '/interface ethernet set [find default-name=ether2] name=ether2-LAN disabled=yes',
    '/interface ethernet set [find default-name=ether3] name=ether3-AP',
    '/interface ethernet set [find default-name=ether4] name=ether4-PPPoE',
    '/interface ethernet set [find default-name=ether5] name=ether5-HS',
    '',
    '# ── 2. Create three bridges ──────────────────────────────────────',
    '/interface bridge add name=bridge-LAN',
    '/interface bridge add name=bridge-PPPoE',
    '/interface bridge add name=bridge-HS',
    '',
    '# ── 3. Assign ports to bridges ───────────────────────────────────',
    '/interface bridge port add interface=ether3-AP    bridge=bridge-LAN',
    '/interface bridge port add interface=ether4-PPPoE bridge=bridge-PPPoE',
    '/interface bridge port add interface=ether5-HS    bridge=bridge-HS',
    '/interface bridge port add interface=wlan1        bridge=bridge-HS',
    '',
    '# ── 4. IP addresses ──────────────────────────────────────────────',
    '/ip address add address=192.168.88.1/24 interface=bridge-LAN    network=192.168.88.0',
    '/ip address add address=192.168.50.1/24 interface=bridge-HS     network=192.168.50.0',
    '/ip address add address=10.10.10.1/24   interface=bridge-PPPoE  network=10.10.10.0',
    '',
    '# ── 5. DHCP client on WAN ────────────────────────────────────────',
    '/ip dhcp-client add interface=ether1-ISP disabled=no add-default-route=yes use-peer-dns=yes comment="inovatech-wan"',
    '',
    '# ── 6. IP pools ──────────────────────────────────────────────────',
    '/ip pool add name=pool-LAN   ranges=192.168.88.2-192.168.88.254',
    '/ip pool add name=pool-PPPoE ranges=10.10.10.3-10.10.10.254',
    '/ip pool add name=pool-HS    ranges=192.168.50.3-192.168.50.254',
    '',
    '# ── 7. DHCP servers ──────────────────────────────────────────────',
    '/ip dhcp-server add name=serverLAN interface=bridge-LAN  address-pool=pool-LAN lease-time=1d',
    '/ip dhcp-server add name=server-hs  interface=bridge-HS   address-pool=pool-HS  lease-time=1d',
    '/ip dhcp-server network add address=192.168.88.0/24 gateway=192.168.88.1',
    '/ip dhcp-server network add address=192.168.50.0/24 gateway=192.168.50.1',
    '',
    '# ── 8. WiFi (AP-bridge mode, Kenya) ──────────────────────────────',
    '/interface wireless set [find default-name=wlan1] mode=ap-bridge band=2ghz-b/g/n country=kenya disabled=no ssid="FreeBest" wireless-protocol=802.11 wps-mode=disabled',
    '',
    '# ── 9. DNS ───────────────────────────────────────────────────────',
    '/ip dns set servers=8.8.8.8,8.8.4.4 allow-remote-requests=yes',
    '',
    '# ── 10. NAT masquerade ────────────────────────────────────────────',
    '/ip firewall nat add chain=srcnat action=masquerade out-interface=ether1-ISP comment="inovatech-masquerade"',
    '/ip firewall nat add chain=srcnat action=masquerade src-address=192.168.50.0/24 comment="masquerade hotspot network"',
    '',
    '# ── 11. PPPoE server ─────────────────────────────────────────────',
    '/interface pppoe-server server add interface=bridge-PPPoE service-name=servicePPPoE one-session-per-host=yes disabled=no',
    '',
    '# ── 12. MSS clamp for PPPoE ──────────────────────────────────────',
    '/ip firewall mangle add chain=forward protocol=tcp tcp-flags=syn action=change-mss new-mss=1452 out-interface=bridge-PPPoE',
    '',
    '# ── 13. System clock ─────────────────────────────────────────────',
    '/system clock set time-zone-name=Africa/Nairobi',
]);

// ── STEP 5 — WireGuard (run AFTER internet confirmed) ─────────────────────

?>
<?php require_once "../partials/head.php"; ?>
<style>
/* ── Provisioning page ───────────────────────────────────────────────── */
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

/* colour themes */
.step-neutral  .step-header { background: #f8f9fa; color: #212529; }
.step-neutral  .step-num    { background: #6c757d; color: #fff; }
.step-blue     .step-header { background: #e7f1ff; color: #0a58ca; }
.step-blue     .step-num    { background: #0d6efd; color: #fff; }
.step-warning  .step-header { background: #fff8e1; color: #664d03; border-bottom: 2px solid #ffc107; }
.step-warning  .step-num    { background: #ffc107; color: #212529; }
.step-vpn      .step-header { background: #e8f5e9; color: #146c43; border-bottom: 2px solid #198754; }
.step-vpn      .step-num    { background: #198754; color: #fff; }
.step-success  .step-header { background: #d1e7dd; color: #0a3622; border-bottom: 2px solid #198754; }
.step-success  .step-num    { background: #198754; color: #fff; }
.step-upload   .step-header { background: #e7f1ff; color: #0a58ca; }
.step-upload   .step-num    { background: #0d6efd; color: #fff; }

/* terminal */
.terminal-block {
    background: #1e1e2e;
    border-radius: 7px;
    overflow: hidden;
    font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
}
.terminal-topbar {
    background: #2a2a3d;
    padding: .35rem .75rem;
    display: flex; align-items: center; gap: .4rem;
}
.terminal-topbar .dot { width: 10px; height: 10px; border-radius: 50%; }
.dot-red    { background: #ff5f57; }
.dot-yellow { background: #febc2e; }
.dot-green  { background: #28c840; }
.terminal-label {
    font-size: .7rem; color: #888;
    margin-left: auto; letter-spacing: .04em; text-transform: uppercase;
}
.terminal-body {
    padding: .85rem 1rem;
    color: #cdd6f4; font-size: .78rem;
    line-height: 1.75; white-space: pre-wrap; word-break: break-all;
    max-height: 420px; overflow-y: auto;
}
.terminal-body .cmd-comment { color: #6c7086; }
.terminal-body .cmd-prompt  { color: #a6e3a1; }
.copy-overlay-btn { position: absolute; top: .5rem; right: .5rem; z-index: 2; }

/* topology badges */
.topo-badge {
    display: flex; align-items: center; gap: .5rem;
    padding: .5rem .85rem; border-radius: 8px;
    background: #f8f9fa; border: 1px solid #dee2e6; font-size: .78rem;
}
.topo-badge .material-icons { font-size: 1.1rem; }

/* provision steps */
.prov-step {
    display: flex; align-items: flex-start; gap: .6rem;
    padding: .65rem .85rem; border-radius: 8px;
    border: 1px solid #dee2e6; background: #f8f9fa; transition: background .2s;
}
.prov-step.running { background: #fff8e1; border-color: #ffc107; }
.prov-step.ok      { background: #d1e7dd; border-color: #198754; }
.prov-step.error   { background: #f8d7da; border-color: #dc3545; }
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

                <!-- ── Topology summary ──────────────────────────────── -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body py-3">
                        <p class="fw-semibold mb-2 small text-muted text-uppercase" style="letter-spacing:.06em">
                            <i class="material-icons align-middle me-1" style="font-size:.9rem">account_tree</i> Network Topology
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="topo-badge">
                                <i class="material-icons text-primary">router</i>
                                <div><div class="fw-semibold">ether1-ISP</div><div class="text-muted">WAN — DHCP + NAT</div></div>
                            </div>
                            <div class="topo-badge">
                                <i class="material-icons text-secondary">cable</i>
                                <div><div class="fw-semibold">ether3-AP → bridge-LAN</div><div class="text-muted">192.168.88.1/24</div></div>
                            </div>
                            <div class="topo-badge">
                                <i class="material-icons text-warning">wifi</i>
                                <div><div class="fw-semibold">wlan1 + ether5-HS → bridge-HS</div><div class="text-muted">192.168.50.1/24 — hotspot</div></div>
                            </div>
                            <div class="topo-badge">
                                <i class="material-icons text-info">cable</i>
                                <div><div class="fw-semibold">ether4-PPPoE → bridge-PPPoE</div><div class="text-muted">10.10.10.1/24 — PPPoE dial-in</div></div>
                            </div>
                            <div class="topo-badge">
                                <i class="material-icons text-success">vpn_lock</i>
                                <div><div class="fw-semibold">wg-billing</div><div class="text-muted">WireGuard → billing VPS</div></div>
                            </div>
                        </div>
                        <p class="text-muted mt-2 mb-0" style="font-size:.75rem">
                            <i class="material-icons align-middle" style="font-size:.85rem">info</i>
                            Hotspot profiles, PPPoE profiles, users, and packages are configured manually per router after provisioning.
                        </p>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════
                     STEP 1 — Connect to internet
                ════════════════════════════════════════════════════ -->
                <div class="step-card step-neutral">
                    <div class="step-header">
                        <span class="step-num">1</span>
                        <i class="material-icons text-muted" style="font-size:1.1rem">power</i>
                        Connect <strong class="ms-1">ether1</strong> to Internet &amp; Power On
                    </div>
                    <div class="step-body">
                        <p class="mb-0 text-muted small">
                            Plug <strong class="text-dark">ether1-ISP</strong> into your ONT, modem, or uplink.
                            Power on and wait for the status LED to stabilise before proceeding.
                        </p>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════
                     STEP 2 — WinBox
                ════════════════════════════════════════════════════ -->
                <div class="step-card step-blue">
                    <div class="step-header">
                        <span class="step-num">2</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#0d6efd">computer</i>
                        Open WinBox — Connect to <code style="font-size:.85em">192.168.88.1</code>
                    </div>
                    <div class="step-body">
                        <p class="small text-muted mb-3">Username <code>admin</code>, no password (fresh reset). Open <strong>New Terminal</strong> once inside.</p>
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

                <!-- ════════════════════════════════════════════════════
                     STEP 3 — Base config
                ════════════════════════════════════════════════════ -->
                <div class="step-card step-warning">
                    <div class="step-header">
                        <span class="step-num">3</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#997404">terminal</i>
                        Paste Base Config in WinBox Terminal
                    </div>
                    <div class="step-body">
                        <p class="text-muted small mb-2">
                            Sets up all interfaces, bridges, IPs, DHCP, WiFi, DNS, NAT, PPPoE server, and clock.
                            <strong>Select All → Copy → Paste into terminal → Enter.</strong>
                        </p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-secondary fw-normal">3 bridges</span>
                            <span class="badge bg-primary fw-normal">IPs + DHCP</span>
                            <span class="badge bg-success fw-normal">WiFi AP-bridge</span>
                            <span class="badge bg-primary fw-normal">WAN + NAT</span>
                            <span class="badge bg-info text-dark fw-normal">PPPoE server</span>
                            <span class="badge bg-warning text-dark fw-normal">DNS + Clock</span>
                        </div>

                        <div class="position-relative">
                            <div class="terminal-block">
                                <div class="terminal-topbar">
                                    <span class="dot dot-red"></span>
                                    <span class="dot dot-yellow"></span>
                                    <span class="dot dot-green"></span>
                                    <span class="terminal-label">WinBox Terminal — Base Config</span>
                                </div>
                                <div class="terminal-body"><?php
                                    foreach (explode("\n", $base_commands) as $line) {
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
                            <textarea id="base-commands" class="d-none" readonly><?= htmlspecialchars($base_commands) ?></textarea>
                            <button class="btn btn-warning btn-sm copy-overlay-btn" onclick="copyText('base-commands', this)">
                                <i class="material-icons align-middle" style="font-size:.9rem">content_copy</i> Copy All
                            </button>
                        </div>

                        <div class="alert alert-warning d-flex align-items-start gap-2 py-2 mt-3 mb-0">
                            <i class="material-icons mt-1" style="font-size:1rem">warning</i>
                            <div class="small">
                                After pasting, your WinBox session on <code>192.168.88.1</code> may disconnect.
                                Reconnect on <code>192.168.88.1</code> (via ether3-AP) or <code>192.168.50.1</code> (via ether5-HS / WiFi) before continuing.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════
                     STEP 4 — Verify internet
                ════════════════════════════════════════════════════ -->
                <div class="step-card step-neutral">
                    <div class="step-header">
                        <span class="step-num">4</span>
                        <i class="material-icons text-muted" style="font-size:1.1rem">network_check</i>
                        Verify Internet on the Router
                    </div>
                    <div class="step-body">
                        <p class="small text-muted mb-2">
                            In WinBox terminal, confirm the router has an internet connection before running the VPN script.
                        </p>
                        <div class="input-group" style="max-width:480px">
                            <span class="input-group-text bg-dark text-white border-dark font-monospace small">$</span>
                            <input type="text" class="form-control font-monospace bg-dark text-white border-dark" style="font-size:.82rem" value="/ping 8.8.8.8 count=3" readonly id="ping-cmd">
                            <button class="btn btn-outline-secondary" onclick="copyText('ping-cmd', this)" title="Copy">
                                <i class="material-icons" style="font-size:1rem">content_copy</i>
                            </button>
                        </div>
                        <p class="text-muted small mt-2 mb-0">You should see replies before proceeding. If no replies, check ether1-ISP cabling and the uplink.</p>
                    </div>
                </div>

                <!-- ════════════════════════════════════════════════════
                     STEP 5 — WireGuard VPN
                ════════════════════════════════════════════════════ -->
                <div class="step-card step-vpn">
                    <div class="step-header">
                        <span class="step-num">5</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#198754">vpn_lock</i>
                        Run 2 Commands — Sets Up WireGuard VPN
                    </div>
                    <div class="step-body">
                        <p class="text-muted small mb-3">
                            Now that the router has internet, fetch and run the VPN bootstrap script.
                            <strong>This only touches WireGuard</strong> — nothing else is changed.
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
                                    Watch <strong>WinBox → Log</strong> for <strong>"WireGuard DONE — VPN tunnel is up"</strong>, then continue to Step 6.
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

                <!-- ════════════════════════════════════════════════════
                     STEP 6 — API Provision
                ════════════════════════════════════════════════════ -->
                <div class="step-card step-success">
                    <div class="step-header">
                        <span class="step-num">6</span>
                        <i class="material-icons" style="font-size:1.1rem;color:#198754">rocket_launch</i>
                        Provision — Hotspot Server &amp; Firewall
                    </div>
                    <div class="step-body">
                        <p class="text-muted small mb-3">
                            Once the VPN is up, click below. The billing server applies remaining config
                            (hotspot server, walled garden, firewall rules) over the VPN tunnel.
                        </p>

                        <div class="row g-2 mb-4">
                            <?php
                            $prov_steps = [
                                ['icon' => 'wifi',     'label' => 'Hotspot Server',        'desc' => 'Hotspot server on bridge-HS, walled garden, hotspot profile'],
                                ['icon' => 'security', 'label' => 'Firewall + NAT',         'desc' => 'Filter rules, VPN accept, masquerade'],
                                ['icon' => 'dns',      'label' => 'DNS + Identity',          'desc' => 'DNS resolver, system identity'],
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

                <!-- ════════════════════════════════════════════════════
                     STEP 7 — Upload hotspot login page
                ════════════════════════════════════════════════════ -->
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

                <!-- ── Footer actions ─────────────────────────────── -->
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
const stepMap   = { hotspot: 0, firewall: 1, dns: 2 };

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