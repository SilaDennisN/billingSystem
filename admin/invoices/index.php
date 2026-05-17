<?php
// admin/invoices/index.php
require_once "../../core/db.php";
require_once "../../core/auth.php";
require_once "../../core/app.php";

/* ══════════════════════════════════════
   POST: mark paid / mark overdue
══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action']  ?? '';
    $type   = $_POST['type']    ?? ''; // 'platform' or 'gateway'
    $id     = (int)($_POST['id'] ?? 0);

    if ($id) {
        $table = $type === 'gateway' ? 'gateway_invoices' : 'subscription_invoices';
        if ($action === 'mark_paid') {
            $pdo->prepare("UPDATE $table SET status='paid', updated_at=NOW() WHERE id=?")->execute([$id]);
            // If gateway invoice paid → activate gateway
            if ($type === 'gateway') {
                $inv = $pdo->prepare("SELECT * FROM gateway_invoices WHERE id=?");
                $inv->execute([$id]);
                $inv = $inv->fetch();
                if ($inv) {
                    $expires = $inv['plan'] === 'yearly'
                        ? date('Y-m-d H:i:s', strtotime('+1 year'))
                        : date('Y-m-d H:i:s', strtotime('+1 month'));
                    $pdo->prepare("
                        UPDATE subscriptions
                        SET gateway_enabled=1, gateway_expires_at=?, updated_at=NOW()
                        WHERE user_id=?
                    ")->execute([$expires, $inv['user_id']]);
                }
            }
        } elseif ($action === 'mark_overdue') {
            $pdo->prepare("UPDATE $table SET status='overdue', updated_at=NOW() WHERE id=?")->execute([$id]);
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM $table WHERE id=? AND status != 'paid'")->execute([$id]);
        }
    }
    header("Location: index?tab=" . ($_POST['tab'] ?? 'platform')); exit;
}

$activeTab = $_GET['tab'] ?? 'platform';

/* ══════════════════════════════════════
   FILTERS — shared
══════════════════════════════════════ */
$filterStatus = $_GET['status'] ?? '';
$filterMonth  = (int)($_GET['month'] ?? 0);
$search       = trim($_GET['q'] ?? '');

function buildWhere(string $alias, string $status, int $month, string $search): array {
    $where  = ['1=1'];
    $params = [];
    if ($status) { $where[] = "$alias.status = ?"; $params[] = $status; }
    if ($month)  { $where[] = "MONTH($alias.created_at) = ?"; $params[] = $month; }
    if ($search) {
        $where[]  = "(u.full_names LIKE ? OR u.phone_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    return [implode(' AND ', $where), $params];
}

/* ══════════════════════════════════════
   PLATFORM INVOICES
══════════════════════════════════════ */
[$pWhere, $pParams] = buildWhere('si', $filterStatus, $filterMonth, $search);
$platformStmt = $pdo->prepare("
    SELECT si.*, u.full_names, u.phone_number, u.email
    FROM subscription_invoices si
    JOIN users u ON u.user_id = si.user_id
    WHERE $pWhere
    ORDER BY si.created_at DESC
    LIMIT 300
");
$platformStmt->execute($pParams);
$platformInvoices = $platformStmt->fetchAll();

/* ══════════════════════════════════════
   GATEWAY INVOICES
══════════════════════════════════════ */
[$gWhere, $gParams] = buildWhere('gi', $filterStatus, $filterMonth, $search);
$gatewayStmt = $pdo->prepare("
    SELECT gi.*, u.full_names, u.phone_number, u.email,
           s.gateway_type, s.gateway_identifier
    FROM gateway_invoices gi
    JOIN users u ON u.user_id = gi.user_id
    LEFT JOIN subscriptions s ON s.user_id = gi.user_id
    WHERE $gWhere
    ORDER BY gi.created_at DESC
    LIMIT 300
");
$gatewayStmt->execute($gParams);
$gatewayInvoices = $gatewayStmt->fetchAll();

/* ══════════════════════════════════════
   SUMMARY STATS
══════════════════════════════════════ */
$pStats = $pdo->query("
    SELECT
        IFNULL(SUM(CASE WHEN status='paid'    THEN total_amount END), 0) AS paid,
        IFNULL(SUM(CASE WHEN status='pending' THEN total_amount END), 0) AS pending,
        IFNULL(SUM(CASE WHEN status='overdue' THEN total_amount END), 0) AS overdue,
        COUNT(*) AS total
    FROM subscription_invoices
    WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())
")->fetch();

$gStats = $pdo->query("
    SELECT
        IFNULL(SUM(CASE WHEN status='paid'    THEN amount END), 0) AS paid,
        IFNULL(SUM(CASE WHEN status='pending' THEN amount END), 0) AS pending,
        IFNULL(SUM(CASE WHEN status='overdue' THEN amount END), 0) AS overdue,
        COUNT(*) AS total
    FROM gateway_invoices
    WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())
")->fetch();
?>
<?php require_once "../../partials/head.php" ?>

<body class="nav-fixed bg-light">
    <?php require_once "../partials/topnav.php" ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php" ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Header -->
                <header style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%);border-bottom:1px solid rgba(99,102,241,.2);">
                    <div class="container-xl px-4">
                        <div class="d-flex justify-content-between align-items-center py-4 flex-wrap gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="material-icons" style="color:#818cf8;font-size:18px;">receipt_long</i>
                                    <span style="font-size:.7rem;color:#64748b;letter-spacing:.1em;text-transform:uppercase;font-weight:600;">Billing › Invoices</span>
                                </div>
                                <h1 class="text-white mb-0 fw-bold" style="font-size:1.6rem;">Invoices</h1>
                                <p style="color:#64748b;" class="mb-0 mt-1 small">Platform fee invoices and gateway subscription invoices</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if ($pStats['overdue'] > 0): ?>
                                <span class="badge px-3 py-2" style="background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.25);border-radius:8px;font-size:.8rem;">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">warning</i>
                                    KES <?= number_format($pStats['overdue'], 0) ?> overdue (platform)
                                </span>
                                <?php endif; ?>
                                <?php if ($gStats['pending'] > 0): ?>
                                <span class="badge px-3 py-2" style="background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.25);border-radius:8px;font-size:.8rem;">
                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">pending_actions</i>
                                    <?= $gStats['total'] ?> gateway pending
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-4 mt-4">

                    <!-- ══ STATS ROW ══ -->
                    <div class="row g-3 mb-4">
                        <!-- Platform this month -->
                        <div class="col-12 col-md-6">
                            <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="small fw-600 text-muted mb-3">
                                        <i class="material-icons" style="font-size:15px;vertical-align:middle;color:#6366f1;">receipt_long</i>
                                        Platform Invoices — This Month
                                    </div>
                                    <div class="row g-2">
                                        <?php foreach ([['Collected','paid','#22c55e',$pStats['paid']],['Pending','pending','#fbbf24',$pStats['pending']],['Overdue','overdue','#ef4444',$pStats['overdue']]] as [$lbl,,$col,$val]): ?>
                                        <div class="col-4 text-center">
                                            <div class="fw-bold" style="color:<?= $col ?>;font-size:1rem;">KES <?= number_format($val,0) ?></div>
                                            <div class="small text-muted"><?= $lbl ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#6366f1,#8b5cf6);"></div>
                            </div>
                        </div>
                        <!-- Gateway this month -->
                        <div class="col-12 col-md-6">
                            <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
                                <div class="card-body p-3">
                                    <div class="small fw-600 text-muted mb-3">
                                        <i class="material-icons" style="font-size:15px;vertical-align:middle;color:#0ea5e9;">mobile_friendly</i>
                                        Gateway Invoices — This Month
                                    </div>
                                    <div class="row g-2">
                                        <?php foreach ([['Collected','paid','#22c55e',$gStats['paid']],['Pending','pending','#fbbf24',$gStats['pending']],['Overdue','overdue','#ef4444',$gStats['overdue']]] as [$lbl,,$col,$val]): ?>
                                        <div class="col-4 text-center">
                                            <div class="fw-bold" style="color:<?= $col ?>;font-size:1rem;">KES <?= number_format($val,0) ?></div>
                                            <div class="small text-muted"><?= $lbl ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div style="height:3px;background:linear-gradient(90deg,#0ea5e9,#38bdf8);"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ FILTER BAR ══ -->
                    <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                        <div class="card-body p-3">
                            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                                <div>
                                    <label class="form-label small fw-600 mb-1">Search</label>
                                    <input type="text" name="q" class="form-control form-control-sm" style="width:200px;"
                                        placeholder="Name or phone…" value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div>
                                    <label class="form-label small fw-600 mb-1">Status</label>
                                    <select name="status" class="form-select form-select-sm" style="width:140px;">
                                        <option value="">All</option>
                                        <option value="paid"    <?= $filterStatus==='paid'    ?'selected':'' ?>>Paid</option>
                                        <option value="pending" <?= $filterStatus==='pending' ?'selected':'' ?>>Pending</option>
                                        <option value="overdue" <?= $filterStatus==='overdue' ?'selected':'' ?>>Overdue</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small fw-600 mb-1">Month</label>
                                    <select name="month" class="form-select form-select-sm" style="width:140px;">
                                        <option value="">All Months</option>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $filterMonth===$m?'selected':'' ?>>
                                            <?= date('F', mktime(0,0,0,$m,1)) ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-sm px-3"
                                        style="background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#818cf8;border-radius:8px;">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">search</i> Filter
                                    </button>
                                    <a href="index?tab=<?= $activeTab ?>" class="btn btn-sm px-3"
                                        style="background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;border-radius:8px;">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">clear</i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ══ TABS ══ -->
                    <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;overflow:hidden;">

                        <!-- Tab nav in dark header -->
                        <div style="background:#0f172a;border-bottom:1px solid rgba(99,102,241,.2);">
                            <div class="px-4 pt-3 d-flex gap-1">
                                <a href="?tab=platform&status=<?= urlencode($filterStatus) ?>&month=<?= $filterMonth ?>&q=<?= urlencode($search) ?>"
                                    class="px-4 py-2 text-decoration-none small fw-600 border-0"
                                    style="border-radius:10px 10px 0 0;
                                        background:<?= $activeTab==='platform' ? 'rgba(99,102,241,.18)' : 'transparent' ?>;
                                        color:<?= $activeTab==='platform' ? '#a5b4fc' : '#64748b' ?>;
                                        border-bottom:<?= $activeTab==='platform' ? '2px solid #6366f1' : 'none' ?>;">
                                    <i class="material-icons" style="font-size:15px;vertical-align:middle;">receipt_long</i>
                                    Platform Invoices
                                    <span class="badge ms-1" style="background:rgba(99,102,241,.3);color:#c7d2fe;font-size:.65rem;">
                                        <?= count($platformInvoices) ?>
                                    </span>
                                </a>
                                <a href="?tab=gateway&status=<?= urlencode($filterStatus) ?>&month=<?= $filterMonth ?>&q=<?= urlencode($search) ?>"
                                    class="px-4 py-2 text-decoration-none small fw-600"
                                    style="border-radius:10px 10px 0 0;
                                        background:<?= $activeTab==='gateway' ? 'rgba(99,102,241,.18)' : 'transparent' ?>;
                                        color:<?= $activeTab==='gateway' ? '#a5b4fc' : '#64748b' ?>;
                                        border-bottom:<?= $activeTab==='gateway' ? '2px solid #6366f1' : 'none' ?>;">
                                    <i class="material-icons" style="font-size:15px;vertical-align:middle;">mobile_friendly</i>
                                    Gateway Invoices
                                    <span class="badge ms-1" style="background:rgba(99,102,241,.3);color:#c7d2fe;font-size:.65rem;">
                                        <?= count($gatewayInvoices) ?>
                                    </span>
                                </a>
                            </div>
                        </div>

                        <!-- ── PLATFORM INVOICES TABLE ── -->
                        <?php if ($activeTab === 'platform'): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                    <tr>
                                        <th class="px-4 py-3 small text-muted fw-600">#</th>
                                        <th class="py-3 small text-muted fw-600">User</th>
                                        <th class="py-3 small text-muted fw-600">Period</th>
                                        <th class="py-3 small text-muted fw-600">Billed Income</th>
                                        <th class="py-3 small text-muted fw-600">Platform Fee</th>
                                        <th class="py-3 small text-muted fw-600">Gateway Fee</th>
                                        <th class="py-3 small text-muted fw-600">Total</th>
                                        <th class="py-3 small text-muted fw-600">Status</th>
                                        <th class="py-3 small text-muted fw-600">Date</th>
                                        <th class="py-3 small text-muted fw-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($platformInvoices as $inv):
                                    $st = $inv['status'];
                                    $stColor = $st==='paid' ? '#22c55e' : ($st==='pending' ? '#fbbf24' : '#ef4444');
                                    $stBg    = $st==='paid' ? 'rgba(74,222,128,.12)' : ($st==='pending' ? 'rgba(251,191,36,.12)' : 'rgba(239,68,68,.12)');
                                ?>
                                <tr>
                                    <td class="px-4">
                                        <span class="font-monospace text-muted small">#<?= str_pad($inv['id'],5,'0',STR_PAD_LEFT) ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.72rem;flex-shrink:0;">
                                                <?= strtoupper(substr($inv['full_names'],0,1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-600 small"><?= htmlspecialchars($inv['full_names']) ?></div>
                                                <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($inv['phone_number']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="small text-muted">
                                            <?= date('M d', strtotime($inv['period_start'])) ?> →
                                            <?= date('M d Y', strtotime($inv['period_end'])) ?>
                                        </span>
                                    </td>
                                    <td class="fw-600 small">KES <?= number_format($inv['billed_income'], 0) ?></td>
                                    <td><span style="color:#f59e0b;font-weight:600;font-size:.85rem;">KES <?= number_format($inv['platform_fee'], 0) ?></span></td>
                                    <td class="small text-muted"><?= $inv['gateway_fee'] > 0 ? 'KES '.number_format($inv['gateway_fee'],0) : '—' ?></td>
                                    <td class="fw-bold small">KES <?= number_format($inv['total_amount'], 0) ?></td>
                                    <td>
                                        <span class="badge" style="background:<?= $stBg ?>;color:<?= $stColor ?>;border-radius:20px;padding:4px 10px;font-size:.7rem;">
                                            <?= $st==='paid' ? '✓ Paid' : ucfirst($st) ?>
                                        </span>
                                    </td>
                                    <td><span class="small text-muted"><?= date('M d, Y', strtotime($inv['created_at'])) ?></span></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if ($st !== 'paid'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="type"   value="platform">
                                                <input type="hidden" name="id"     value="<?= $inv['id'] ?>">
                                                <input type="hidden" name="tab"    value="platform">
                                                <button type="submit" class="btn btn-sm" title="Mark Paid"
                                                    style="background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.25);color:#22c55e;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">check_circle</i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($st === 'pending'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="mark_overdue">
                                                <input type="hidden" name="type"   value="platform">
                                                <input type="hidden" name="id"     value="<?= $inv['id'] ?>">
                                                <input type="hidden" name="tab"    value="platform">
                                                <button type="submit" class="btn btn-sm" title="Mark Overdue"
                                                    style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">warning</i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($st !== 'paid'): ?>
                                            <form method="POST" onsubmit="return confirm('Delete this invoice?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="type"   value="platform">
                                                <input type="hidden" name="id"     value="<?= $inv['id'] ?>">
                                                <input type="hidden" name="tab"    value="platform">
                                                <button type="submit" class="btn btn-sm" title="Delete"
                                                    style="background:rgba(100,116,139,.08);border:1px solid rgba(100,116,139,.2);color:#94a3b8;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($platformInvoices)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="material-icons d-block mb-2" style="font-size:40px;opacity:.2;">receipt_long</i>
                                        No platform invoices found.
                                    </td>
                                </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ── GATEWAY INVOICES TABLE ── -->
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                    <tr>
                                        <th class="px-4 py-3 small text-muted fw-600">#</th>
                                        <th class="py-3 small text-muted fw-600">User</th>
                                        <th class="py-3 small text-muted fw-600">Gateway</th>
                                        <th class="py-3 small text-muted fw-600">Plan</th>
                                        <th class="py-3 small text-muted fw-600">Period</th>
                                        <th class="py-3 small text-muted fw-600">Amount</th>
                                        <th class="py-3 small text-muted fw-600">Status</th>
                                        <th class="py-3 small text-muted fw-600">Date</th>
                                        <th class="py-3 small text-muted fw-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($gatewayInvoices as $inv):
                                    $st = $inv['status'];
                                    $stColor = $st==='paid' ? '#22c55e' : ($st==='pending' ? '#fbbf24' : '#ef4444');
                                    $stBg    = $st==='paid' ? 'rgba(74,222,128,.12)' : ($st==='pending' ? 'rgba(251,191,36,.12)' : 'rgba(239,68,68,.12)');
                                ?>
                                <tr>
                                    <td class="px-4">
                                        <span class="font-monospace text-muted small">#<?= str_pad($inv['id'],5,'0',STR_PAD_LEFT) ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#0ea5e9,#38bdf8);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.72rem;flex-shrink:0;">
                                                <?= strtoupper(substr($inv['full_names'],0,1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-600 small"><?= htmlspecialchars($inv['full_names']) ?></div>
                                                <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($inv['phone_number']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($inv['gateway_identifier']): ?>
                                        <span class="small">
                                            <i class="material-icons" style="font-size:13px;vertical-align:middle;color:#0ea5e9;"><?= $inv['gateway_type']==='till' ? 'store' : 'account_balance' ?></i>
                                            <?= $inv['gateway_type']==='till' ? 'Till' : 'Paybill' ?>
                                            <span class="font-monospace"><?= htmlspecialchars($inv['gateway_identifier']) ?></span>
                                        </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background:<?= $inv['plan']==='yearly'?'rgba(99,102,241,.12)':'rgba(100,116,139,.1)' ?>;color:<?= $inv['plan']==='yearly'?'#a5b4fc':'#64748b' ?>;border-radius:6px;padding:3px 8px;font-size:.7rem;">
                                            <?= ucfirst($inv['plan']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="small text-muted">
                                            <?= date('M d', strtotime($inv['period_start'])) ?> →
                                            <?= date('M d Y', strtotime($inv['period_end'])) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold small">KES <?= number_format($inv['amount'], 0) ?></td>
                                    <td>
                                        <span class="badge" style="background:<?= $stBg ?>;color:<?= $stColor ?>;border-radius:20px;padding:4px 10px;font-size:.7rem;">
                                            <?= $st==='paid' ? '✓ Paid' : ucfirst($st) ?>
                                        </span>
                                    </td>
                                    <td><span class="small text-muted"><?= date('M d, Y', strtotime($inv['created_at'])) ?></span></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if ($st !== 'paid'): ?>
                                            <form method="POST" onsubmit="return confirm('Mark as paid? This will activate the user\'s gateway.')">
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="type"   value="gateway">
                                                <input type="hidden" name="id"     value="<?= $inv['id'] ?>">
                                                <input type="hidden" name="tab"    value="gateway">
                                                <button type="submit" class="btn btn-sm" title="Mark Paid — activates gateway"
                                                    style="background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.25);color:#22c55e;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">check_circle</i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($st === 'pending'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="mark_overdue">
                                                <input type="hidden" name="type"   value="gateway">
                                                <input type="hidden" name="id"     value="<?= $inv['id'] ?>">
                                                <input type="hidden" name="tab"    value="gateway">
                                                <button type="submit" class="btn btn-sm" title="Mark Overdue"
                                                    style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#f87171;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">warning</i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($st !== 'paid'): ?>
                                            <form method="POST" onsubmit="return confirm('Delete this invoice?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="type"   value="gateway">
                                                <input type="hidden" name="id"     value="<?= $inv['id'] ?>">
                                                <input type="hidden" name="tab"    value="gateway">
                                                <button type="submit" class="btn btn-sm" title="Delete"
                                                    style="background:rgba(100,116,139,.08);border:1px solid rgba(100,116,139,.2);color:#94a3b8;border-radius:7px;padding:3px 8px;">
                                                    <i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($gatewayInvoices)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="material-icons d-block mb-2" style="font-size:40px;opacity:.2;">mobile_friendly</i>
                                        No gateway invoices found.
                                    </td>
                                </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                    </div><!-- /card -->

                </div>
            </main>
            <?php require_once "../../partials/footer.php" ?>
        </div>
    </div>
    <?php require_once "../../partials/scripts.php" ?>
</body>
</html>