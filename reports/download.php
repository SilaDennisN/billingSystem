<?php
require_once "../core/db.php";
require_once "../core/auth.php";         // ← gives us $routerIds
require_once "../assets/TCPDF/tcpdf.php";

if (!is_logged_in()) exit("Unauthorized");

// $routerIds is already set by auth.php
/** @var array $routerIds Injected by auth.php */
$routerIds = $routerIds ?? [0]; // satisfies Intelephense + guards against edge case
$routerPlaceholders = implode(',', array_fill(0, count($routerIds), '?'));

/* ── DATE FILTER ─────────────────────────────────────────────────── */
$period   = $_GET['period']    ?? 'month';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

switch ($period) {
    case 'today':
        $from  = date('Y-m-d 00:00:00');
        $to    = date('Y-m-d 23:59:59');
        $label = 'Today — ' . date('d M Y');
        break;
    case 'week':
        $from  = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $to    = date('Y-m-d 23:59:59');
        $label = 'This Week';
        break;
    case 'year':
        $from  = date('Y-01-01 00:00:00');
        $to    = date('Y-12-31 23:59:59');
        $label = 'This Year — ' . date('Y');
        break;
    case 'custom':
        $from  = $dateFrom ? $dateFrom . ' 00:00:00' : date('Y-m-01 00:00:00');
        $to    = $dateTo   ? $dateTo   . ' 23:59:59' : date('Y-m-d 23:59:59');
        $label = date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));
        break;
    default:
        $from  = date('Y-m-01 00:00:00');
        $to    = date('Y-m-d 23:59:59');
        $label = 'This Month — ' . date('F Y');
}

$fromDate = date('Y-m-d', strtotime($from));
$toDate   = date('Y-m-d', strtotime($to));

/* ── QUERIES (all scoped to $routerIds) ──────────────────────────── */

// Revenue
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(amount), 0)                                                  AS total,
        COALESCE(SUM(CASE WHEN user_type='hotspot' THEN amount ELSE 0 END), 0)   AS hotspot,
        COALESCE(SUM(CASE WHEN user_type='pppoe'   THEN amount ELSE 0 END), 0)   AS pppoe,
        COUNT(*)                                                                   AS tx_count
    FROM payments
    WHERE status IN ('used','confirmed')
      AND created_at BETWEEN ? AND ?
      AND router_id IN ($routerPlaceholders)
");
$stmt->execute(array_merge([$from, $to], $routerIds));
$revenue = $stmt->fetch();

// Expenses
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
      AND router_id IN ($routerPlaceholders)
");
$stmt->execute(array_merge([$fromDate, $toDate], $routerIds));
$expenses = $stmt->fetch();

// Users
$stmt = $pdo->prepare("
    SELECT
        COUNT(*)               AS total,
        SUM(user_type='hotspot') AS hotspot,
        SUM(user_type='pppoe')   AS pppoe,
        SUM(status='active')     AS active,
        SUM(status='expired')    AS expired
    FROM hotspot_users
    WHERE router_id IN ($routerPlaceholders)
");
$stmt->execute($routerIds);
$users = $stmt->fetch();

// New users in period
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM hotspot_users
    WHERE created_at BETWEEN ? AND ?
      AND router_id IN ($routerPlaceholders)
");
$stmt->execute(array_merge([$from, $to], $routerIds));
$newUsers = $stmt->fetchColumn();

// Expenses by category
$stmt = $pdo->prepare("
    SELECT category, COALESCE(SUM(amount), 0) AS total
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
      AND router_id IN ($routerPlaceholders)
    GROUP BY category
    ORDER BY total DESC
");
$stmt->execute(array_merge([$fromDate, $toDate], $routerIds));
$expByCategory = $stmt->fetchAll();

// Top plans
$stmt = $pdo->prepare("
    SELECT hp.profile_name, hp.plan_type,
           COUNT(hu.user_id) AS user_count,
           COALESCE(SUM(p.amount), 0) AS revenue
    FROM hotspot_profiles hp
    LEFT JOIN hotspot_users hu
           ON hu.plan_id = hp.id
          AND hu.router_id IN ($routerPlaceholders)
    LEFT JOIN payments p
           ON p.username  = hu.username
          AND p.router_id = hu.router_id
          AND p.status IN ('used','confirmed')
          AND p.created_at BETWEEN ? AND ?
    WHERE hp.router_id IN ($routerPlaceholders)
    GROUP BY hp.id
    ORDER BY revenue DESC
    LIMIT 10
");
$stmt->execute(array_merge($routerIds, [$from, $to], $routerIds));
$topPlans = $stmt->fetchAll();

// Recent payments
$stmt = $pdo->prepare("
    SELECT p.username, p.amount, p.payment_method, p.user_type, p.created_at,
           hp.profile_name
    FROM payments p
    LEFT JOIN hotspot_profiles hp ON hp.id = p.plan_id
    WHERE p.status IN ('used','confirmed')
      AND p.created_at BETWEEN ? AND ?
      AND p.router_id IN ($routerPlaceholders)
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute(array_merge([$from, $to], $routerIds));
$recentPay = $stmt->fetchAll();

$profit = $revenue['total'] - $expenses['total'];
$margin = $revenue['total'] > 0
    ? round(($profit / $revenue['total']) * 100, 1)
    : 0;

/* ── BUILD PDF ───────────────────────────────────────────────────── */
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('Hotspot Billing System');
$pdf->SetAuthor($_SESSION['user']['full_names'] ?? 'Admin');
$pdf->SetTitle('Billing Report — ' . $label);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();

$green  = [0, 172, 105];
$red    = [232, 21,  0];
$blue   = [0,  97,  242];
$grey   = [100, 110, 120];
$light  = [245, 247, 250];

/* ── HEADER ─────────────────────────────────────────────────────── */
$pdf->SetFillColor(...$blue);
$pdf->Rect(0, 0, 210, 30, 'F');

$pdf->SetFont('helvetica', 'B', 18);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(15, 8);
$pdf->Cell(0, 10, 'Billing & Revenue Report', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetXY(15, 19);
$pdf->Cell(0, 6, $label . '   |   Generated: ' . date('d M Y H:i'), 0, 1, 'L');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetY(38);

//* ── SUMMARY SECTION ─────────────────────────────────────────────── */
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(...$light);
$pdf->Cell(0, 7, 'Financial Summary', 0, 1, 'L', true);
$pdf->Ln(2);

$cols = [
    ['label' => 'Total Revenue',  'value' => 'KES ' . number_format($revenue['total'], 2),  'note' => $revenue['tx_count'] . ' transactions'],
    ['label' => 'Total Expenses', 'value' => 'KES ' . number_format($expenses['total'], 2), 'note' => $expenses['count'] . ' entries'],
    ['label' => 'Net Profit',     'value' => 'KES ' . number_format($profit, 2),             'note' => $margin . '% margin'],
    ['label' => 'Active Users',   'value' => (string)$users['active'],                       'note' => 'New this period: ' . $newUsers], // ← was 'note'> (typo)
];

$x0     = 15;
$colW   = 43;
$gap    = 3;
$startY = $pdf->GetY();   // ← capture Y ONCE, before the loop

foreach ($cols as $i => $c) {
    $x = $x0 + $i * ($colW + $gap);

    // Draw box background
    $pdf->SetFillColor(...$light);
    $pdf->Rect($x, $startY, $colW, 20, 'F');

    // Label
    $pdf->SetXY($x + 2, $startY + 2);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(...$grey);
    $pdf->Cell($colW - 4, 4, $c['label'], 0, 0);   // ← 0 = no newline

    // Value
    $pdf->SetXY($x + 2, $startY + 7);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($colW - 4, 6, $c['value'], 0, 0);   // ← 0 = no newline

    // Note
    $pdf->SetXY($x + 2, $startY + 14);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(...$grey);
    $pdf->Cell($colW - 4, 4, $c['note'], 0, 0);    // ← 0 = no newline
}

$pdf->SetY($startY + 24);    // ← advance cursor past all boxes uniformly
$pdf->SetTextColor(0, 0, 0);


/* ── REVENUE BREAKDOWN ───────────────────────────────────────────── */
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(...$light);
$pdf->Cell(0, 7, 'Revenue Breakdown', 0, 1, 'L', true);
$pdf->Ln(1);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(...$blue);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(90, 7, 'Category', 1, 0, 'L', true);
$pdf->Cell(45, 7, 'Amount (KES)', 1, 0, 'R', true);
$pdf->Cell(45, 7, 'Share', 1, 1, 'R', true);

$rows = [
    ['Hotspot Revenue', $revenue['hotspot'],
        $revenue['total'] > 0 ? round(($revenue['hotspot']/$revenue['total'])*100,1).'%' : '—'],
    ['PPPoE Revenue',   $revenue['pppoe'],
        $revenue['total'] > 0 ? round(($revenue['pppoe']/$revenue['total'])*100,1).'%' : '—'],
    ['TOTAL',           $revenue['total'], '100%'],
];
$pdf->SetTextColor(0, 0, 0);
foreach ($rows as $i => $r) {
    $pdf->SetFillColor(...($i%2===0 ? [255,255,255] : $light));
    $pdf->SetFont('helvetica', $i===count($rows)-1?'B':'', 9);
    $pdf->Cell(90, 6, $r[0], 1, 0, 'L', true);
    $pdf->Cell(45, 6, number_format($r[1],2), 1, 0, 'R', true);
    $pdf->Cell(45, 6, $r[2], 1, 1, 'R', true);
}
$pdf->Ln(4);

/* ── EXPENSES BY CATEGORY ────────────────────────────────────────── */
if (!empty($expByCategory)) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(...$light);
    $pdf->Cell(0, 7, 'Expenses by Category', 0, 1, 'L', true);
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(...$red);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(120, 7, 'Category', 1, 0, 'L', true);
    $pdf->Cell(60, 7, 'Amount (KES)', 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);
    foreach ($expByCategory as $i => $cat) {
        $pdf->SetFillColor(...($i%2===0 ? [255,255,255] : $light));
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(120, 6, ucfirst($cat['category']), 1, 0, 'L', true);
        $pdf->Cell(60, 6, number_format($cat['total'],2), 1, 1, 'R', true);
    }
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(...$light);
    $pdf->Cell(120, 6, 'TOTAL EXPENSES', 1, 0, 'L', true);
    $pdf->Cell(60, 6, number_format($expenses['total'],2), 1, 1, 'R', true);
    $pdf->Ln(4);
}

/* ── TOP PLANS ───────────────────────────────────────────────────── */
if (!empty($topPlans)) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(...$light);
    $pdf->Cell(0, 7, 'Top Plans Performance', 0, 1, 'L', true);
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(...$green);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(80, 7, 'Plan Name', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Type', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Users', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Revenue (KES)', 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);
    foreach ($topPlans as $i => $plan) {
        $pdf->SetFillColor(...($i%2===0 ? [255,255,255] : $light));
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(80, 6, $plan['profile_name'], 1, 0, 'L', true);
        $pdf->Cell(30, 6, strtoupper($plan['plan_type']), 1, 0, 'C', true);
        $pdf->Cell(30, 6, $plan['user_count'], 1, 0, 'C', true);
        $pdf->Cell(40, 6, number_format($plan['revenue'],2), 1, 1, 'R', true);
    }
    $pdf->Ln(4);
}

/* ── USER STATS ──────────────────────────────────────────────────── */
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(...$light);
$pdf->Cell(0, 7, 'User Statistics', 0, 1, 'L', true);
$pdf->Ln(1);

$userRows = [
    ['Total Users (all-time)', $users['total']],
    ['Active Users',           $users['active']],
    ['Expired Users',          $users['expired']],
    ['Hotspot Users',          $users['hotspot']],
    ['PPPoE Users',            $users['pppoe']],
    ['New This Period',        $newUsers],
];
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(...$blue);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(120, 7, 'Metric', 1, 0, 'L', true);
$pdf->Cell(60, 7, 'Count', 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);
foreach ($userRows as $i => $row) {
    $pdf->SetFillColor(...($i%2===0 ? [255,255,255] : $light));
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(120, 6, $row[0], 1, 0, 'L', true);
    $pdf->Cell(60, 6, $row[1], 1, 1, 'R', true);
}
$pdf->Ln(4);

/* ── RECENT PAYMENTS ─────────────────────────────────────────────── */
if (!empty($recentPay)) {
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(...$light);
    $pdf->Cell(0, 7, 'Recent Payments (up to 20)', 0, 1, 'L', true);
    $pdf->Ln(1);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(...$blue);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(35, 7, 'Date', 1, 0, 'L', true);
    $pdf->Cell(45, 7, 'Username', 1, 0, 'L', true);
    $pdf->Cell(45, 7, 'Plan', 1, 0, 'L', true);
    $pdf->Cell(20, 7, 'Type', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Amount (KES)', 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);
    foreach ($recentPay as $i => $p) {
        $pdf->SetFillColor(...($i%2===0 ? [255,255,255] : $light));
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(35, 5, date('d M Y H:i', strtotime($p['created_at'])), 1, 0, 'L', true);
        $pdf->Cell(45, 5, $p['username'], 1, 0, 'L', true);
        $pdf->Cell(45, 5, $p['profile_name'] ?? '—', 1, 0, 'L', true);
        $pdf->Cell(20, 5, strtoupper($p['user_type'] ?? ''), 1, 0, 'C', true);
        $pdf->Cell(35, 5, number_format($p['amount'],2), 1, 1, 'R', true);
    }
}

/* ── FOOTER ──────────────────────────────────────────────────────── */
$pdf->SetY(-20);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(...$grey);
$pdf->Cell(0, 5, 'Generated by Hotspot Billing System on ' . date('d M Y H:i') .
    ' | Report Period: ' . $label, 0, 0, 'C');

$filename = 'billing-report-' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');