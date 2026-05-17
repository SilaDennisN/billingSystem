<?php
// admin/dashboard/index.php  — starter admin dashboard
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/app.php";

// TODO: replace with admin auth check
// if (!is_admin()) { header("Location: ../../auth/login"); exit; }

/* ══════════════════════════════════════
   PLATFORM STATS
══════════════════════════════════════ */

// Total users
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Active subscriptions
$activeSubscriptions = $pdo->query("
    SELECT COUNT(*) FROM subscriptions WHERE status = 'active'
")->fetchColumn();

// Gateways enabled
$gatewaysEnabled = $pdo->query("
    SELECT COUNT(*) FROM subscriptions WHERE gateway_enabled = 1
")->fetchColumn();

// Platform fee collected this month
$platformFeeThisMonth = $pdo->query("
    SELECT IFNULL(SUM(platform_fee), 0) FROM subscription_invoices
    WHERE status = 'paid'
      AND MONTH(created_at) = MONTH(CURDATE())
      AND YEAR(created_at)  = YEAR(CURDATE())
")->fetchColumn();

// Pending gateway invoices
$pendingGateway = $pdo->query("
    SELECT COUNT(*) FROM gateway_invoices WHERE status = 'pending'
")->fetchColumn();

/* ══════════════════════════════════════
   PER-USER INCOME TABLE
   Shows: user, this month's billed income, platform fee, gateway status
══════════════════════════════════════ */
$stmt = $pdo->query("
    SELECT
    u.user_id,
    u.full_names,
    u.phone_number,
    u.email,
    s.status           AS sub_status,
    s.gateway_enabled,
    s.gateway_type,
    s.gateway_identifier,
    s.gateway_plan,
    s.gateway_expires_at,
    k.provider         AS key_provider,
    IFNULL(income.total, 0) AS monthly_income,
    ROUND(IFNULL(income.total, 0) * 0.05, 2) AS platform_fee
FROM users u
LEFT JOIN subscriptions s ON s.user_id = u.user_id
LEFT JOIN user_api_keys k ON k.user_id = u.user_id
    AND k.provider IN ('mpesa', 'pesaflux', 'intasend')
LEFT JOIN (
    SELECT ura.user_id, SUM(p.amount) AS total   -- ← was p.router_id
    FROM payments p
    JOIN user_router_access ura ON ura.router_id = p.router_id
    WHERE p.status = 'used'
      AND MONTH(p.created_at) = MONTH(CURDATE())
      AND YEAR(p.created_at)  = YEAR(CURDATE())
    GROUP BY ura.user_id                          -- ← correct grouping
) income ON income.user_id = u.user_id
ORDER BY monthly_income DESC
LIMIT 50
");
$users = $stmt->fetchAll();
?>
<?php require_once "../partials/head.php" ?>

<body class="nav-fixed bg-light">
    <?php require_once "partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "partials/sidebar.php" ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Page header -->
                <header style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); border-bottom: 1px solid rgba(99,102,241,.2);">
                    <div class="container-xl px-4">
                        <div class="d-flex justify-content-between align-items-center py-4">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span style="width:8px;height:8px;background:#4ade80;border-radius:50%;display:inline-block;"></span>
                                    <span style="font-size:.7rem;color:#64748b;letter-spacing:.1em;text-transform:uppercase;font-weight:600;">Live</span>
                                </div>
                                <h1 class="text-white mb-0 display-6 fw-bold">Admin Dashboard</h1>
                                <p style="color:#64748b;" class="mb-0 mt-1 small">
                                    Platform overview · <?= date('l, F j Y') ?>
                                </p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="../admin/users/create" class="btn btn-sm"
                                    style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:#a5b4fc;border-radius:8px;">
                                    <i class="material-icons" style="font-size:16px;vertical-align:middle;">person_add</i>
                                    Add User
                                </a>
                                <a href="../admin/gateways/pending" class="btn btn-sm"
                                    style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.25);color:#fbbf24;border-radius:8px;">
                                    <i class="material-icons" style="font-size:16px;vertical-align:middle;">pending_actions</i>
                                    Pending (<?= $pendingGateway ?>)
                                </a>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 mt-4">

                    <!-- ══ KPI STAT CARDS ══ -->
                    <div class="row g-3 mb-4">

                        <!-- Total Users -->
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px; overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small text-muted fw-500">Total Users</span>
                                        <span style="width:36px;height:36px;background:rgba(99,102,241,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:#6366f1;">people</i>
                                        </span>
                                    </div>
                                    <div class="h3 fw-bold mb-0"><?= number_format($totalUsers) ?></div>
                                    <div class="small text-muted mt-1">registered accounts</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#6366f1,#8b5cf6);"></div>
                            </div>
                        </div>

                        <!-- Active Subscriptions -->
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px; overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small text-muted fw-500">Active Plans</span>
                                        <span style="width:36px;height:36px;background:rgba(74,222,128,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:#4ade80;">check_circle</i>
                                        </span>
                                    </div>
                                    <div class="h3 fw-bold mb-0"><?= number_format($activeSubscriptions) ?></div>
                                    <div class="small text-muted mt-1">active subscriptions</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#4ade80,#22c55e);"></div>
                            </div>
                        </div>

                        <!-- Platform Fee This Month -->
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px; overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small text-muted fw-500">Fees This Month</span>
                                        <span style="width:36px;height:36px;background:rgba(251,191,36,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:#fbbf24;">payments</i>
                                        </span>
                                    </div>
                                    <div class="h3 fw-bold mb-0">KES <?= number_format($platformFeeThisMonth, 0) ?></div>
                                    <div class="small text-muted mt-1">platform revenue</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#fbbf24,#f59e0b);"></div>
                            </div>
                        </div>

                        <!-- Active Gateways -->
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px; overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <span class="small text-muted fw-500">Active Gateways</span>
                                        <span style="width:36px;height:36px;background:rgba(14,165,233,.1);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:#0ea5e9;">mobile_friendly</i>
                                        </span>
                                    </div>
                                    <div class="h3 fw-bold mb-0"><?= number_format($gatewaysEnabled) ?></div>
                                    <div class="small text-muted mt-1">M-Pesa gateways live</div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#0ea5e9,#38bdf8);"></div>
                            </div>
                        </div>

                    </div>

                    <!-- ══ USER INCOME + GATEWAY TABLE ══ -->
                    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px; overflow:hidden;">
                        <div class="card-header d-flex justify-content-between align-items-center px-4 py-3"
                            style="background:#0f172a; border-bottom:1px solid rgba(99,102,241,.2);">
                            <div class="d-flex align-items-center gap-2">
                                <i class="material-icons" style="color:#818cf8; font-size:20px;">manage_accounts</i>
                                <span class="fw-bold text-white">User Overview</span>
                                <span class="badge ms-1" style="background:rgba(99,102,241,.2);color:#a5b4fc;font-size:.7rem;">
                                    <?= count($users) ?> users
                                </span>
                            </div>
                            <div class="d-flex gap-2">
                                <input type="text" id="userSearch" class="form-control form-control-sm"
                                    placeholder="Search user..."
                                    style="background:rgba(255,255,255,.06);border:1px solid rgba(99,102,241,.25);color:#e2e8f0;border-radius:8px;width:180px;font-size:.8rem;">
                                <a href="../admin/users" class="btn btn-sm"
                                    style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:#a5b4fc;border-radius:8px;font-size:.8rem;">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle" id="usersTable">
                                <thead style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                                    <tr>
                                        <th class="px-4 py-3 small text-muted fw-600">User</th>
                                        <th class="py-3 small text-muted fw-600">Status</th>
                                        <th class="py-3 small text-muted fw-600">This Month Income</th>
                                        <th class="py-3 small text-muted fw-600">Platform Fee</th>
                                        <th class="py-3 small text-muted fw-600">Gateway</th>
                                        <th class="py-3 small text-muted fw-600">Provider</th>
                                        <th class="py-3 small text-muted fw-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($users as $u): ?>
                                    <?php
                                    $isActive   = $u['sub_status'] === 'active';
                                    $gwOn       = $u['gateway_enabled'] == 1;
                                    $provider   = strtolower($u['key_provider'] ?? '');
                                    $isFreeGw   = in_array($provider, ['mpesa','intasend']);
                                    $gwExpired  = false;
                                    if ($gwOn && $u['gateway_expires_at'] && !$isFreeGw) {
                                        $gwExpired = strtotime($u['gateway_expires_at']) <= time();
                                    }
                                    ?>
                                    <tr class="user-row">
                                        <td class="px-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <div style="
                                                    width:36px;height:36px;border-radius:10px;flex-shrink:0;
                                                    background:linear-gradient(135deg,#6366f1,#8b5cf6);
                                                    display:flex;align-items:center;justify-content:center;
                                                    font-weight:700;color:#fff;font-size:.8rem;">
                                                    <?= strtoupper(substr($u['full_names'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-600 small"><?= htmlspecialchars($u['full_names']) ?></div>
                                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($u['phone_number']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($isActive): ?>
                                                <span class="badge" style="background:rgba(74,222,128,.12);color:#22c55e;border-radius:20px;font-size:.7rem;padding:4px 10px;">
                                                    <span style="width:5px;height:5px;background:#22c55e;border-radius:50%;display:inline-block;margin-right:4px;"></span>
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background:rgba(239,68,68,.1);color:#ef4444;border-radius:20px;font-size:.7rem;padding:4px 10px;">
                                                    <?= ucfirst($u['sub_status'] ?? 'None') ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold" style="color:#0f172a;">
                                                KES <?= number_format($u['monthly_income'], 0) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="fw-600" style="color:<?= $u['platform_fee'] > 0 ? '#f59e0b' : '#94a3b8' ?>;">
                                                KES <?= number_format($u['platform_fee'], 0) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($gwOn && !$gwExpired): ?>
                                                <span class="badge d-flex align-items-center gap-1"
                                                    style="background:rgba(74,222,128,.1);color:#22c55e;border-radius:20px;font-size:.7rem;padding:4px 10px;width:fit-content;">
                                                    <i class="material-icons" style="font-size:12px;">check_circle</i>
                                                    <?= $u['gateway_type'] === 'till' ? 'Till' : 'Paybill' ?>
                                                    <span class="font-monospace"><?= htmlspecialchars($u['gateway_identifier']) ?></span>
                                                </span>
                                            <?php elseif ($gwExpired): ?>
                                                <span class="badge" style="background:rgba(239,68,68,.1);color:#ef4444;border-radius:20px;font-size:.7rem;padding:4px 10px;">
                                                    <i class="material-icons" style="font-size:12px;vertical-align:middle;">warning</i> Expired
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#94a3b8;font-size:.75rem;">— None —</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($provider === 'mpesa'): ?>
                                                <span class="badge" style="background:rgba(74,222,128,.1);color:#22c55e;font-size:.7rem;border-radius:6px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:11px;vertical-align:middle;">key</i> M-Pesa · Free
                                                </span>
                                            <?php elseif ($provider === 'intasend'): ?>
                                                <span class="badge" style="background:rgba(14,165,233,.1);color:#0ea5e9;font-size:.7rem;border-radius:6px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:11px;vertical-align:middle;">key</i> IntaSend · Free
                                                </span>
                                            <?php elseif ($provider === 'pesaflux'): ?>
                                                <span class="badge" style="background:rgba(251,191,36,.1);color:#fbbf24;font-size:.7rem;border-radius:6px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:11px;vertical-align:middle;">cloud</i> PesaFlux · Billed
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#94a3b8;font-size:.75rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="../admin/users/view?id=<?= $u['user_id'] ?>"
                                                    class="btn btn-sm"
                                                    style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:#818cf8;border-radius:7px;padding:3px 8px;font-size:.75rem;"
                                                    title="View user">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i>
                                                </a>
                                                <?php if (!$gwOn || !$provider): ?>
                                                <button class="btn btn-sm"
                                                    style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);color:#fbbf24;border-radius:7px;padding:3px 8px;font-size:.75rem;"
                                                    title="Assign gateway keys"
                                                    onclick="openKeyModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['full_names']) ?>')">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">vpn_key</i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="material-icons d-block mb-2" style="font-size:36px;opacity:.3;">people</i>
                                            No users found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div><!-- /container -->
            </main>

            <?php require_once "../partials/footer.php" ?>
        </div>
    </div>

    <!-- ══ ASSIGN API KEYS MODAL ══ -->
    <div class="modal fade" id="keyModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
                <div class="modal-header" style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                    <h5 class="modal-title text-white">
                        <i class="material-icons me-2" style="color:#818cf8;vertical-align:middle;">vpn_key</i>
                        Assign PesaFlux API Keys
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../admin/gateways/assign-key">
                    <div class="modal-body p-4">
                        <input type="hidden" name="user_id" id="keyUserId">
                        <div class="alert py-2 mb-4" style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);border-radius:10px;color:#818cf8;font-size:.85rem;">
                            <i class="material-icons me-1" style="font-size:16px;vertical-align:middle;">info</i>
                            Assigning PesaFlux keys for: <strong id="keyUserName"></strong>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-600 small">Consumer Key</label>
                            <input type="text" class="form-control font-monospace" name="consumer_key"
                                placeholder="Safaricom consumer key" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-600 small">Consumer Secret</label>
                            <div class="input-group">
                                <input type="password" class="form-control font-monospace" name="consumer_secret"
                                    id="adminConsumerSecret" placeholder="Consumer secret" required autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="toggleVisibility('adminConsumerSecret', this)">
                                    <i class="material-icons" style="font-size:18px;vertical-align:middle;">visibility</i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-600 small">Passkey</label>
                            <div class="input-group">
                                <input type="password" class="form-control font-monospace" name="passkey"
                                    id="adminPasskey" placeholder="Lipa Na M-Pesa passkey" required autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="toggleVisibility('adminPasskey', this)">
                                    <i class="material-icons" style="font-size:18px;vertical-align:middle;">visibility</i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-600 small">Shortcode</label>
                            <input type="text" class="form-control" name="shortcode"
                                placeholder="e.g. 174379" pattern="\d{5,8}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-600 small">Activate Gateway</label>
                            <div class="d-flex gap-2">
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="activate" id="activateYes" value="1" checked>
                                    <label class="btn btn-outline-success w-100 btn-sm" for="activateYes">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">check_circle</i> Activate Now
                                    </label>
                                </div>
                                <div class="flex-fill">
                                    <input type="radio" class="btn-check" name="activate" id="activateNo" value="0">
                                    <label class="btn btn-outline-secondary w-100 btn-sm" for="activateNo">
                                        Save Only
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm px-4"
                            style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;">
                            <i class="material-icons me-1" style="font-size:16px;vertical-align:middle;">save</i>
                            Save Keys
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php require_once "../partials/scripts.php" ?>

    <script>
    // Assign key modal
    function openKeyModal(userId, userName) {
        document.getElementById('keyUserId').value  = userId;
        document.getElementById('keyUserName').textContent = userName;
        new bootstrap.Modal(document.getElementById('keyModal')).show();
    }

    // Password toggle
    function toggleVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
        btn.querySelector('i').textContent = isPass ? 'visibility_off' : 'visibility';
    }

    // Live user search
    document.getElementById('userSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#usersTable tbody .user-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
    </script>
</body>
</html>