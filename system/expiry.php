<?php
require_once "../partials/head.php";
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/expiry_engine.php";
require __DIR__ . '/../subscription/subscription_gate.php';

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
check_subscription_gate($pdo, $user_id);

$stmt = $pdo->prepare("SELECT router_id FROM user_router_access WHERE user_id = ?");
$stmt->execute([$user_id]);
$routerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($routerIds)) $routerIds = [0];

$placeholders = implode(',', array_fill(0, count($routerIds), '?'));

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM hotspot_users
    WHERE status = 'active' AND expires_at <= NOW()
    AND router_id IN ($placeholders)
");
$countStmt->execute($routerIds);
$pendingCount = (int)$countStmt->fetchColumn();

/* Manual run via POST */
$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $results = run_expiry_engine($pdo, $routerIds);
    /* Refresh pending count after run */
    $countStmt->execute($routerIds);
    $pendingCount = (int)$countStmt->fetchColumn();
}
?>

<body class="nav-fixed bg-light">

    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <header style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 60%,#0f3460 100%);border-bottom:3px solid #e94560;">
                    <div class="container-xl px-1 py-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:46px;height:46px;background:rgba(233,69,96,0.15);border:1px solid rgba(233,69,96,0.4);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fa fa-clock" style="color:#e94560;font-size:1.2rem;"></i>
                                </div>
                                <div>
                                    <h1 class="text-white mb-0" style="font-size:1.4rem;font-weight:700;">Expiry Engine</h1>
                                    <p class="mb-0" style="color:rgba(255,255,255,0.5);font-size:0.78rem;">Auto-runs every 60s via WebSocket server &middot; Manual run available below</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span id="wsStatusBadge" style="background:rgba(250,204,21,0.15);border:1px solid rgba(250,204,21,0.3);color:#facc15;font-size:0.72rem;font-weight:600;padding:4px 10px;border-radius:20px;">
                                    <i class="fa fa-circle me-1"></i>Connecting...
                                </span>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 py-4">

                    <!-- Manual run results -->
                    <?php if ($results !== null): ?>
                        <?php if (!empty($results['expired'])): ?>
                        <div class="alert d-flex align-items-start gap-3 mb-4" style="background:#0d2b1f;border:1px solid #1a7a4a;border-radius:10px;color:#4ade80;">
                            <i class="fa fa-circle-check mt-1"></i>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;">Engine ran successfully</div>
                                <div style="font-size:0.82rem;color:rgba(74,222,128,0.75);margin-top:2px;">
                                    <?= count($results['expired']) ?> user(s) expired:
                                    <span style="font-family:monospace;"><?= implode(', ', array_map('htmlspecialchars', $results['expired'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($results['total'] === 0): ?>
                        <div class="alert d-flex align-items-start gap-3 mb-4" style="background:#1a1a0d;border:1px solid #6b6b1a;border-radius:10px;color:#facc15;">
                            <i class="fa fa-circle-info mt-1"></i>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;">Nothing to expire</div>
                                <div style="font-size:0.82rem;color:rgba(250,204,21,0.7);margin-top:2px;">No active users with a passed expiry were found.</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($results['failed'])): ?>
                        <div class="alert d-flex align-items-start gap-3 mb-4" style="background:#2b0d0d;border:1px solid #7a1a1a;border-radius:10px;color:#f87171;">
                            <i class="fa fa-triangle-exclamation mt-1"></i>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;"><?= count($results['failed']) ?> user(s) failed</div>
                                <?php foreach ($results['failed'] as $f): ?>
                                <div style="font-size:0.82rem;color:rgba(248,113,113,0.75);margin-top:2px;">
                                    <span style="font-family:monospace;"><?= htmlspecialchars($f['username']) ?></span> — <?= htmlspecialchars($f['error']) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Live notification area — populated by WS -->
                    <div id="liveAlert" style="display:none;" class="alert d-flex align-items-start gap-3 mb-4" style="background:#0d1a2b;border:1px solid #1a4a7a;border-radius:10px;color:#60a5fa;"></div>

                    <div class="row g-4">

                        <!-- Pending count -->
                        <div class="col-12 col-md-4">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Pending Expiry</span>
                                        <div style="width:34px;height:34px;border-radius:8px;background:#fef2f2;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa fa-user-clock" style="color:#ef4444;font-size:0.85rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2.4rem;font-weight:800;color:#1e293b;line-height:1;" id="pendingCount"><?= $pendingCount ?></div>
                                    <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">expired users awaiting removal</div>
                                </div>
                            </div>
                        </div>

                        <!-- Router scope -->
                        <div class="col-12 col-md-4">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Your Routers</span>
                                        <div style="width:34px;height:34px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa fa-network-wired" style="color:#3b82f6;font-size:0.85rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2.4rem;font-weight:800;color:#1e293b;line-height:1;"><?= count($routerIds) === 1 && $routerIds[0] === 0 ? 0 : count($routerIds) ?></div>
                                    <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">routers in scope for manual run</div>
                                </div>
                            </div>
                        </div>

                        <!-- Next auto-run countdown -->
                        <div class="col-12 col-md-4">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Next Auto-Run</span>
                                        <div style="width:34px;height:34px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa fa-rotate" style="color:#22c55e;font-size:0.85rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2.4rem;font-weight:800;color:#1e293b;line-height:1;" id="countdown">60</div>
                                    <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">seconds (runs every 60s automatically)</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action card -->
                        <div class="col-12">
                            <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="row align-items-center g-4">
                                        <div class="col-12 col-md-8">
                                            <h5 style="font-weight:700;color:#1e293b;margin-bottom:6px;font-size:1rem;">Manual Run</h5>
                                            <p style="color:#64748b;font-size:0.85rem;margin-bottom:0;line-height:1.6;">
                                                The engine runs automatically every 60 seconds via the WebSocket server across
                                                <strong>all active routers</strong>. Use manual run only if you need to force
                                                an immediate expiry on <strong>your <?= count($routerIds) === 1 && $routerIds[0] === 0 ? 'assigned' : count($routerIds) ?> router(s)</strong>.
                                            </p>
                                            <div class="mt-3 d-inline-flex align-items-center gap-2" style="background:<?= $pendingCount === 0 ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= $pendingCount === 0 ? '#bbf7d0' : '#fecaca' ?>;border-radius:7px;padding:6px 12px;">
                                                <i class="fa <?= $pendingCount === 0 ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" style="color:<?= $pendingCount === 0 ? '#22c55e' : '#ef4444' ?>;font-size:0.8rem;"></i>
                                                <span style="font-size:0.8rem;color:<?= $pendingCount === 0 ? '#166534' : '#991b1b' ?>;font-weight:500;" id="pendingLabel">
                                                    <?= $pendingCount === 0 ? 'All users are up to date.' : "{$pendingCount} user(s) will be removed from your routers." ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4 text-md-end">
                                            <form method="POST">
                                                <button type="submit"
                                                    class="btn px-4 py-2"
                                                    style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;border-radius:9px;font-weight:600;font-size:0.88rem;box-shadow:0 4px 12px rgba(220,38,38,0.3);transition:all .2s;"
                                                    <?= $pendingCount === 0 ? 'disabled' : '' ?>
                                                    onclick="return confirm('Disconnect and remove <?= $pendingCount ?> expired user(s)?');">
                                                    <i class="fa fa-bolt me-2"></i>Run Now
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>

    <!-- Toast container -->
    <div style="position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:8px;" id="toastWrap"></div>

    <style>
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .btn:not(:disabled):hover { filter:brightness(1.1); transform:translateY(-1px); }
    </style>

    <script>
    (function () {
        /* ── Countdown ticker ── */
        let countdown = 60;
        const countEl = document.getElementById('countdown');
        setInterval(() => {
            countdown = countdown <= 1 ? 60 : countdown - 1;
            if (countEl) countEl.textContent = countdown;
        }, 1000);

        /* ── Toast ── */
        function toast(msg, type = 'success') {
            const wrap  = document.getElementById('toastWrap');
            const color = type === 'success' ? '#22c55e' : '#ef4444';
            const t     = document.createElement('div');
            t.style.cssText = `background:#1e293b;color:#fff;padding:10px 18px;border-radius:10px;font-size:0.82rem;font-weight:500;border-left:3px solid ${color};opacity:0;transform:translateY(8px);transition:all .25s;min-width:260px;`;
            t.textContent = msg;
            wrap.appendChild(t);
            requestAnimationFrame(() => { t.style.opacity = '1'; t.style.transform = 'translateY(0)'; });
            setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 5000);
        }

        /* ── WS status badge ── */
        function setWsStatus(state) {
            const badge = document.getElementById('wsStatusBadge');
            const map = {
                connecting: ['rgba(250,204,21,0.15)', 'rgba(250,204,21,0.3)', '#facc15', 'Connecting...'],
                online:     ['rgba(34,197,94,0.15)',  'rgba(34,197,94,0.3)',  '#4ade80', 'Auto-run Active'],
                offline:    ['rgba(248,113,113,0.15)','rgba(248,113,113,0.3)','#f87171', 'Disconnected'],
            };
            const [bg, border, color, label] = map[state] || map.connecting;
            badge.style.cssText = `background:${bg};border:1px solid ${border};color:${color};font-size:0.72rem;font-weight:600;padding:4px 10px;border-radius:20px;`;
            badge.innerHTML = `<i class="fa fa-circle me-1"></i>${label}`;
        }

        /* ── Handle expiry_result push ── */
        function handleExpiryResult(data) {
            const expired = data.expired || [];
            const failed  = data.failed  || [];

            /* Reset countdown */
            countdown = 60;
            if (countEl) countEl.textContent = '60';

            if (expired.length > 0) {
                toast(`Auto-expiry: ${expired.length} user(s) removed at ${data.time}`, 'success');

                /* Update pending count */
                const el = document.getElementById('pendingCount');
                if (el) {
                    const current = parseInt(el.textContent) || 0;
                    el.textContent = Math.max(0, current - expired.length);
                }
            }

            if (failed.length > 0) {
                toast(`Expiry: ${failed.length} user(s) failed — check logs`, 'error');
            }
        }

        /* ── WebSocket ── */
        let ws = null;

        function connectWS() {
            setWsStatus('connecting');
            ws = new WebSocket('wss://billing.inovatech.co.ke/ws');

            ws.onopen = () => {
                setWsStatus('online');
                /* No subscription needed — broadcastAll sends to everyone */
            };

            ws.onmessage = (event) => {
                try {
                    const d = JSON.parse(event.data);
                    if (d.type === 'expiry_result') {
                        handleExpiryResult(d);
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

        connectWS();
    })();
    </script>

</body>
</html>
