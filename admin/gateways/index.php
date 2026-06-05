<?php
// admin/gateways/index.php
require_once "../../core/db.php";
require_once "../../core/auth.php";
require_once "../../core/app.php";

/* ══════════════════════════════════════
   ENCRYPTION HELPERS
══════════════════════════════════════ */
function admin_encrypt(string $plain): string {
    $key = hash('sha256', APP_KEY);
    $iv  = substr(hash('sha256', APP_KEY), 0, 16);
    return openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
}
function admin_decrypt(string $cipher): string {
    $key = hash('sha256', APP_KEY);
    $iv  = substr(hash('sha256', APP_KEY), 0, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv);
}

/* ══════════════════════════════════════
   ACTIVE TAB
══════════════════════════════════════ */
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all', 'pending', 'pesaflux', 'apikeys'])) $tab = 'all';

/* ══════════════════════════════════════
   SEARCH / FILTER
══════════════════════════════════════ */
$search      = trim($_GET['q']        ?? '');
$filterType  = $_GET['type']          ?? '';
$filterProv  = $_GET['provider']      ?? '';
$filterStat  = $_GET['status']        ?? '';

/* ══════════════════════════════════════
   STAT COUNTS
══════════════════════════════════════ */
$totalGateways    = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE gateway_enabled=1")->fetchColumn();
$tillCount        = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE gateway_enabled=1 AND gateway_type='till'")->fetchColumn();
$paybillCount     = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE gateway_enabled=1 AND gateway_type='paybill'")->fetchColumn();
$pesafluxCount    = $pdo->query("SELECT COUNT(*) FROM user_api_keys WHERE provider='pesaflux'")->fetchColumn();
$mpesaOwnCount    = $pdo->query("SELECT COUNT(*) FROM user_api_keys WHERE provider='mpesa'")->fetchColumn();
$intasendCount    = $pdo->query("SELECT COUNT(*) FROM user_api_keys WHERE provider='intasend'")->fetchColumn();
$pendingApproval  = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE gateway_enabled=0 AND gateway_type IS NOT NULL AND gateway_identifier IS NOT NULL")->fetchColumn();
$expiringSoon     = $pdo->query("
    SELECT COUNT(*) FROM subscriptions
    WHERE gateway_enabled=1
      AND gateway_expires_at IS NOT NULL
      AND gateway_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
      AND gateway_plan NOT IN ('own')
")->fetchColumn();

/* ══════════════════════════════════════
   POST HANDLERS
══════════════════════════════════════ */
$flashSuccess = '';
$flashError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // ── Approve gateway (enable it) ──────────────────────────────
    if ($action === 'approve_gateway' && $user_id) {
        $pdo->prepare("
            UPDATE subscriptions SET gateway_enabled=1, updated_at=NOW() WHERE user_id=?
        ")->execute([$user_id]);
        $flashSuccess = 'Gateway approved and activated.';
    }

    // ── Reject / disable gateway ────────────────────────────────
    if ($action === 'reject_gateway' && $user_id) {
        $pdo->prepare("
            UPDATE subscriptions
            SET gateway_enabled=0, gateway_type=NULL, gateway_identifier=NULL,
                gateway_bank=NULL, gateway_plan=NULL, gateway_expires_at=NULL, updated_at=NOW()
            WHERE user_id=?
        ")->execute([$user_id]);
        $flashSuccess = 'Gateway rejected and cleared.';
    }

    // ── Revoke gateway ───────────────────────────────────────────
    if ($action === 'revoke_gateway' && $user_id) {
        $pdo->prepare("
            UPDATE subscriptions
            SET gateway_enabled=0, updated_at=NOW() WHERE user_id=?
        ")->execute([$user_id]);
        $flashSuccess = 'Gateway access revoked.';
    }

    // ── Restore gateway ──────────────────────────────────────────
    if ($action === 'restore_gateway' && $user_id) {
        $pdo->prepare("
            UPDATE subscriptions SET gateway_enabled=1, updated_at=NOW() WHERE user_id=?
        ")->execute([$user_id]);
        $flashSuccess = 'Gateway restored.';
    }

    // ── Save PesaFlux API keys for a user ────────────────────────
    if ($action === 'save_pesaflux_keys' && $user_id) {
        $consumerKey    = trim($_POST['pf_consumer_key']    ?? '');
        $consumerSecret = trim($_POST['pf_consumer_secret'] ?? '');
        $passkey        = trim($_POST['pf_passkey']         ?? '');
        $shortcode      = trim($_POST['pf_shortcode']       ?? '');
        $partyb         = trim($_POST['pf_partyb']          ?? '');
        $env            = ($_POST['pf_env'] ?? 'live') === 'sandbox' ? 'sandbox' : 'live';

        if (empty($consumerKey) || empty($consumerSecret) || empty($passkey) || empty($shortcode)) {
            $flashError = 'All PesaFlux key fields are required.';
        } else {
            $encCK = admin_encrypt($consumerKey);
            $encCS = admin_encrypt($consumerSecret);
            $encPK = admin_encrypt($passkey);
            if (empty($partyb)) $partyb = $shortcode;

            $pdo->prepare("
                INSERT INTO user_api_keys
                    (user_id, provider, consumer_key_encrypted, consumer_secret_encrypted,
                     passkey_encrypted, shortcode, partyb, environment, created_at, updated_at)
                VALUES (?, 'pesaflux', ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    consumer_key_encrypted    = VALUES(consumer_key_encrypted),
                    consumer_secret_encrypted = VALUES(consumer_secret_encrypted),
                    passkey_encrypted         = VALUES(passkey_encrypted),
                    shortcode                 = VALUES(shortcode),
                    partyb                    = VALUES(partyb),
                    environment               = VALUES(environment),
                    updated_at                = NOW()
            ")->execute([$user_id, $encCK, $encCS, $encPK, $shortcode, $partyb, $env]);

            // Activate gateway
            $pdo->prepare("
                UPDATE subscriptions SET gateway_enabled=1, updated_at=NOW() WHERE user_id=?
            ")->execute([$user_id]);

            $flashSuccess = 'PesaFlux API keys saved and gateway activated.';
        }
    }

    // ── Delete user API keys ─────────────────────────────────────
    if ($action === 'delete_api_keys' && $user_id) {
        $provider = trim($_POST['provider'] ?? '');
        if (in_array($provider, ['mpesa', 'pesaflux', 'intasend'])) {
            $pdo->prepare("DELETE FROM user_api_keys WHERE user_id=? AND provider=?")->execute([$user_id, $provider]);
            $pdo->prepare("UPDATE subscriptions SET gateway_enabled=0, updated_at=NOW() WHERE user_id=?")->execute([$user_id]);
            $flashSuccess = ucfirst($provider) . ' API keys deleted.';
        }
    }

    // ── Extend gateway expiry ────────────────────────────────────
    if ($action === 'extend_expiry' && $user_id) {
        $days = (int)($_POST['extend_days'] ?? 30);
        $pdo->prepare("
            UPDATE subscriptions
            SET gateway_expires_at = DATE_ADD(GREATEST(gateway_expires_at, NOW()), INTERVAL ? DAY),
                gateway_enabled=1, updated_at=NOW()
            WHERE user_id=?
        ")->execute([$days, $user_id]);
        $flashSuccess = "Gateway expiry extended by {$days} days.";
    }

    header("Location: index?tab={$tab}" . ($flashSuccess ? "&success=".urlencode($flashSuccess) : "&error=".urlencode($flashError)));
    exit;
}

if (isset($_GET['success'])) $flashSuccess = htmlspecialchars($_GET['success']);
if (isset($_GET['error']))   $flashError   = htmlspecialchars($_GET['error']);

/* ══════════════════════════════════════
   TAB QUERIES
══════════════════════════════════════ */

// ── ALL GATEWAYS ─────────────────────────────────────────────────
$allWhere  = ['s.gateway_enabled = 1'];
$allParams = [];
if ($search) {
    $allWhere[]  = '(u.full_names LIKE ? OR u.phone_number LIKE ? OR u.email LIKE ? OR s.gateway_identifier LIKE ?)';
    $allParams   = array_merge($allParams, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($filterType) {
    $allWhere[]  = 's.gateway_type = ?';
    $allParams[] = $filterType;
}
if ($filterProv) {
    $allWhere[]  = 'k.provider = ?';
    $allParams[] = $filterProv;
}
$allSQL = "
    SELECT u.user_id, u.full_names, u.phone_number, u.email,
           s.gateway_type, s.gateway_identifier, s.gateway_bank, s.gateway_account,
           s.gateway_plan, s.gateway_expires_at, s.updated_at,
           k.provider AS key_provider
    FROM subscriptions s
    JOIN users u ON u.user_id = s.user_id
    LEFT JOIN user_api_keys k ON k.user_id = s.user_id AND k.provider IN ('mpesa','pesaflux','intasend')
    WHERE " . implode(' AND ', $allWhere) . "
    ORDER BY s.updated_at DESC LIMIT 200
";
$stmtAll = $pdo->prepare($allSQL);
$stmtAll->execute($allParams);
$allGateways = $stmtAll->fetchAll();

// ── PENDING APPROVAL ─────────────────────────────────────────────
$pendingSQL = "
    SELECT u.user_id, u.full_names, u.phone_number, u.email,
           s.gateway_type, s.gateway_identifier, s.gateway_bank, s.gateway_account,
           s.gateway_plan, s.created_at, s.updated_at,
           k.provider AS key_provider
    FROM subscriptions s
    JOIN users u ON u.user_id = s.user_id
    LEFT JOIN user_api_keys k ON k.user_id = s.user_id AND k.provider IN ('mpesa','pesaflux','intasend')
    WHERE s.gateway_enabled = 0
      AND s.gateway_type IS NOT NULL
      AND s.gateway_identifier IS NOT NULL
    ORDER BY s.updated_at ASC LIMIT 200
";
$pendingRows = $pdo->query($pendingSQL)->fetchAll();

// ── PESAFLUX KEYS ────────────────────────────────────────────────
$pfSearch = trim($_GET['pf_q'] ?? '');
$pfWhere  = ["s.gateway_plan IN ('monthly','yearly')"];
$pfParams = [];
if ($pfSearch) {
    $pfWhere[]  = '(u.full_names LIKE ? OR u.phone_number LIKE ? OR s.gateway_identifier LIKE ?)';
    $pfParams   = ["%$pfSearch%", "%$pfSearch%", "%$pfSearch%"];
}
$pfSQL = "
    SELECT u.user_id, u.full_names, u.phone_number, u.email,
           s.gateway_type, s.gateway_identifier, s.gateway_bank, s.gateway_account,
           s.gateway_plan, s.gateway_expires_at, s.gateway_enabled,
           k.shortcode AS pf_shortcode, k.partyb AS pf_partyb,
           k.environment AS pf_env,
           CASE WHEN k.consumer_key_encrypted IS NOT NULL THEN 1 ELSE 0 END AS has_keys
    FROM subscriptions s
    JOIN users u ON u.user_id = s.user_id
    LEFT JOIN user_api_keys k ON k.user_id = s.user_id AND k.provider='pesaflux'
    WHERE " . implode(' AND ', $pfWhere) . "
    ORDER BY s.gateway_expires_at ASC LIMIT 200
";
$stmtPf = $pdo->prepare($pfSQL);
$stmtPf->execute($pfParams);
$pesafluxRows = $stmtPf->fetchAll();

// ── USER API KEYS ─────────────────────────────────────────────────
$akSearch   = trim($_GET['ak_q'] ?? '');
$akProvider = $_GET['ak_provider'] ?? '';
$akWhere    = ["k.provider IN ('mpesa','pesaflux','intasend')"];
$akParams   = [];
if ($akSearch) {
    $akWhere[]  = '(u.full_names LIKE ? OR u.phone_number LIKE ? OR u.email LIKE ?)';
    $akParams   = ["%$akSearch%", "%$akSearch%", "%$akSearch%"];
}
if ($akProvider) {
    $akWhere[]  = 'k.provider = ?';
    $akParams[] = $akProvider;
}
$akSQL = "
    SELECT u.user_id, u.full_names, u.phone_number, u.email,
           k.id AS key_id, k.provider, k.shortcode, k.partyb,
           k.environment, k.created_at AS key_created,
           CASE WHEN k.consumer_key_encrypted IS NOT NULL THEN 1
                WHEN k.public_key_encrypted   IS NOT NULL THEN 1 ELSE 0 END AS has_key,
           s.gateway_enabled, s.gateway_type, s.gateway_identifier
    FROM user_api_keys k
    JOIN users u ON u.user_id = k.user_id
    LEFT JOIN subscriptions s ON s.user_id = k.user_id
    WHERE " . implode(' AND ', $akWhere) . "
    ORDER BY k.created_at DESC LIMIT 200
";
$stmtAk = $pdo->prepare($akSQL);
$stmtAk->execute($akParams);
$apiKeyRows = $stmtAk->fetchAll();

/* ── helper ── */
function providerBadge(string $prov): string {
    return match ($prov) {
        'mpesa'    => '<span class="badge" style="background:rgba(74,222,128,.12);color:#22c55e;border-radius:6px;padding:3px 8px;font-size:.68rem;"><i class="material-icons" style="font-size:11px;vertical-align:middle;">key</i> M-Pesa</span>',
        'intasend' => '<span class="badge" style="background:rgba(14,165,233,.12);color:#38bdf8;border-radius:6px;padding:3px 8px;font-size:.68rem;"><i class="material-icons" style="font-size:11px;vertical-align:middle;">key</i> IntaSend</span>',
        'pesaflux' => '<span class="badge" style="background:rgba(251,191,36,.12);color:#fbbf24;border-radius:6px;padding:3px 8px;font-size:.68rem;"><i class="material-icons" style="font-size:11px;vertical-align:middle;">cloud</i> PesaFlux</span>',
        default    => '<span class="badge" style="background:rgba(100,116,139,.12);color:#64748b;border-radius:6px;padding:3px 8px;font-size:.68rem;">Unknown</span>',
    };
}
function gatewayTypeBadge(?string $t): string {
    if (!$t) return '<span class="text-muted small">—</span>';
    return $t === 'till'
        ? '<span class="badge" style="background:rgba(251,191,36,.1);color:#fbbf24;border-radius:6px;padding:3px 8px;font-size:.68rem;"><i class="material-icons" style="font-size:11px;vertical-align:middle;">store</i> Till</span>'
        : '<span class="badge" style="background:rgba(14,165,233,.1);color:#38bdf8;border-radius:6px;padding:3px 8px;font-size:.68rem;"><i class="material-icons" style="font-size:11px;vertical-align:middle;">account_balance</i> Paybill</span>';
}
function userAvatar(string $name, string $extra = ''): string {
    $letter = strtoupper(substr($name, 0, 1));
    return '<div style="width:36px;height:36px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.8rem;">'.$letter.'</div>';
}
?>
<?php require_once "../../partials/head.php" ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php" ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- ═══════════ PAGE HEADER ═══════════ -->
                <header style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%);border-bottom:1px solid rgba(99,102,241,.2);">
                    <div class="container-xl px-4">
                        <div class="d-flex justify-content-between align-items-center py-4 flex-wrap gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="material-icons" style="color:#818cf8;font-size:18px;">mobile_friendly</i>
                                    <span style="font-size:.7rem;color:#64748b;letter-spacing:.1em;text-transform:uppercase;font-weight:600;">Gateways › M-Pesa</span>
                                </div>
                                <h1 class="text-white mb-0 fw-bold" style="font-size:1.6rem;">M-Pesa Gateways</h1>
                                <p style="color:#64748b;" class="mb-0 mt-1 small">Manage payment gateways, API keys, and billing</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if ($pendingApproval > 0): ?>
                                <a href="?tab=pending" class="badge px-3 py-2 text-decoration-none"
                                    style="background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.25);border-radius:8px;font-size:.8rem;">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">pending_actions</i>
                                    <?= $pendingApproval ?> pending approval
                                </a>
                                <?php endif; ?>
                                <?php if ($expiringSoon > 0): ?>
                                <span class="badge px-3 py-2"
                                    style="background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.25);border-radius:8px;font-size:.8rem;">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">warning</i>
                                    <?= $expiringSoon ?> expiring in 7d
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 mt-4">

                    <!-- Flash Messages -->
                    <?php if ($flashSuccess): ?>
                        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert">
                            <i class="material-icons">check_circle</i><?= $flashSuccess ?>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($flashError): ?>
                        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert">
                            <i class="material-icons">error</i><?= $flashError ?>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- ═══════════ KPI CARDS ═══════════ -->
                    <div class="row g-3 mb-4">
                        <?php
                        $kpis = [
                            ['Total Active', $totalGateways,  'mobile_friendly', '#6366f1,#8b5cf6', '#818cf8'],
                            ['Till (BuyGoods)', $tillCount,   'store',           '#fbbf24,#f59e0b', '#fbbf24'],
                            ['Paybill',       $paybillCount,  'account_balance', '#38bdf8,#0ea5e9', '#38bdf8'],
                            ['Pending Approval', $pendingApproval, 'pending_actions', '#f87171,#ef4444', '#f87171'],
                        ];
                        foreach ($kpis as [$lbl, $val, $icon, $grad, $col]): ?>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 shadow-sm h-100" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="small text-muted fw-500"><?= $lbl ?></span>
                                        <span style="width:36px;height:36px;background:rgba(99,102,241,.08);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                            <i class="material-icons" style="font-size:18px;color:<?= $col ?>;"><?= $icon ?></i>
                                        </span>
                                    </div>
                                    <div class="h3 fw-bold mb-0"><?= number_format($val) ?></div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,<?= $grad ?>);"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ═══════════ TABS ═══════════ -->
                    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">
                        <!-- Tab Nav -->
                        <div style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                            <div class="px-4 pt-3 d-flex gap-1 flex-wrap">
                                <?php
                                $tabs = [
                                    ['all',      'mobile_friendly',  'All Gateways',    $totalGateways],
                                    ['pending',   'pending_actions',  'Pending Approval', $pendingApproval],
                                    ['pesaflux',  'cloud',            'PesaFlux Keys',   $pesafluxCount],
                                    ['apikeys',   'key',              'User API Keys',   $mpesaOwnCount + $intasendCount],
                                ];
                                foreach ($tabs as [$tid, $icon, $label, $count]):
                                    $active = $tab === $tid;
                                ?>
                                <a href="?tab=<?= $tid ?>"
                                    class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none small fw-600"
                                    style="border-radius:10px 10px 0 0;
                                           background:<?= $active ? 'rgba(99,102,241,.15)' : 'transparent' ?>;
                                           color:<?= $active ? '#a5b4fc' : '#64748b' ?>;
                                           border-bottom:<?= $active ? '2px solid #6366f1' : '2px solid transparent' ?>;
                                           transition:all .2s;">
                                    <i class="material-icons" style="font-size:16px;"><?= $icon ?></i>
                                    <?= $label ?>
                                    <?php if ($count > 0): ?>
                                        <span class="badge ms-1" style="background:<?= $active ? 'rgba(99,102,241,.3)' : 'rgba(100,116,139,.2)' ?>;color:<?= $active ? '#a5b4fc' : '#64748b' ?>;font-size:.65rem;"><?= $count ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- ══════════════════════════════
                             TAB: ALL GATEWAYS
                        ══════════════════════════════ -->
                        <?php if ($tab === 'all'): ?>
                        <div class="card-body p-0">
                            <!-- Filter Bar -->
                            <div class="p-3 border-bottom bg-white">
                                <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                                    <input type="hidden" name="tab" value="all">
                                    <div>
                                        <label class="form-label small fw-600 mb-1">Search</label>
                                        <input type="text" name="q" class="form-control form-control-sm" style="width:200px;"
                                            placeholder="Name, phone, till/paybill…" value="<?= htmlspecialchars($search) ?>">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-600 mb-1">Type</label>
                                        <select name="type" class="form-select form-select-sm" style="width:130px;">
                                            <option value="">All Types</option>
                                            <option value="till"    <?= $filterType==='till'    ?'selected':'' ?>>Till</option>
                                            <option value="paybill" <?= $filterType==='paybill' ?'selected':'' ?>>Paybill</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-600 mb-1">Provider</label>
                                        <select name="provider" class="form-select form-select-sm" style="width:140px;">
                                            <option value="">All Providers</option>
                                            <option value="mpesa"    <?= $filterProv==='mpesa'    ?'selected':'' ?>>M-Pesa (Own)</option>
                                            <option value="pesaflux" <?= $filterProv==='pesaflux' ?'selected':'' ?>>PesaFlux</option>
                                            <option value="intasend" <?= $filterProv==='intasend' ?'selected':'' ?>>IntaSend</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-sm px-3"
                                            style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#818cf8;border-radius:8px;">
                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">search</i> Filter
                                        </button>
                                        <a href="?tab=all" class="btn btn-sm px-3"
                                            style="background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;border-radius:8px;">
                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">clear</i>
                                        </a>
                                    </div>
                                </form>
                            </div>
                            <!-- Table -->
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                        <tr>
                                            <th class="px-4 py-3 small text-muted fw-600">User</th>
                                            <th class="py-3 small text-muted fw-600">Type</th>
                                            <th class="py-3 small text-muted fw-600">Identifier</th>
                                            <th class="py-3 small text-muted fw-600">Provider / Plan</th>
                                            <th class="py-3 small text-muted fw-600">Bank / Account</th>
                                            <th class="py-3 small text-muted fw-600">Expires</th>
                                            <th class="py-3 small text-muted fw-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($allGateways as $row):
                                        $isFree     = in_array($row['key_provider'], ['mpesa','intasend']);
                                        $expired    = false;
                                        $daysLeft   = null;
                                        if (!$isFree && $row['gateway_expires_at']) {
                                            $exp      = new DateTime($row['gateway_expires_at']);
                                            $now      = new DateTime();
                                            $expired  = $exp <= $now;
                                            $daysLeft = max(0, (int)$now->diff($exp)->days);
                                        }
                                    ?>
                                    <tr class="<?= $expired ? 'table-danger' : '' ?>">
                                        <td class="px-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <?= userAvatar($row['full_names']) ?>
                                                <div>
                                                    <div class="fw-600 small"><?= htmlspecialchars($row['full_names']) ?></div>
                                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($row['phone_number']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= gatewayTypeBadge($row['gateway_type']) ?></td>
                                        <td>
                                            <span class="font-monospace fw-600 small"><?= htmlspecialchars($row['gateway_identifier'] ?? '—') ?></span>
                                        </td>
                                        <td><?= providerBadge($row['key_provider'] ?? '') ?>
                                            <span class="ms-1 small text-muted"><?= ucfirst($row['gateway_plan'] ?? '') ?></span>
                                        </td>
                                        <td class="small text-muted">
                                            <?php if ($row['gateway_bank']): ?>
                                                <div><?= htmlspecialchars($row['gateway_bank']) ?></div>
                                                <div class="font-monospace" style="font-size:.7rem;"><?= htmlspecialchars($row['gateway_account'] ?? '') ?></div>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isFree): ?>
                                                <span class="small" style="color:#4ade80;"><i class="material-icons" style="font-size:13px;vertical-align:middle;">all_inclusive</i> No expiry</span>
                                            <?php elseif ($row['gateway_expires_at']): ?>
                                                <span class="small <?= $expired ? 'text-danger fw-bold' : ($daysLeft <= 7 ? 'text-warning fw-600' : 'text-muted') ?>">
                                                    <?= date('M d, Y', strtotime($row['gateway_expires_at'])) ?>
                                                    <?php if (!$expired && $daysLeft !== null): ?>
                                                        <br><span style="font-size:.68rem;"><?= $daysLeft ?>d left</span>
                                                    <?php elseif ($expired): ?>
                                                        <br><span style="font-size:.68rem;color:#ef4444;">Expired</span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <a href="../../admin/users/view?id=<?= $row['user_id'] ?>"
                                                    class="btn btn-sm"
                                                    style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:#818cf8;border-radius:7px;padding:3px 8px;" title="View user">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i>
                                                </a>
                                                <!-- Extend expiry (non-free plans) -->
                                                <?php if (!$isFree): ?>
                                                <button class="btn btn-sm" title="Extend Expiry"
                                                    onclick="openExtendModal(<?= $row['user_id'] ?>, '<?= htmlspecialchars($row['full_names']) ?>', '<?= $row['gateway_expires_at'] ?>')"
                                                    style="background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);color:#4ade80;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">event_available</i>
                                                </button>
                                                <?php endif; ?>
                                                <!-- Revoke -->
                                                <form method="POST" onsubmit="return confirm('Revoke this gateway?')">
                                                    <input type="hidden" name="action" value="revoke_gateway">
                                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                    <button type="submit" class="btn btn-sm" title="Revoke Gateway"
                                                        style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;border-radius:7px;padding:3px 8px;">
                                                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">mobile_off</i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($allGateways)): ?>
                                    <tr><td colspan="7" class="text-center py-5 text-muted">
                                        <i class="material-icons d-block mb-2" style="font-size:40px;opacity:.25;">mobile_friendly</i>
                                        No active gateways found.
                                    </td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>


                        <!-- ══════════════════════════════
                             TAB: PENDING APPROVAL
                        ══════════════════════════════ -->
                        <?php elseif ($tab === 'pending'): ?>
                        <div class="card-body p-0">
                            <?php if (empty($pendingRows)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="material-icons d-block mb-2" style="font-size:48px;opacity:.2;">check_circle</i>
                                    <div class="fw-600 mb-1">All clear!</div>
                                    <div class="small">No gateways are waiting for approval.</div>
                                </div>
                            <?php else: ?>
                            <div class="p-3 border-bottom bg-amber-50" style="background:#fffbeb;">
                                <div class="d-flex align-items-center gap-2 small text-warning fw-600">
                                    <i class="material-icons" style="font-size:18px;">pending_actions</i>
                                    <?= count($pendingRows) ?> gateway(s) submitted by users are awaiting your review.
                                    Verify the details and approve or reject each one.
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                        <tr>
                                            <th class="px-4 py-3 small text-muted fw-600">User</th>
                                            <th class="py-3 small text-muted fw-600">Type</th>
                                            <th class="py-3 small text-muted fw-600">Identifier</th>
                                            <th class="py-3 small text-muted fw-600">Provider</th>
                                            <th class="py-3 small text-muted fw-600">Bank / Account</th>
                                            <th class="py-3 small text-muted fw-600">Plan</th>
                                            <th class="py-3 small text-muted fw-600">Submitted</th>
                                            <th class="py-3 small text-muted fw-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($pendingRows as $row): ?>
                                    <tr>
                                        <td class="px-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <?= userAvatar($row['full_names']) ?>
                                                <div>
                                                    <div class="fw-600 small"><?= htmlspecialchars($row['full_names']) ?></div>
                                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($row['phone_number']) ?></div>
                                                    <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($row['email'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= gatewayTypeBadge($row['gateway_type']) ?></td>
                                        <td>
                                            <span class="font-monospace fw-bold" style="font-size:.9rem;">
                                                <?= htmlspecialchars($row['gateway_identifier'] ?? '—') ?>
                                            </span>
                                        </td>
                                        <td><?= providerBadge($row['key_provider'] ?? 'pesaflux') ?></td>
                                        <td class="small text-muted">
                                            <?php if ($row['gateway_bank']): ?>
                                                <?= htmlspecialchars($row['gateway_bank']) ?><br>
                                                <span class="font-monospace" style="font-size:.7rem;"><?= htmlspecialchars($row['gateway_account'] ?? '') ?></span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background:rgba(99,102,241,.1);color:#818cf8;border-radius:6px;padding:3px 8px;font-size:.7rem;">
                                                <?= ucfirst($row['gateway_plan'] ?? 'monthly') ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?= date('M d, Y', strtotime($row['updated_at'])) ?></td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <!-- Approve -->
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="approve_gateway">
                                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                    <button type="submit" class="btn btn-sm fw-600"
                                                        style="background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.3);color:#22c55e;border-radius:7px;padding:4px 10px;font-size:.75rem;">
                                                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">check_circle</i> Approve
                                                    </button>
                                                </form>
                                                <!-- Reject -->
                                                <form method="POST" onsubmit="return confirm('Reject and clear this gateway request?')">
                                                    <input type="hidden" name="action" value="reject_gateway">
                                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                    <button type="submit" class="btn btn-sm fw-600"
                                                        style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;border-radius:7px;padding:4px 10px;font-size:.75rem;">
                                                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">cancel</i> Reject
                                                    </button>
                                                </form>
                                                <!-- View user -->
                                                <a href="../../admin/users/view?id=<?= $row['user_id'] ?>"
                                                    class="btn btn-sm"
                                                    style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:#818cf8;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>


                        <!-- ══════════════════════════════
                             TAB: PESAFLUX KEYS
                        ══════════════════════════════ -->
                        <?php elseif ($tab === 'pesaflux'): ?>
                        <div class="card-body p-0">
                            <!-- Header + search -->
                            <div class="p-3 border-bottom bg-white d-flex flex-wrap gap-3 align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="material-icons" style="color:#fbbf24;font-size:20px;">cloud</i>
                                    <span class="fw-600 small">PesaFlux users need M-Pesa Daraja keys assigned by admins. Assign keys below.</span>
                                </div>
                                <form method="GET" class="d-flex gap-2">
                                    <input type="hidden" name="tab" value="pesaflux">
                                    <input type="text" name="pf_q" class="form-control form-control-sm" style="width:220px;"
                                        placeholder="Search name, phone, identifier…" value="<?= htmlspecialchars($pfSearch) ?>">
                                    <button type="submit" class="btn btn-sm px-3"
                                        style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#818cf8;border-radius:8px;">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">search</i>
                                    </button>
                                </form>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                        <tr>
                                            <th class="px-4 py-3 small text-muted fw-600">User</th>
                                            <th class="py-3 small text-muted fw-600">Gateway</th>
                                            <th class="py-3 small text-muted fw-600">Plan</th>
                                            <th class="py-3 small text-muted fw-600">Expires</th>
                                            <th class="py-3 small text-muted fw-600">API Keys</th>
                                            <th class="py-3 small text-muted fw-600">Status</th>
                                            <th class="py-3 small text-muted fw-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($pesafluxRows as $row):
                                        $expired   = false;
                                        $daysLeft  = null;
                                        if ($row['gateway_expires_at']) {
                                            $exp     = new DateTime($row['gateway_expires_at']);
                                            $now     = new DateTime();
                                            $expired = $exp <= $now;
                                            $daysLeft = max(0, (int)$now->diff($exp)->days);
                                        }
                                    ?>
                                    <tr>
                                        <td class="px-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <?= userAvatar($row['full_names']) ?>
                                                <div>
                                                    <div class="fw-600 small"><?= htmlspecialchars($row['full_names']) ?></div>
                                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($row['phone_number']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <?= gatewayTypeBadge($row['gateway_type']) ?>
                                                <span class="font-monospace small fw-600 ms-1"><?= htmlspecialchars($row['gateway_identifier'] ?? '—') ?></span>
                                            </div>
                                            <?php if ($row['gateway_bank']): ?>
                                                <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($row['gateway_bank']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background:rgba(99,102,241,.1);color:#818cf8;border-radius:6px;padding:3px 8px;font-size:.7rem;">
                                                <?= ucfirst($row['gateway_plan'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['gateway_expires_at']): ?>
                                                <span class="small <?= $expired ? 'text-danger fw-bold' : ($daysLeft <= 7 ? 'text-warning fw-600' : 'text-muted') ?>">
                                                    <?= date('M d, Y', strtotime($row['gateway_expires_at'])) ?>
                                                    <?php if (!$expired): ?><br><span style="font-size:.68rem;"><?= $daysLeft ?>d left</span><?php endif; ?>
                                                </span>
                                            <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['has_keys']): ?>
                                                <span class="badge d-flex align-items-center gap-1 w-fit"
                                                    style="background:rgba(74,222,128,.1);color:#22c55e;border-radius:6px;padding:3px 8px;font-size:.7rem;width:fit-content;">
                                                    <i class="material-icons" style="font-size:12px;">check_circle</i>
                                                    Assigned
                                                    <?php if ($row['pf_env'] === 'sandbox'): ?>
                                                        <span class="badge ms-1" style="background:rgba(251,191,36,.2);color:#fbbf24;font-size:.62rem;">sandbox</span>
                                                    <?php endif; ?>
                                                </span>
                                                <?php if ($row['pf_shortcode']): ?>
                                                    <div class="font-monospace text-muted" style="font-size:.68rem;">SC: <?= htmlspecialchars($row['pf_shortcode']) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge" style="background:rgba(239,68,68,.1);color:#f87171;border-radius:6px;padding:3px 8px;font-size:.7rem;">
                                                    <i class="material-icons" style="font-size:12px;vertical-align:middle;">warning</i> Missing
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['gateway_enabled']): ?>
                                                <span class="badge" style="background:rgba(74,222,128,.1);color:#22c55e;border-radius:20px;padding:4px 10px;font-size:.7rem;">
                                                    <span style="width:5px;height:5px;background:#22c55e;border-radius:50%;display:inline-block;margin-right:4px;"></span>Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background:rgba(239,68,68,.1);color:#f87171;border-radius:20px;padding:4px 10px;font-size:.7rem;">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <button class="btn btn-sm fw-600" title="Assign / Update PesaFlux Keys"
                                                    onclick="openPesafluxModal(<?= $row['user_id'] ?>, '<?= htmlspecialchars(addslashes($row['full_names'])) ?>', '<?= htmlspecialchars($row['pf_shortcode'] ?? '') ?>', '<?= htmlspecialchars($row['pf_partyb'] ?? '') ?>', '<?= $row['pf_env'] ?? 'live' ?>')"
                                                    style="background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.3);color:#fbbf24;border-radius:7px;padding:4px 10px;font-size:.75rem;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">vpn_key</i> <?= $row['has_keys'] ? 'Update Keys' : 'Assign Keys' ?>
                                                </button>
                                                <button class="btn btn-sm" title="Extend Expiry"
                                                    onclick="openExtendModal(<?= $row['user_id'] ?>, '<?= htmlspecialchars(addslashes($row['full_names'])) ?>', '<?= $row['gateway_expires_at'] ?>')"
                                                    style="background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);color:#4ade80;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">event_available</i>
                                                </button>
                                                <a href="../../admin/users/view?id=<?= $row['user_id'] ?>"
                                                    class="btn btn-sm"
                                                    style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:#818cf8;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($pesafluxRows)): ?>
                                    <tr><td colspan="7" class="text-center py-5 text-muted">
                                        <i class="material-icons d-block mb-2" style="font-size:40px;opacity:.25;">cloud_off</i>
                                        No PesaFlux users found.
                                    </td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>


                        <!-- ══════════════════════════════
                             TAB: USER API KEYS
                        ══════════════════════════════ -->
                        <?php elseif ($tab === 'apikeys'): ?>
                        <div class="card-body p-0">
                            <!-- Filter -->
                            <div class="p-3 border-bottom bg-white">
                                <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                                    <input type="hidden" name="tab" value="apikeys">
                                    <div>
                                        <label class="form-label small fw-600 mb-1">Search</label>
                                        <input type="text" name="ak_q" class="form-control form-control-sm" style="width:220px;"
                                            placeholder="Name, phone, email…" value="<?= htmlspecialchars($akSearch) ?>">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-600 mb-1">Provider</label>
                                        <select name="ak_provider" class="form-select form-select-sm" style="width:140px;">
                                            <option value="">All Providers</option>
                                            <option value="mpesa"    <?= $akProvider==='mpesa'    ?'selected':'' ?>>M-Pesa (Own)</option>
                                            <option value="intasend" <?= $akProvider==='intasend' ?'selected':'' ?>>IntaSend</option>
                                            <option value="pesaflux" <?= $akProvider==='pesaflux' ?'selected':'' ?>>PesaFlux</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-sm px-3"
                                            style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#818cf8;border-radius:8px;">
                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">search</i> Filter
                                        </button>
                                        <a href="?tab=apikeys" class="btn btn-sm px-3"
                                            style="background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;border-radius:8px;">
                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">clear</i>
                                        </a>
                                    </div>
                                </form>
                            </div>

                            <!-- Provider summary pills -->
                            <div class="p-3 border-bottom bg-white d-flex gap-3 flex-wrap">
                                <span class="d-flex align-items-center gap-2 small">
                                    <span style="width:10px;height:10px;background:#22c55e;border-radius:50%;display:inline-block;"></span>
                                    M-Pesa Own: <strong><?= $mpesaOwnCount ?></strong>
                                </span>
                                <span class="d-flex align-items-center gap-2 small">
                                    <span style="width:10px;height:10px;background:#38bdf8;border-radius:50%;display:inline-block;"></span>
                                    IntaSend: <strong><?= $intasendCount ?></strong>
                                </span>
                                <span class="d-flex align-items-center gap-2 small">
                                    <span style="width:10px;height:10px;background:#fbbf24;border-radius:50%;display:inline-block;"></span>
                                    PesaFlux: <strong><?= $pesafluxCount ?></strong>
                                </span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                        <tr>
                                            <th class="px-4 py-3 small text-muted fw-600">User</th>
                                            <th class="py-3 small text-muted fw-600">Provider</th>
                                            <th class="py-3 small text-muted fw-600">Shortcode / PartyB</th>
                                            <th class="py-3 small text-muted fw-600">Gateway</th>
                                            <th class="py-3 small text-muted fw-600">Keys</th>
                                            <th class="py-3 small text-muted fw-600">Added</th>
                                            <th class="py-3 small text-muted fw-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($apiKeyRows as $row): ?>
                                    <tr>
                                        <td class="px-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <?= userAvatar($row['full_names']) ?>
                                                <div>
                                                    <div class="fw-600 small"><?= htmlspecialchars($row['full_names']) ?></div>
                                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($row['phone_number']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= providerBadge($row['provider']) ?></td>
                                        <td>
                                            <div class="font-monospace small fw-600"><?= htmlspecialchars($row['shortcode'] ?? '—') ?></div>
                                            <?php if ($row['partyb'] && $row['partyb'] !== $row['shortcode']): ?>
                                                <div class="text-muted font-monospace" style="font-size:.7rem;">PartyB: <?= htmlspecialchars($row['partyb']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($row['environment'] === 'sandbox'): ?>
                                                <span class="badge" style="background:rgba(251,191,36,.15);color:#f59e0b;font-size:.65rem;border-radius:4px;padding:1px 5px;">sandbox</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['gateway_enabled']): ?>
                                                <div class="d-flex align-items-center gap-1">
                                                    <?= gatewayTypeBadge($row['gateway_type']) ?>
                                                    <span class="font-monospace small"><?= htmlspecialchars($row['gateway_identifier'] ?? '') ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">Gateway inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['has_key']): ?>
                                                <span class="badge d-flex align-items-center gap-1"
                                                    style="background:rgba(74,222,128,.1);color:#22c55e;border-radius:6px;padding:3px 8px;font-size:.7rem;width:fit-content;">
                                                    <i class="material-icons" style="font-size:11px;">lock</i> Encrypted
                                                </span>
                                            <?php else: ?>
                                                <span class="badge" style="background:rgba(239,68,68,.1);color:#f87171;border-radius:6px;padding:3px 8px;font-size:.7rem;">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?= date('M d, Y', strtotime($row['key_created'])) ?></td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <a href="../../admin/users/view?id=<?= $row['user_id'] ?>"
                                                    class="btn btn-sm"
                                                    style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);color:#818cf8;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i>
                                                </a>
                                                <?php if (!$row['gateway_enabled']): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="restore_gateway">
                                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                    <button type="submit" class="btn btn-sm" title="Re-enable gateway"
                                                        style="background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);color:#4ade80;border-radius:7px;padding:3px 8px;">
                                                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">play_circle</i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <form method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars(ucfirst($row['provider'])) ?> API keys for this user?')">
                                                    <input type="hidden" name="action" value="delete_api_keys">
                                                    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                                    <input type="hidden" name="provider" value="<?= htmlspecialchars($row['provider']) ?>">
                                                    <button type="submit" class="btn btn-sm" title="Delete keys"
                                                        style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;border-radius:7px;padding:3px 8px;">
                                                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($apiKeyRows)): ?>
                                    <tr><td colspan="7" class="text-center py-5 text-muted">
                                        <i class="material-icons d-block mb-2" style="font-size:40px;opacity:.25;">key_off</i>
                                        No user API keys found.
                                    </td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div><!-- end main card -->
                </div>
            </main>
            <?php require_once "../../partials/footer.php" ?>
        </div>
    </div>

    <?php require_once "../../partials/scripts.php" ?>


    <!-- ═══════════════════════════════════════════════════
         MODAL: Assign PesaFlux API Keys
    ═══════════════════════════════════════════════════ -->
    <div class="modal fade" id="pesafluxModal" tabindex="-1" aria-labelledby="pesafluxModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
                <div class="modal-header" style="background:linear-gradient(135deg,#0f172a,#1e1b4b);border-bottom:1px solid rgba(99,102,241,.2);">
                    <div>
                        <h5 class="modal-title text-white fw-bold mb-0" id="pesafluxModalLabel">
                            <i class="material-icons" style="vertical-align:middle;color:#fbbf24;">cloud</i>
                            Assign PesaFlux API Keys
                        </h5>
                        <div class="small mt-1" style="color:#64748b;" id="pf-modal-user-name"></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="save_pesaflux_keys">
                    <input type="hidden" name="user_id" id="pf-modal-user-id">
                    <div class="modal-body p-4">
                        <div class="alert alert-warning py-2 mb-4 small">
                            <i class="material-icons" style="font-size:15px;vertical-align:middle;">info</i>
                            These are <strong>admin-assigned Daraja API credentials</strong> for this user's PesaFlux gateway.
                            Keys are stored encrypted with AES-256.
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-600 mb-1">Consumer Key</label>
                                <input type="text" class="form-control font-monospace" name="pf_consumer_key"
                                    id="pf-modal-ck" placeholder="Safaricom Daraja Consumer Key" autocomplete="off" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-600 mb-1">Consumer Secret</label>
                                <div class="input-group">
                                    <input type="password" class="form-control font-monospace" name="pf_consumer_secret"
                                        id="pf-modal-cs" placeholder="Consumer Secret" autocomplete="off" required>
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="toggleVis('pf-modal-cs', this)">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">visibility</i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-600 mb-1">Passkey</label>
                                <div class="input-group">
                                    <input type="password" class="form-control font-monospace" name="pf_passkey"
                                        id="pf-modal-pk" placeholder="Lipa Na M-Pesa Passkey" autocomplete="off" required>
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="toggleVis('pf-modal-pk', this)">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">visibility</i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-600 mb-1">Business Shortcode</label>
                                <input type="text" class="form-control font-monospace" name="pf_shortcode"
                                    id="pf-modal-sc" placeholder="e.g. 174379" pattern="\d{5,8}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-600 mb-1">PartyB <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="text" class="form-control font-monospace" name="pf_partyb"
                                    id="pf-modal-pb" placeholder="Defaults to shortcode" pattern="\d{5,8}">
                                <div class="form-text" style="font-size:.7rem;">Leave blank for Paybill; enter till number for BuyGoods.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-600 mb-1">Environment</label>
                                <div class="d-flex gap-2">
                                    <div class="flex-fill">
                                        <input type="radio" class="btn-check" name="pf_env" id="pf_env_live" value="live" checked>
                                        <label class="btn btn-outline-success w-100 btn-sm" for="pf_env_live">
                                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">check_circle</i> Live
                                        </label>
                                    </div>
                                    <div class="flex-fill">
                                        <input type="radio" class="btn-check" name="pf_env" id="pf_env_sandbox" value="sandbox">
                                        <label class="btn btn-outline-warning w-100 btn-sm" for="pf_env_sandbox">
                                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">science</i> Sandbox
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top:1px solid #e2e8f0;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm px-4 fw-600"
                            style="background:linear-gradient(135deg,#f59e0b,#fbbf24);border:none;color:#000;border-radius:8px;">
                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">save</i> Save & Activate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ═══════════════════════════════════════════════════
         MODAL: Extend Expiry
    ═══════════════════════════════════════════════════ -->
    <div class="modal fade" id="extendModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
                <div class="modal-header" style="background:linear-gradient(135deg,#0f172a,#064e3b);border-bottom:1px solid rgba(74,222,128,.2);">
                    <h5 class="modal-title text-white fw-bold mb-0">
                        <i class="material-icons" style="vertical-align:middle;color:#4ade80;">event_available</i>
                        Extend Expiry
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="extend_expiry">
                    <input type="hidden" name="user_id" id="ext-modal-user-id">
                    <div class="modal-body p-4">
                        <div class="small text-muted mb-3" id="ext-modal-user-name"></div>
                        <div class="mb-3">
                            <div class="small text-muted mb-2">Current expiry:</div>
                            <div class="fw-bold font-monospace small" id="ext-modal-expiry"></div>
                        </div>
                        <label class="form-label fw-600 small">Extend by</label>
                        <div class="d-flex gap-2 flex-wrap mb-2">
                            <?php foreach ([30 => '30d', 60 => '60d', 90 => '90d', 365 => '1y'] as $days => $lbl): ?>
                            <div>
                                <input type="radio" class="btn-check" name="extend_days" id="ext_<?= $days ?>" value="<?= $days ?>"
                                    <?= $days === 30 ? 'checked' : '' ?>>
                                <label class="btn btn-outline-success btn-sm" for="ext_<?= $days ?>"><?= $lbl ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="small text-muted">Extension is added on top of the current expiry date (or today if already expired).</div>
                    </div>
                    <div class="modal-footer" style="border-top:1px solid #e2e8f0;">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm px-4 fw-600">
                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">save</i> Extend
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
    // ── Toggle password visibility ───────────────────────────────
    function toggleVis(inputId, btn) {
        const el = document.getElementById(inputId);
        const show = el.type === 'password';
        el.type = show ? 'text' : 'password';
        btn.innerHTML = show
            ? '<i class="material-icons" style="font-size:16px;vertical-align:middle;">visibility_off</i>'
            : '<i class="material-icons" style="font-size:16px;vertical-align:middle;">visibility</i>';
    }

    // ── Open PesaFlux modal ──────────────────────────────────────
    function openPesafluxModal(userId, name, shortcode, partyb, env) {
        document.getElementById('pf-modal-user-id').value   = userId;
        document.getElementById('pf-modal-user-name').textContent = name;
        document.getElementById('pf-modal-sc').value        = shortcode || '';
        document.getElementById('pf-modal-pb').value        = partyb    || '';
        document.getElementById('pf-modal-ck').value        = '';
        document.getElementById('pf-modal-cs').value        = '';
        document.getElementById('pf-modal-pk').value        = '';
        // Set environment radio
        var envEl = document.getElementById('pf_env_' + (env || 'live'));
        if (envEl) envEl.checked = true;
        new bootstrap.Modal(document.getElementById('pesafluxModal')).show();
    }

    // ── Open extend expiry modal ─────────────────────────────────
    function openExtendModal(userId, name, expiresAt) {
        document.getElementById('ext-modal-user-id').textContent  = '';
        document.getElementById('ext-modal-user-id').value        = userId;
        document.getElementById('ext-modal-user-name').textContent = name;
        document.getElementById('ext-modal-expiry').textContent   = expiresAt
            ? new Date(expiresAt).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'})
            : 'Not set';
        new bootstrap.Modal(document.getElementById('extendModal')).show();
    }
    </script>

</body>
</html>