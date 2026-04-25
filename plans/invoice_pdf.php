<?php
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../assets/TCPDF/tcpdf.php";

if (!is_logged_in()) exit;

$id = $_GET['id'] ?? null;
if (!$id) exit("Invalid invoice");

// Fetch invoice
$stmt = $pdo->prepare("
    SELECT i.*, 
           hu.username,
           hp.profile_name,
           r.name AS router_name
    FROM invoices i
    JOIN hotspot_users hu ON hu.user_id = i.user_id
    JOIN hotspot_profiles hp ON hp.id = i.plan_id
    JOIN routers r ON r.router_id = i.router_id
    WHERE i.invoice_id = ?
");
$stmt->execute([$id]);
$inv = $stmt->fetch();

if (!$inv) exit("Invoice not found");

// ─── Custom TCPDF class with header/footer ───────────────────────────────────
class InvoicePDF extends TCPDF {

    public $inv;

    public function Header() {
        // Blue header bar
        $this->SetFillColor(30, 90, 168);
        $this->Rect(0, 0, 210, 38, 'F');

        // Company name (white)
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 20);
        $this->SetXY(12, 8);
        $this->Cell(100, 10, 'InovaTech', 0, 1, 'L');

        $this->SetFont('helvetica', '', 9);
        $this->SetXY(12, 19);
        $this->Cell(100, 5, 'Main Street, Malili Town  |  Phone: 0740 770 212  |  info@inovatech.co.ke', 0, 1, 'L');

        // INVOICE label (right side)
        $this->SetFont('helvetica', 'B', 26);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(120, 7);
        $this->Cell(78, 12, 'INVOICE', 0, 1, 'R');

        // Invoice # and date
        $this->SetFont('helvetica', '', 9);
        $this->SetXY(120, 20);
        $this->Cell(78, 5, 'Invoice #: ' . $this->inv['invoice_number'], 0, 1, 'R');
        $this->SetXY(120, 25);
        $this->Cell(78, 5, 'Date: ' . date("d M Y", strtotime($this->inv['created_at'])), 0, 1, 'R');

        // Reset text color
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer() {
        $this->SetY(-18);
        $this->SetFillColor(30, 90, 168);
        $this->Rect(0, $this->GetY(), 210, 20, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 6, 'Thank you for choosing InovaTech. Payment is due within 7 days of invoice date.', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

// ─── Determine status colours ─────────────────────────────────────────────────
$statusColors = [
    'paid'    => ['bg' => [39, 174, 96],  'text' => 'PAID'],
    'unpaid'  => ['bg' => [243, 156, 18], 'text' => 'UNPAID'],
    'overdue' => ['bg' => [192, 57, 43],  'text' => 'OVERDUE'],
];
$statusKey   = strtolower($inv['status']);
$statusColor = $statusColors[$statusKey] ?? ['bg' => [100, 100, 100], 'text' => strtoupper($inv['status'])];

// ─── Create PDF ───────────────────────────────────────────────────────────────
$pdf = new InvoicePDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->inv = $inv;

$pdf->SetCreator('InovaTech Billing System');
$pdf->SetAuthor('InovaTech');
$pdf->SetTitle('Invoice ' . $inv['invoice_number']);

$pdf->SetMargins(12, 44, 12);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(18);
$pdf->SetAutoPageBreak(true, 24);

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// ─── Bill To / From boxes ─────────────────────────────────────────────────────
$y = $pdf->GetY() + 4;

// "Bill To" box
$pdf->SetFillColor(245, 247, 250);
$pdf->SetDrawColor(200, 210, 225);
$pdf->RoundedRect(12, $y, 88, 38, 3, '1111', 'DF');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(30, 90, 168);
$pdf->SetXY(17, $y + 4);
$pdf->Cell(78, 6, 'BILL TO', 0, 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(30, 30, 30);
$pdf->SetX(17);
$pdf->Cell(78, 6, htmlspecialchars($inv['username']), 0, 1);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetX(17);
$pdf->Cell(78, 5, 'Router: ' . htmlspecialchars($inv['router_name']), 0, 1);
$pdf->SetX(17);
$pdf->Cell(78, 5, 'Plan: ' . htmlspecialchars($inv['profile_name']), 0, 1);

// "Payment Details" box
$pdf->SetFillColor(245, 247, 250);
$pdf->RoundedRect(112, $y, 88, 38, 3, '1111', 'DF');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(30, 90, 168);
$pdf->SetXY(117, $y + 4);
$pdf->Cell(78, 6, 'PAYMENT DETAILS', 0, 1);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetX(117);
$pdf->Cell(38, 5, 'Invoice Date:', 0, 0);
$pdf->Cell(40, 5, date("d M Y", strtotime($inv['created_at'])), 0, 1);

$pdf->SetX(117);
$pdf->Cell(38, 5, 'Due Date:', 0, 0);
$dueDate = date("d M Y", strtotime($inv['created_at'] . ' +7 days'));
$pdf->Cell(40, 5, $dueDate, 0, 1);

$pdf->SetX(117);
$pdf->Cell(38, 5, 'Amount Due:', 0, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(40, 5, 'KES ' . number_format($inv['amount'], 2), 0, 1);

// ─── Items Table ──────────────────────────────────────────────────────────────
$pdf->SetTextColor(0, 0, 0);
$tableY = $y + 46;
$pdf->SetY($tableY);

// Table header
$pdf->SetFillColor(30, 90, 168);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetDrawColor(30, 90, 168);
$pdf->SetLineWidth(0.1);

$pdf->Cell(85, 8, 'DESCRIPTION', 1, 0, 'L', true);
$pdf->Cell(55, 8, 'BILLING PERIOD', 1, 0, 'C', true);
$pdf->Cell(46, 8, 'AMOUNT (KES)', 1, 1, 'R', true);

// Table row
$pdf->SetFillColor(252, 252, 255);
$pdf->SetTextColor(40, 40, 40);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetDrawColor(210, 218, 230);

$period = date("d M Y", strtotime($inv['period_start'])) . ' – ' . date("d M Y", strtotime($inv['period_end']));
$pdf->Cell(85, 8, htmlspecialchars($inv['profile_name']) . ' Plan', 1, 0, 'L', true);
$pdf->Cell(55, 8, $period, 1, 0, 'C', true);
$pdf->Cell(46, 8, number_format($inv['amount'], 2), 1, 1, 'R', true);

// Subtotal / Total rows
$pdf->SetFillColor(245, 247, 252);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(85, 7, '', 0, 0, 'L');
$pdf->Cell(55, 7, 'Subtotal', 1, 0, 'R', true);
$pdf->Cell(46, 7, number_format($inv['amount'], 2), 1, 1, 'R', true);

$pdf->Cell(85, 7, '', 0, 0, 'L');
$pdf->Cell(55, 7, 'Tax (0%)', 1, 0, 'R', true);
$pdf->Cell(46, 7, '0.00', 1, 1, 'R', true);

// Total row (highlighted)
$pdf->SetFillColor(30, 90, 168);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(85, 9, '', 0, 0, 'L');
$pdf->Cell(55, 9, 'TOTAL DUE', 1, 0, 'R', true);
$pdf->Cell(46, 9, 'KES ' . number_format($inv['amount'], 2), 1, 1, 'R', true);

// ─── Status Badge ─────────────────────────────────────────────────────────────
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);
$statusY = $pdf->GetY();

$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(12, $statusY);
$pdf->Cell(30, 7, 'Payment Status:', 0, 0, 'L');

// Coloured badge
$pdf->SetFillColor(...$statusColor['bg']);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->RoundedRect($pdf->GetX(), $statusY, 26, 7, 2, '1111', 'F');
$pdf->SetXY($pdf->GetX(), $statusY);
$pdf->Cell(26, 7, $statusColor['text'], 0, 1, 'C');

// ─── Notes ───────────────────────────────────────────────────────────────────
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(6);
$noteY = $pdf->GetY();

$pdf->SetFillColor(255, 249, 230);
$pdf->SetDrawColor(240, 190, 60);
$pdf->RoundedRect(12, $noteY, 186, 20, 3, '1111', 'DF');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(150, 100, 0);
$pdf->SetXY(17, $noteY + 3);
$pdf->Cell(176, 5, 'NOTE', 0, 1);

$pdf->SetFont('helvetica', '', 8.5);
$pdf->SetTextColor(80, 60, 0);
$pdf->SetX(17);
$pdf->MultiCell(176, 5, "Please make payment within 7 days of invoice date. Late payments may incur a penalty.\nFor queries contact us at info@inovatech.co.ke or call 0740 770 212.", 0, 'L');

// ─── Output ───────────────────────────────────────────────────────────────────
$pdf->Output('Invoice_' . $inv['invoice_number'] . '.pdf', 'D');