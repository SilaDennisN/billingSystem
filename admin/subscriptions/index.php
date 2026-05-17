<?php
// admin/subscriptions/index.php
require_once "../../core/db.php";
require_once "../../core/auth.php";
require_once "../../core/app.php";

/* ══════════════════════════════════════
   FILTERS
══════════════════════════════════════ */
$filterStatus  = $_GET['status']   ?? '';
$filterGateway = $_GET['gateway']  ?? '';
$filterPlan    = $_GET['plan']     ?? '';
$search        = trim($_GET['q']   ?? '');

$where   = ['1=1'];
$params  = [];

if ($filterStatus) {
    $where[]  = 's.status = ?';
    $params[] = $filterStatus;
}
if ($filterGateway === 'enabled') {
    $where[] = 's.gateway_enabled = 1';
} elseif ($filterGateway === 'disabled') {
    $where[] = '(s.gateway_enabled = 0 OR s.gateway_enabled IS NULL)';
}
if ($filterPlan) {
    $where[]  = 's.gateway_plan = ?';
    $params[] = $filterPlan;
}
if ($search) {
    $where[]  = '(u.full_names LIKE ? OR u.phone_number LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = implode(' AND ', $where);

/* ══════════════════════════════════════
   STAT COUNTS
══════════════════════════════════════ */
$totalSubs      = $pdo->query("SELECT COUNT(*) FROM subscriptions")->fetchColumn();
$activeSubs     = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn();
$suspendedSubs  = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='suspended'")->fetchColumn();
$gatewayOn      = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE gateway_enabled=1")->fetchColumn();
$expiringSoon   = $pdo->query("
    SELECT COUNT(*) FROM subscriptions
    WHERE gateway_enabled=1
      AND gateway_expires_at IS NOT NULL
      AND gateway_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
      AND gateway_plan NOT IN ('own')
")->fetchColumn();

/* ══════════════════════════════════════
   MAIN QUERY
══════════════════════════════════════ */
$stmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.full_names,
        u.phone_number,
        u.email,
        s.status,
        s.gateway_enabled,
        s.gateway_type,
        s.gateway_identifier,
        s.gateway_plan,
        s.gateway_expires_at,
        s.created_at AS sub_created,
        s.updated_at AS sub_updated,
        k.provider   AS key_provider
    FROM users u
    LEFT JOIN subscriptions s     ON s.user_id = u.user_id
    LEFT JOIN user_api_keys k     ON k.user_id = u.user_id
        AND k.provider IN ('mpesa','pesaflux','intasend')
    WHERE $whereSQL
    ORDER BY s.status ASC, u.full_names ASC
    LIMIT 200
");
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

/* ══════════════════════════════════════
   POST: suspend / reactivate / disable_gateway
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id) {
        if ($action === 'suspend') {
            $pdo->prepare("UPDATE subscriptions SET status='suspended', updated_at=NOW() WHERE user_id=?")->execute([$user_id]);
        } elseif ($action === 'reactivate') {
            $pdo->prepare("UPDATE subscriptions SET status='active', updated_at=NOW() WHERE user_id=?")->execute([$user_id]);
        } elseif ($action === 'disable_gateway') {
            $pdo->prepare("
                UPDATE subscriptions
                SET gateway_enabled=0, gateway_type=NULL, gateway_identifier=NULL,
                    gateway_plan=NULL, gateway_expires_at=NULL, updated_at=NOW()
                WHERE user_id=?
            ")->execute([$user_id]);
        }
    }
    header("Location: index"); exit;
}
?>
<?php require_once "../../partials/head.php" ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php" ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Page Header -->
                <header style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%);border-bottom:1px solid rgba(99,102,241,.2);">
                    <div class="container-xl px-4">
                        <div class="d-flex justify-content-between align-items-center py-4 flex-wrap gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="material-icons" style="color:#818cf8;font-size:18px;">card_membership</i>
                                    <span style="font-size:.7rem;color:#64748b;letter-spacing:.1em;text-transform:uppercase;font-weight:600;">Users › Subscriptions</span>
                                </div>
                                <h1 class="text-white mb-0 fw-bold" style="font-size:1.6rem;">Subscriptions</h1>
                                <p style="color:#64748b;" class="mb-0 mt-1 small">Manage user plans, gateway status and billing cycles</p>
                            </div>
                            <div class="d-flex gap-2">
                                <span class="badge px-3 py-2" style="background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.25);border-radius:8px;font-size:.8rem;">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">warning</i>
                                    <?= $expiringSoon ?> expiring in 7d
                                </span>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 mt-4">

                    <!-- KPI Cards -->
                    <div class="row g-3 mb-4">
                        <?php
                        $cards = [
                            ['Total Subscriptions', $totalSubs,    'card_membership', '#6366f1,#8b5cf6', '#6366f1'],
                            ['Active',              $activeSubs,   'check_circle',    '#4ade80,#22c55e', '#4ade80'],
                            ['Suspended',           $suspendedSubs,'pause_circle',    '#f87171,#ef4444', '#f87171'],
                            ['Gateways Live',       $gatewayOn,    'mobile_friendly', '#0ea5e9,#38bdf8', '#0ea5e9'],
                        ];
                        foreach ($cards as [$label, $val, $icon, $grad, $color]): ?>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="small text-muted fw-500"><?= $label ?></span>
                                        <span style="width:36px;height:36px;background:rgba(99,102,241,.08);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:<?= $color ?>;"><?= $icon ?></i>
                                        </span>
                                    </div>
                                    <div class="h3 fw-bold mb-0"><?= number_format($val) ?></div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,<?= $grad ?>);"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Filters -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                        <div class="card-body p-3">
                            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                                <div>
                                    <label class="form-label small fw-600 mb-1">Search</label>
                                    <input type="text" name="q" class="form-control form-control-sm" style="width:200px;"
                                        placeholder="Name, phone, email…" value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div>
                                    <label class="form-label small fw-600 mb-1">Status</label>
                                    <select name="status" class="form-select form-select-sm" style="width:140px;">
                                        <option value="">All Statuses</option>
                                        <option value="active"    <?= $filterStatus==='active'    ? 'selected':'' ?>>Active</option>
                                        <option value="suspended" <?= $filterStatus==='suspended' ? 'selected':'' ?>>Suspended</option>
                                        <option value="cancelled" <?= $filterStatus==='cancelled' ? 'selected':'' ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small fw-600 mb-1">Gateway</label>
                                    <select name="gateway" class="form-select form-select-sm" style="width:140px;">
                                        <option value="">All</option>
                                        <option value="enabled"  <?= $filterGateway==='enabled'  ? 'selected':'' ?>>Enabled</option>
                                        <option value="disabled" <?= $filterGateway==='disabled' ? 'selected':'' ?>>Disabled</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small fw-600 mb-1">Plan</label>
                                    <select name="plan" class="form-select form-select-sm" style="width:130px;">
                                        <option value="">All Plans</option>
                                        <option value="monthly" <?= $filterPlan==='monthly' ? 'selected':'' ?>>Monthly</option>
                                        <option value="yearly"  <?= $filterPlan==='yearly'  ? 'selected':'' ?>>Yearly</option>
                                        <option value="own"     <?= $filterPlan==='own'     ? 'selected':'' ?>>Own Keys</option>
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-sm px-3"
                                        style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#818cf8;border-radius:8px;">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">search</i> Filter
                                    </button>
                                    <a href="index" class="btn btn-sm px-3"
                                        style="background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;border-radius:8px;">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">clear</i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">
                        <div class="card-header d-flex justify-content-between align-items-center px-4 py-3"
                            style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                            <div class="d-flex align-items-center gap-2">
                                <i class="material-icons" style="color:#818cf8;font-size:20px;">card_membership</i>
                                <span class="fw-bold text-white">All Subscriptions</span>
                                <span class="badge ms-1" style="background:rgba(99,102,241,.2);color:#a5b4fc;font-size:.7rem;">
                                    <?= count($subscriptions) ?> results
                                </span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                    <tr>
                                        <th class="px-4 py-3 small text-muted fw-600">User</th>
                                        <th class="py-3 small text-muted fw-600">Sub Status</th>
                                        <th class="py-3 small text-muted fw-600">Since</th>
                                        <th class="py-3 small text-muted fw-600">Gateway</th>
                                        <th class="py-3 small text-muted fw-600">Provider / Plan</th>
                                        <th class="py-3 small text-muted fw-600">Expires</th>
                                        <th class="py-3 small text-muted fw-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($subscriptions as $row):
                                    $isActive  = $row['status'] === 'active';
                                    $gwOn      = $row['gateway_enabled'] == 1;
                                    $provider  = strtolower($row['key_provider'] ?? '');
                                    $isFree    = in_array($provider, ['mpesa','intasend']);
                                    $expired   = false;
                                    $daysLeft  = null;
                                    if ($gwOn && $row['gateway_expires_at'] && !$isFree) {
                                        $exp     = new DateTime($row['gateway_expires_at']);
                                        $now     = new DateTime();
                                        $expired = $exp <= $now;
                                        $daysLeft = max(0, (int)$now->diff($exp)->days);
                                    }
                                ?>
                                <tr>
                                    <td class="px-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div style="width:36px;height:36px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.8rem;">
                                                <?= strtoupper(substr($row['full_names'],0,1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-600 small"><?= htmlspecialchars($row['full_names']) ?></div>
                                                <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($row['phone_number']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="badge" style="background:rgba(74,222,128,.12);color:#22c55e;border-radius:20px;padding:4px 10px;font-size:.7rem;">
                                                <span style="width:5px;height:5px;background:#22c55e;border-radius:50%;display:inline-block;margin-right:4px;"></span>Active
                                            </span>
                                        <?php elseif ($row['status'] === 'suspended'): ?>
                                            <span class="badge" style="background:rgba(239,68,68,.1);color:#ef4444;border-radius:20px;padding:4px 10px;font-size:.7rem;">Suspended</span>
                                        <?php else: ?>
                                            <span class="badge" style="background:rgba(100,116,139,.1);color:#64748b;border-radius:20px;padding:4px 10px;font-size:.7rem;"><?= ucfirst($row['status'] ?? 'None') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?= $row['sub_created'] ? date('M d, Y', strtotime($row['sub_created'])) : '—' ?></span>
                                    </td>
                                    <td>
                                        <?php if ($gwOn && !$expired): ?>
                                            <span class="badge d-flex align-items-center gap-1" style="background:rgba(74,222,128,.1);color:#22c55e;border-radius:20px;padding:4px 10px;width:fit-content;font-size:.7rem;">
                                                <i class="material-icons" style="font-size:12px;">check_circle</i>
                                                <?= $row['gateway_type'] === 'till' ? 'Till' : 'Paybill' ?>&nbsp;<span class="font-monospace"><?= htmlspecialchars($row['gateway_identifier'] ?? '') ?></span>
                                            </span>
                                        <?php elseif ($expired): ?>
                                            <span class="badge" style="background:rgba(239,68,68,.1);color:#ef4444;border-radius:20px;padding:4px 10px;font-size:.7rem;">
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
                                                <i class="material-icons" style="font-size:11px;vertical-align:middle;">cloud</i> PesaFlux · <?= ucfirst($row['gateway_plan'] ?? 'Billed') ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;font-size:.75rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isFree && $gwOn): ?>
                                            <span class="small" style="color:#4ade80;"><i class="material-icons" style="font-size:13px;vertical-align:middle;">all_inclusive</i> No expiry</span>
                                        <?php elseif ($row['gateway_expires_at']): ?>
                                            <span class="small <?= $daysLeft !== null && $daysLeft <= 7 ? 'text-danger fw-600' : 'text-muted' ?>">
                                                <?= date('M d, Y', strtotime($row['gateway_expires_at'])) ?>
                                                <?php if ($daysLeft !== null && !$expired): ?>
                                                    <br><span style="font-size:.68rem;"><?= $daysLeft ?>d left</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="../../admin/users/view?id=<?= $row['user_id'] ?>"
                                                class="btn btn-sm" title="View user"
                                                style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:#818cf8;border-radius:7px;padding:3px 8px;">
                                                <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i>
                                            </a>
                                            <?php if ($isActive): ?>
                                            <form method="POST" onsubmit="return confirm('Suspend this user\'s subscription?')">
                                                <input type="hidden" name="action" value="suspend">
                                                <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                <button type="submit" class="btn btn-sm" title="Suspend"
                                                    style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">pause_circle</i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="reactivate">
                                                <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                <button type="submit" class="btn btn-sm" title="Reactivate"
                                                    style="background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);color:#4ade80;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">play_circle</i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($gwOn): ?>
                                            <form method="POST" onsubmit="return confirm('Disable this user\'s gateway?')">
                                                <input type="hidden" name="action" value="disable_gateway">
                                                <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                <button type="submit" class="btn btn-sm" title="Disable Gateway"
                                                    style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);color:#fbbf24;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">mobile_off</i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($subscriptions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="material-icons d-block mb-2" style="font-size:40px;opacity:.25;">card_membership</i>
                                        No subscriptions match your filters.
                                    </td>
                                </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </main>
            <?php require_once "../../partials/footer.php" ?>
        </div>
    </div>
    <?php require_once "../../partials/scripts.php" ?>
</body>
</html>