<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../assets/tcpdf/tcpdf.php";

if (!is_logged_in()) exit;

/* Load data */
$revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status='used' || status= 'Confirmed'")->fetchColumn();
$expenses = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn();
$users = $pdo->query("SELECT COUNT(*) FROM hotspot_users")->fetchColumn();

/* Create PDF */
$pdf = new TCPDF();
$pdf->SetCreator('Inovatech Billing');
$pdf->SetAuthor('Inovatech');
$pdf->SetTitle('System Report');
$pdf->AddPage();

$html = "
<h2>Inovatech Billing Report</h2>
<hr>
<p><strong>Total Revenue:</strong> KES ".number_format($revenue,2)."</p>
<p><strong>Total Expenses:</strong> KES ".number_format($expenses,2)."</p>
<p><strong>Net Profit:</strong> KES ".number_format($revenue-$expenses,2)."</p>

<h3>Users</h3>
<p>Total Users: $users</p>

<p style='font-size:10px;color:#777'>
Generated on ".date('d M Y H:i')."
</p>
";

$pdf->writeHTML($html);
$pdf->Output('billing-report.pdf', 'D');
