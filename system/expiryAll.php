<?php
require_once "../partials/head.php";
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/expiry_engine.php";

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

/* ── Count all pending expired users across every router ── */
$countStmt = $pdo->query("
    SELECT COUNT(*) FROM hotspot_users
    WHERE status = 'active'
    AND expires_at <= NOW()
");
$pendingCount = (int) $countStmt->fetchColumn();

/* ── Total active users for context ── */
$totalStmt = $pdo->query("SELECT COUNT(*) FROM hotspot_users WHERE status = 'active'");
$totalActive = (int) $totalStmt->fetchColumn();

/* ── Run engine on POST (no router filter = all routers) ── */
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $results = run_expiry_engine($pdo);
}
?>

<body class="nav-fixed bg-light">

    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Page Header -->
                <header style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%); border-bottom: 3px solid #e94560;">
                    <div class="container-xl px-5 py-4">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:46px;height:46px;background:rgba(233,69,96,0.15);border:1px solid rgba(233,69,96,0.4);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-clock" style="color:#e94560;font-size:1.2rem;"></i>
                            </div>
                            <div>
                                <h1 class="text-white mb-0" style="font-size:1.4rem;font-weight:700;letter-spacing:-0.3px;">Expiry Engine</h1>
                                <p class="mb-0" style="color:rgba(255,255,255,0.5);font-size:0.78rem;">Disconnect &amp; disable expired hotspot users across <strong style="color:rgba(255,255,255,0.75);">all routers</strong></p>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 py-4">

                    <!-- Result alerts -->
                    <?php if ($results !== null): ?>
                        <?php if ($results['total'] === 0): ?>
                        <div class="alert d-flex align-items-start gap-3 mb-4" style="background:#1a1a0d;border:1px solid #6b6b1a;border-radius:10px;color:#facc15;">
                            <i class="fas fa-circle-info mt-1" style="font-size:1rem;flex-shrink:0;"></i>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;">Nothing to expire</div>
                                <div style="font-size:0.82rem;color:rgba(250,204,21,0.7);margin-top:2px;">No active users with a passed expiry were found across any router.</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($results['expired'])): ?>
                        <div class="alert d-flex align-items-start gap-3 mb-4" style="background:#0d2b1f;border:1px solid #1a7a4a;border-radius:10px;color:#4ade80;">
                            <i class="fas fa-circle-check mt-1" style="font-size:1rem;flex-shrink:0;"></i>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;">Engine ran successfully — <?= count($results['expired']) ?> user(s) expired</div>
                                <div style="font-size:0.82rem;color:rgba(74,222,128,0.75);margin-top:4px;font-family:monospace;word-break:break-all;">
                                    <?= implode(', ', array_map('htmlspecialchars', $results['expired'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($results['failed'])): ?>
                        <div class="alert d-flex align-items-start gap-3 mb-4" style="background:#2b0d0d;border:1px solid #7a1a1a;border-radius:10px;color:#f87171;">
                            <i class="fas fa-triangle-exclamation mt-1" style="font-size:1rem;flex-shrink:0;"></i>
                            <div>
                                <div style="font-weight:600;font-size:0.9rem;"><?= count($results['failed']) ?> user(s) failed to expire</div>
                                <?php foreach ($results['failed'] as $f): ?>
                                <div style="font-size:0.82rem;color:rgba(248,113,113,0.75);margin-top:3px;">
                                    <span style="font-family:monospace;"><?= htmlspecialchars($f['username']) ?></span> — <?= htmlspecialchars($f['error']) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Stat cards -->
                    <div class="row g-4 mb-4">

                        <div class="col-12 col-md-4">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Pending Expiry</span>
                                        <div style="width:34px;height:34px;border-radius:8px;background:#fef2f2;display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-user-clock" style="color:#ef4444;font-size:0.85rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2.4rem;font-weight:800;color:#1e293b;line-height:1;"><?= $pendingCount ?></div>
                                    <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">expired users awaiting removal</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Total Active Users</span>
                                        <div style="width:34px;height:34px;border-radius:8px;background:#eff6ff;display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-users" style="color:#3b82f6;font-size:0.85rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:2.4rem;font-weight:800;color:#1e293b;line-height:1;"><?= $totalActive ?></div>
                                    <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;">across all routers</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="card border-0 h-100" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.8px;color:#94a3b8;">Current Time</span>
                                        <div style="width:34px;height:34px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-calendar-check" style="color:#22c55e;font-size:0.85rem;"></i>
                                        </div>
                                    </div>
                                    <div style="font-size:1rem;font-weight:700;color:#1e293b;line-height:1.4;"><?= date('d M Y') ?></div>
                                    <div style="font-size:0.78rem;color:#94a3b8;margin-top:4px;"><?= date('H:i:s') ?> (Africa/Nairobi)</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Action card -->
                    <div class="card border-0" style="border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.07);">
                        <div class="card-body p-4">
                            <div class="row align-items-center g-4">
                                <div class="col-12 col-md-8">
                                    <h5 style="font-weight:700;color:#1e293b;margin-bottom:6px;font-size:1rem;">Run Expiry Engine — All Routers</h5>
                                    <p style="color:#64748b;font-size:0.85rem;margin-bottom:0;line-height:1.6;">
                                        This will immediately <strong>disconnect active sessions</strong>, remove cookies, remove hotspot hosts,
                                        and <strong>delete expired users</strong> from <strong>every router</strong> in the system.
                                    </p>

                                    <?php if ($pendingCount === 0): ?>
                                    <div class="mt-3 d-inline-flex align-items-center gap-2" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:6px 12px;">
                                        <i class="fas fa-circle-check" style="color:#22c55e;font-size:0.8rem;"></i>
                                        <span style="font-size:0.8rem;color:#166534;font-weight:500;">All users are up to date — nothing to expire right now.</span>
                                    </div>
                                    <?php else: ?>
                                    <div class="mt-3 d-inline-flex align-items-center gap-2" style="background:#fef2f2;border:1px solid #fecaca;border-radius:7px;padding:6px 12px;">
                                        <i class="fas fa-triangle-exclamation" style="color:#ef4444;font-size:0.8rem;"></i>
                                        <span style="font-size:0.8rem;color:#991b1b;font-weight:500;"><?= $pendingCount ?> user(s) will be removed across all routers.</span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12 col-md-4 text-md-end">
                                    <form method="POST">
                                        <button
                                            type="submit"
                                            class="btn px-4 py-2"
                                            style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;border-radius:9px;font-weight:600;font-size:0.88rem;letter-spacing:0.2px;box-shadow:0 4px 12px rgba(220,38,38,0.3);transition:all .2s;"
                                            <?= $pendingCount === 0 ? 'disabled' : '' ?>
                                            onclick="return confirm('This will disconnect and remove <?= $pendingCount ?> expired user(s) across ALL routers. Continue?');"
                                        >
                                            <i class="fas fa-bolt me-2"></i>Run Expiry Engine
                                        </button>
                                    </form>
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

    <style>
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn:not(:disabled):hover { filter: brightness(1.1); transform: translateY(-1px); }
    </style>

</body>
</html>