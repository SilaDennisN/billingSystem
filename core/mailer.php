<?php
/**
 * core/mailer.php
 * 
 * Reusable email sending function for Inovatech.
 * 
 * Usage:
 *   require_once '../core/mailer.php';
 *   send_email($to_email, $to_name, $subject, $html_body, $plain_body);
 * 
 * Returns true on success, false on failure (error is logged).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// ─── SMTP CONFIG ─────────────────────────────────────────────
require_once('app.php');
// ─────────────────────────────────────────────────────────────

/**
 * Send an HTML email.
 *
 * @param string $to_email   Recipient email address
 * @param string $to_name    Recipient display name
 * @param string $subject    Email subject line
 * @param string $html_body  Full HTML body (use build_email_template() below)
 * @param string $plain_body Plain-text fallback
 * @return bool
 */
function send_email(string $to_email, string $to_name, string $subject, string $html_body, string $plain_body = ''): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = $plain_body ?: strip_tags($html_body);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("[Inovatech Mailer] Failed to send to {$to_email} — " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Wraps content in the standard Inovatech branded email shell.
 *
 * @param string $title        Heading shown inside the email body
 * @param string $inner_html   The unique content for this email
 * @return string              Complete HTML email
 */
function build_email_template(string $title, string $inner_html): string
{
    $year = date('Y');
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$title}</title>
    </head>
    <body style='margin:0;padding:0;background-color:#f4f6f9;font-family:Arial,sans-serif;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f4f6f9;padding:40px 0;'>
            <tr><td align='center'>
                <table width='600' cellpadding='0' cellspacing='0'
                       style='background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);'>

                    <!-- Header -->
                    <tr>
                        <td style='background-color:#1651f5;padding:32px 40px;text-align:center;'>
                            <h1 style='margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:1px;'>INOVATECH</h1>
                            <p style='margin:4px 0 0;color:#c0d4ff;font-size:13px;'>Billing &amp; Account Services</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style='padding:40px 40px 32px;'>
                            <h2 style='margin:0 0 20px;color:#1a1a1a;font-size:20px;'>{$title}</h2>
                            {$inner_html}
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style='padding:0 40px;'>
                            <hr style='border:none;border-top:1px solid #eeeeee;margin:0;'>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style='padding:24px 40px;text-align:center;'>
                            <p style='margin:0 0 4px;color:#aaaaaa;font-size:12px;'>
                                Sent by <strong>Inovatech Billing</strong> &middot; noreply@inovatech.co.ke
                            </p>
                            <p style='margin:0;color:#aaaaaa;font-size:12px;'>
                                &copy; {$year} Inovatech. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td></tr>
        </table>
    </body>
    </html>";
}

/**
 * ══════════════════════════════════════════════════════════════
 *  PRE-BUILT EMAIL TEMPLATES
 *  Call these directly — they handle subject, HTML and plain text.
 * ══════════════════════════════════════════════════════════════
 */

/**
 * Invoice ready notification.
 */
function send_invoice_email(string $email, string $name, array $invoice): bool
{
    $invoiceNo   = str_pad($invoice['id'], 5, '0', STR_PAD_LEFT);
    $period      = date('F Y', strtotime($invoice['period_start']));
    $income      = 'KES ' . number_format($invoice['billed_income'], 2);
    $platformFee = 'KES ' . number_format($invoice['platform_fee'], 2);
    $gatewayFee  = $invoice['gateway_fee'] > 0 ? 'KES ' . number_format($invoice['gateway_fee'], 2) : '—';
    $total       = 'KES ' . number_format($invoice['total_amount'], 2);
    $dueDate     = date('M d, Y', strtotime($invoice['period_end'] . ' +7 days'));
    $dashLink    = 'https://billing.inovatech.co.ke/subscriptions/index';

    $inner = "
        <p style='margin:0 0 16px;color:#555555;font-size:15px;line-height:1.6;'>
            Hi <strong>{$name}</strong>, your invoice for <strong>{$period}</strong> is ready.
            Please review the breakdown below and settle your balance before <strong>{$dueDate}</strong>.
        </p>

        <!-- Invoice table -->
        <table width='100%' cellpadding='0' cellspacing='0'
               style='border:1px solid #e8ecf0;border-radius:6px;overflow:hidden;margin-bottom:24px;font-size:14px;'>
            <tr style='background:#f4f6f9;'>
                <td style='padding:10px 16px;color:#888;font-weight:600;text-transform:uppercase;font-size:11px;letter-spacing:.5px;'>Description</td>
                <td style='padding:10px 16px;color:#888;font-weight:600;text-transform:uppercase;font-size:11px;letter-spacing:.5px;text-align:right;'>Amount</td>
            </tr>
            <tr><td style='padding:12px 16px;border-top:1px solid #eee;color:#333;'>Invoice #</td>
                <td style='padding:12px 16px;border-top:1px solid #eee;text-align:right;color:#555;font-family:monospace;'>{$invoiceNo}</td></tr>
            <tr><td style='padding:12px 16px;border-top:1px solid #eee;color:#333;'>Billing Period</td>
                <td style='padding:12px 16px;border-top:1px solid #eee;text-align:right;color:#555;'>{$period}</td></tr>
            <tr><td style='padding:12px 16px;border-top:1px solid #eee;color:#333;'>Total Billed Income</td>
                <td style='padding:12px 16px;border-top:1px solid #eee;text-align:right;color:#555;'>{$income}</td></tr>
            <tr><td style='padding:12px 16px;border-top:1px solid #eee;color:#333;'>Platform Fee (5%)</td>
                <td style='padding:12px 16px;border-top:1px solid #eee;text-align:right;color:#e6a817;font-weight:600;'>{$platformFee}</td></tr>
            <tr><td style='padding:12px 16px;border-top:1px solid #eee;color:#333;'>M-Pesa Gateway Fee</td>
                <td style='padding:12px 16px;border-top:1px solid #eee;text-align:right;color:#555;'>{$gatewayFee}</td></tr>
            <tr style='background:#f4f6f9;'>
                <td style='padding:14px 16px;border-top:2px solid #1651f5;color:#1a1a1a;font-weight:700;font-size:15px;'>Total Due</td>
                <td style='padding:14px 16px;border-top:2px solid #1651f5;text-align:right;color:#1651f5;font-weight:700;font-size:15px;'>{$total}</td>
            </tr>
        </table>

        <p style='margin:0 0 8px;color:#888;font-size:13px;text-align:center;'>
            ⏱ Payment due by <strong>{$dueDate}</strong>. Unpaid invoices may lead to account suspension.
        </p>

        <table cellpadding='0' cellspacing='0' width='100%' style='margin-top:20px;'>
            <tr><td align='center'>
                <a href='{$dashLink}'
                   style='display:inline-block;background-color:#1651f5;color:#ffffff;text-decoration:none;
                          font-size:14px;font-weight:600;padding:13px 32px;border-radius:6px;'>
                    View Invoice in Dashboard
                </a>
            </td></tr>
        </table>";

    $plain = "Hi {$name},\n\nYour invoice #{$invoiceNo} for {$period} is ready.\n\n"
           . "Billed Income : {$income}\n"
           . "Platform Fee  : {$platformFee}\n"
           . "Gateway Fee   : {$gatewayFee}\n"
           . "Total Due     : {$total}\n\n"
           . "Due Date: {$dueDate}\n\n"
           . "View your invoice: {$dashLink}\n\n"
           . "© " . date('Y') . " Inovatech.";

    $html = build_email_template("Your Invoice is Ready — {$period}", $inner);
    return send_email($email, $name, "Invoice Ready – {$period} | Inovatech", $html, $plain);
}

/**
 * Account suspended notification.
 */
function send_suspension_email(string $email, string $name, string $invoiceNo, string $totalDue): bool
{
    $dashLink = 'https://billing.inovatech.co.ke/subscriptions/index';

    $inner = "
        <p style='margin:0 0 16px;color:#555;font-size:15px;line-height:1.6;'>
            Hi <strong>{$name}</strong>, your Inovatech account has been <strong style='color:#dc3545;'>suspended</strong>
            due to an unpaid invoice of <strong>{$totalDue}</strong> (Invoice #{$invoiceNo}).
        </p>
        <div style='background:#fff3f3;border-left:4px solid #dc3545;padding:16px 20px;border-radius:4px;margin-bottom:24px;'>
            <p style='margin:0;color:#dc3545;font-size:14px;font-weight:600;'>
                ⚠ Your routers and hotspot users are currently inaccessible.
            </p>
            <p style='margin:8px 0 0;color:#555;font-size:13px;'>
                Settle your outstanding balance to restore full access immediately.
            </p>
        </div>
        <table cellpadding='0' cellspacing='0' width='100%'>
            <tr><td align='center'>
                <a href='{$dashLink}'
                   style='display:inline-block;background-color:#dc3545;color:#ffffff;text-decoration:none;
                          font-size:14px;font-weight:600;padding:13px 32px;border-radius:6px;'>
                    Pay Now &amp; Restore Access
                </a>
            </td></tr>
        </table>
        <p style='margin:20px 0 0;color:#888;font-size:12px;text-align:center;'>
            Need help? Reply to this email or contact us via WhatsApp.
        </p>";

    $plain = "Hi {$name},\n\nYour account has been suspended due to unpaid invoice #{$invoiceNo} ({$totalDue}).\n\n"
           . "Please settle your balance to restore access: {$dashLink}\n\n"
           . "© " . date('Y') . " Inovatech.";

    $html = build_email_template('Account Suspended — Action Required', $inner);
    return send_email($email, $name, 'Account Suspended – Inovatech', $html, $plain);
}

/**
 * Trial expiry warning (sent at 3 days remaining).
 */
function send_trial_expiry_email(string $email, string $name, string $expiryDate): bool
{
    $dashLink = 'https://billing.inovatech.co.ke/subscriptions/index';

    $inner = "
        <p style='margin:0 0 16px;color:#555;font-size:15px;line-height:1.6;'>
            Hi <strong>{$name}</strong>, your free trial expires on <strong>{$expiryDate}</strong>.
            After that, you'll need an active subscription to continue managing your routers and hotspot users.
        </p>
        <div style='background:#fff8e1;border-left:4px solid #f4a100;padding:16px 20px;border-radius:4px;margin-bottom:24px;'>
            <p style='margin:0;color:#c47f00;font-size:14px;font-weight:600;'>⏱ 3 days left on your trial.</p>
            <p style='margin:8px 0 0;color:#555;font-size:13px;'>
                You pay only 5% of your monthly income. No fixed fees, no surprises.
            </p>
        </div>
        <table cellpadding='0' cellspacing='0' width='100%'>
            <tr><td align='center'>
                <a href='{$dashLink}'
                   style='display:inline-block;background-color:#1651f5;color:#ffffff;text-decoration:none;
                          font-size:14px;font-weight:600;padding:13px 32px;border-radius:6px;'>
                    Activate My Subscription
                </a>
            </td></tr>
        </table>";

    $plain = "Hi {$name},\n\nYour Inovatech free trial expires on {$expiryDate}.\n\n"
           . "Activate your subscription to keep access: {$dashLink}\n\n"
           . "Pricing: 5% of monthly income + optional KES 400 M-Pesa gateway.\n\n"
           . "© " . date('Y') . " Inovatech.";

    $html = build_email_template('Your Free Trial is Ending Soon', $inner);
    return send_email($email, $name, 'Your Trial Expires in 3 Days – Inovatech', $html, $plain);
}

/**
 * Trial expired notification.
 */
function send_trial_expired_email(string $email, string $name): bool
{
    $dashLink = 'https://billing.inovatech.co.ke/subscriptions/index';

    $inner = "
        <p style='margin:0 0 16px;color:#555;font-size:15px;line-height:1.6;'>
            Hi <strong>{$name}</strong>, your 14-day free trial has ended.
            Your routers and hotspot data are safe — subscribe now to restore access.
        </p>
        <table cellpadding='0' cellspacing='0' width='100%'>
            <tr><td align='center'>
                <a href='{$dashLink}'
                   style='display:inline-block;background-color:#1651f5;color:#ffffff;text-decoration:none;
                          font-size:14px;font-weight:600;padding:13px 32px;border-radius:6px;'>
                    Subscribe Now
                </a>
            </td></tr>
        </table>
        <p style='margin:20px 0 0;color:#888;font-size:12px;text-align:center;'>
            5% of monthly income + optional KES 400 gateway. No fixed plans.
        </p>";

    $plain = "Hi {$name},\n\nYour Inovatech free trial has ended. Subscribe to restore access: {$dashLink}\n\n"
           . "© " . date('Y') . " Inovatech.";

    $html = build_email_template('Your Free Trial Has Ended', $inner);
    return send_email($email, $name, 'Trial Ended – Subscribe to Continue | Inovatech', $html, $plain);
}

/**
 * Payment confirmation email — sent immediately after M-Pesa payment is verified.
 */
function send_payment_confirmation_email(string $email, string $name, array $invoice, string $mpesaCode): bool
{
    $invoiceNo  = str_pad($invoice['id'], 5, '0', STR_PAD_LEFT);
    $period     = date('F Y', strtotime($invoice['period_start']));
    $total      = 'KES ' . number_format($invoice['total_amount'], 2);
    $paidAt     = date('M d, Y H:i', strtotime($invoice['paid_at'] ?? 'now'));
    $dashLink   = 'https://billing.inovatech.co.ke/subscriptions/index';

    $inner = "
        <p style='margin:0 0 20px;color:#555;font-size:15px;line-height:1.6;'>
            Hi <strong>{$name}</strong>, we've received your payment. Your subscription is active.
        </p>

        <!-- Green success banner -->
        <div style='background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:20px 24px;margin-bottom:24px;text-align:center;'>
            <div style='font-size:2rem;margin-bottom:6px;'>✅</div>
            <div style='font-family:monospace;font-size:1.1rem;font-weight:700;color:#16a34a;letter-spacing:.05em;'>{$mpesaCode}</div>
            <div style='font-size:12px;color:#555;margin-top:4px;'>M-Pesa Transaction Code</div>
        </div>

        <!-- Receipt table -->
        <table width='100%' cellpadding='0' cellspacing='0'
               style='border:1px solid #e8ecf0;border-radius:6px;overflow:hidden;margin-bottom:24px;font-size:14px;'>
            <tr style='background:#f4f6f9;'>
                <td style='padding:10px 16px;color:#888;font-weight:600;text-transform:uppercase;font-size:11px;letter-spacing:.5px;'>Detail</td>
                <td style='padding:10px 16px;color:#888;font-weight:600;text-transform:uppercase;font-size:11px;letter-spacing:.5px;text-align:right;'>Value</td>
            </tr>
            <tr><td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>Invoice #</td>
                <td style='padding:11px 16px;border-top:1px solid #eee;text-align:right;font-family:monospace;color:#555;'>{$invoiceNo}</td></tr>
            <tr><td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>Billing Period</td>
                <td style='padding:11px 16px;border-top:1px solid #eee;text-align:right;color:#555;'>{$period}</td></tr>
            <tr><td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>Paid At</td>
                <td style='padding:11px 16px;border-top:1px solid #eee;text-align:right;color:#555;'>{$paidAt}</td></tr>
            <tr style='background:#f0fdf4;'>
                <td style='padding:13px 16px;border-top:2px solid #16a34a;color:#1a1a1a;font-weight:700;'>Amount Paid</td>
                <td style='padding:13px 16px;border-top:2px solid #16a34a;text-align:right;color:#16a34a;font-weight:700;font-size:15px;'>{$total}</td>
            </tr>
        </table>

        <table cellpadding='0' cellspacing='0' width='100%'>
            <tr><td align='center'>
                <a href='{$dashLink}'
                   style='display:inline-block;background-color:#1651f5;color:#ffffff;text-decoration:none;
                          font-size:14px;font-weight:600;padding:13px 32px;border-radius:6px;'>
                    Go to Dashboard
                </a>
            </td></tr>
        </table>
        <p style='margin:16px 0 0;color:#aaa;font-size:12px;text-align:center;'>
            Keep this email as your receipt. Thank you for using Inovatech.
        </p>";

    $plain = "Hi {$name},\n\nPayment confirmed!\n\n"
           . "M-Pesa Code  : {$mpesaCode}\n"
           . "Invoice #    : {$invoiceNo}\n"
           . "Period       : {$period}\n"
           . "Amount Paid  : {$total}\n"
           . "Paid At      : {$paidAt}\n\n"
           . "Your subscription is now active.\n"
           . "Dashboard: {$dashLink}\n\n"
           . "© " . date('Y') . " Inovatech.";

    $html = build_email_template('Payment Confirmed ✅', $inner);
    return send_email($email, $name, "Payment Confirmed – {$period} | Inovatech", $html, $plain);
}

/**
 * Email address verification — sent immediately after account registration.
 *
 * @param string $email      Recipient email address
 * @param string $name       Recipient full name
 * @param string $raw_token  The raw (unhashed) token to embed in the link
 * @return bool
 */
function send_verification_email(string $email, string $name, string $raw_token): bool
{
    $verify_link = 'https://billing.inovatech.co.ke/auth/verify_email.php?token=' . urlencode($raw_token);
    $expires_in  = '24 hours';

    $inner = "
        <p style='margin:0 0 16px;color:#555555;font-size:15px;line-height:1.6;'>
            Hi <strong>{$name}</strong>, welcome to Inovatech!<br>
            Please confirm your email address to activate your account.
        </p>

        <!-- Verification CTA -->
        <table cellpadding='0' cellspacing='0' width='100%' style='margin-bottom:28px;'>
            <tr><td align='center'>
                <a href='{$verify_link}'
                   style='display:inline-block;background-color:#1651f5;color:#ffffff;text-decoration:none;
                          font-size:15px;font-weight:700;padding:15px 36px;border-radius:8px;
                          letter-spacing:.3px;'>
                    ✅ Verify My Email Address
                </a>
            </td></tr>
        </table>

        <!-- Expiry notice -->
        <div style='background:#f4f6f9;border-left:4px solid #1651f5;padding:14px 18px;
                    border-radius:4px;margin-bottom:24px;'>
            <p style='margin:0;color:#555;font-size:13px;line-height:1.6;'>
                ⏱ This link is valid for <strong>{$expires_in}</strong>. 
                If it expires, you can request a new one from the login page.
            </p>
        </div>

        <!-- Fallback link -->
        <p style='margin:0 0 6px;color:#888;font-size:12px;'>
            If the button doesn't work, copy and paste this URL into your browser:
        </p>
        <p style='margin:0;word-break:break-all;'>
            <a href='{$verify_link}'
               style='color:#1651f5;font-size:12px;font-family:monospace;'>{$verify_link}</a>
        </p>

        <hr style='border:none;border-top:1px solid #eee;margin:28px 0 20px;'>
        <p style='margin:0;color:#aaa;font-size:12px;'>
            If you did not create an Inovatech account, you can safely ignore this email.
            No action is required on your part.
        </p>";

    $plain = "Hi {$name},\n\n"
           . "Welcome to Inovatech! Please verify your email address by visiting the link below:\n\n"
           . "{$verify_link}\n\n"
           . "This link expires in {$expires_in}.\n\n"
           . "If you did not register, please ignore this email.\n\n"
           . "© " . date('Y') . " Inovatech.";

    $html = build_email_template('Verify Your Email Address', $inner);
    return send_email($email, $name, 'Verify Your Email – Inovatech', $html, $plain);
}


/**
 * PPPoE credentials email — sent when a new PPPoE user is created.
 *
 * @param string $email         Customer email address
 * @param string $username      PPPoE username
 * @param string $password      PPPoE password (plaintext for delivery)
 * @param string $plan_name     Plan / profile name
 * @param string $router_name   Router the user is on
 * @param string $expires_at    Expiry datetime string
 * @param float|null $install_fee  Installation fee paid (null = not recorded)
 * @param string|null $invoice_no  Invoice number if one was created
 * @return bool
 */
/**
 * PPPoE credentials + first invoice breakdown email.
 */
function send_pppoe_credentials_email(
    string  $email,
    string  $username,
    string  $password,
    string  $plan_name,
    string  $router_name,
    string  $expires_at,
    ?float  $install_fee    = null,
    ?string $invoice_no     = null,
    float   $prorated       = 0.00,
    float   $plan_price     = 0.00,
    int     $days_remaining = 0,
    int     $days_in_month  = 0
): bool {

    $expireFormatted = date('F j, Y \a\t H:i', strtotime($expires_at));
    $total           = $prorated + ($install_fee ?? 0.00);

    // ── Invoice breakdown rows ────────────────────────────────────────────────
    $breakdownRows = '';

    if ($plan_price > 0) {
        $breakdownRows .= "
            <tr>
                <td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>
                    Monthly Plan
                </td>
                <td style='padding:11px 16px;border-top:1px solid #eee;
                           text-align:right;color:#555;'>
                    KES " . number_format($plan_price, 2) . "
                </td>
            </tr>
            <tr>
                <td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>
                    Prorated ({$days_remaining} of {$days_in_month} days)
                </td>
                <td style='padding:11px 16px;border-top:1px solid #eee;
                           text-align:right;color:#555;'>
                    KES " . number_format($prorated, 2) . "
                </td>
            </tr>";
    }

    if ($install_fee !== null && $install_fee > 0) {
        $breakdownRows .= "
            <tr>
                <td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>
                    Installation Fee
                </td>
                <td style='padding:11px 16px;border-top:1px solid #eee;
                           text-align:right;color:#555;'>
                    KES " . number_format($install_fee, 2) . "
                </td>
            </tr>";
    }

    if ($invoice_no) {
        $breakdownRows .= "
            <tr>
                <td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>
                    Invoice #
                </td>
                <td style='padding:11px 16px;border-top:1px solid #eee;
                           text-align:right;font-family:monospace;color:#555;'>
                    {$invoice_no}
                </td>
            </tr>";
    }

    $inner = "
        <p style='margin:0 0 20px;color:#555;font-size:15px;line-height:1.6;'>
            Hi <strong>{$username}</strong>, your PPPoE internet account is ready.
            Use the credentials below to connect.
        </p>

        <!-- Credentials box -->
        <div style='background:#f0f4ff;border:1px solid #c7d7fd;border-radius:8px;
                    padding:20px 24px;margin-bottom:24px;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='font-size:15px;'>
                <tr>
                    <td style='color:#555;padding-bottom:12px;width:35%;'>Username</td>
                    <td style='font-family:monospace;font-weight:700;color:#1651f5;
                               font-size:17px;padding-bottom:12px;'>
                        {$username}
                    </td>
                </tr>
                <tr>
                    <td style='color:#555;'>Password</td>
                    <td style='font-family:monospace;font-weight:700;
                               color:#1651f5;font-size:17px;'>
                        {$password}
                    </td>
                </tr>
            </table>
        </div>

        <!-- Account details -->
        <table width='100%' cellpadding='0' cellspacing='0'
               style='border:1px solid #e8ecf0;border-radius:6px;overflow:hidden;
                      margin-bottom:24px;font-size:14px;'>
            <tr style='background:#f4f6f9;'>
                <td style='padding:10px 16px;color:#888;font-weight:600;
                           text-transform:uppercase;font-size:11px;letter-spacing:.5px;'>
                    Detail
                </td>
                <td style='padding:10px 16px;color:#888;font-weight:600;
                           text-transform:uppercase;font-size:11px;letter-spacing:.5px;
                           text-align:right;'>
                    Value
                </td>
            </tr>
            <tr>
                <td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>Plan</td>
                <td style='padding:11px 16px;border-top:1px solid #eee;
                           text-align:right;color:#555;'>{$plan_name}</td>
            </tr>
            <tr>
                <td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>Router</td>
                <td style='padding:11px 16px;border-top:1px solid #eee;
                           text-align:right;color:#555;'>{$router_name}</td>
            </tr>
            <tr>
                <td style='padding:11px 16px;border-top:1px solid #eee;color:#333;'>
                    Valid Until
                </td>
                <td style='padding:11px 16px;border-top:1px solid #eee;
                           text-align:right;color:#555;'>{$expireFormatted}</td>
            </tr>
        </table>

        <!-- Invoice breakdown -->
        <p style='margin:0 0 8px;color:#1a1a1a;font-weight:700;font-size:15px;'>
            First Invoice Breakdown
        </p>
        <table width='100%' cellpadding='0' cellspacing='0'
               style='border:1px solid #e8ecf0;border-radius:6px;overflow:hidden;
                      margin-bottom:24px;font-size:14px;'>
            <tr style='background:#f4f6f9;'>
                <td style='padding:10px 16px;color:#888;font-weight:600;
                           text-transform:uppercase;font-size:11px;letter-spacing:.5px;'>
                    Item
                </td>
                <td style='padding:10px 16px;color:#888;font-weight:600;
                           text-transform:uppercase;font-size:11px;letter-spacing:.5px;
                           text-align:right;'>
                    Amount
                </td>
            </tr>
            {$breakdownRows}
            <tr style='background:#f4f6f9;'>
                <td style='padding:13px 16px;border-top:2px solid #1651f5;
                           color:#1a1a1a;font-weight:700;font-size:15px;'>
                    Total Due
                </td>
                <td style='padding:13px 16px;border-top:2px solid #1651f5;
                           text-align:right;color:#1651f5;font-weight:700;font-size:15px;'>
                    KES " . number_format($total, 2) . "
                </td>
            </tr>
        </table>

        <!-- Warning -->
        <div style='background:#fffbeb;border-left:4px solid #f59e0b;
                    padding:14px 18px;border-radius:4px;margin-bottom:8px;'>
            <p style='margin:0;color:#92400e;font-size:13px;line-height:1.6;'>
                ⏳ Your invoice is <strong>unpaid</strong>. Please settle 
                <strong>KES " . number_format($total, 2) . "</strong> 
                before <strong>" . date('F j, Y', strtotime($expires_at)) . "</strong>
                to avoid disconnection.
            </p>
        </div>

        <div style='background:#f0fdf4;border-left:4px solid #16a34a;
                    padding:14px 18px;border-radius:4px;margin-top:16px;'>
            <p style='margin:0;color:#14532d;font-size:13px;line-height:1.6;'>
                🔒 Keep your credentials safe. Do not share them with anyone.
                Contact us immediately if you suspect unauthorised access.
            </p>
        </div>";

    // ── Plain text fallback ───────────────────────────────────────────────────
    $plain = "Your PPPoE Internet Credentials\n"
           . "================================\n\n"
           . "Username    : {$username}\n"
           . "Password    : {$password}\n"
           . "Plan        : {$plan_name}\n"
           . "Router      : {$router_name}\n"
           . "Valid Until : {$expireFormatted}\n\n"
           . "── First Invoice ──\n";

    if ($plan_price > 0) {
        $plain .= "Monthly Plan  : KES " . number_format($plan_price, 2) . "\n"
               .  "Prorated ({$days_remaining}/{$days_in_month} days): KES " . number_format($prorated, 2) . "\n";
    }
    if ($install_fee !== null && $install_fee > 0) {
        $plain .= "Install Fee   : KES " . number_format($install_fee, 2) . "\n";
    }
    if ($invoice_no) {
        $plain .= "Invoice #     : {$invoice_no}\n";
    }

    $plain .= "Total Due     : KES " . number_format($total, 2) . "\n\n"
           .  "Please pay before " . date('F j, Y', strtotime($expires_at)) . " to avoid disconnection.\n\n"
           .  "© " . date('Y') . " Inovatech.";

    $html = build_email_template('Your PPPoE Internet Credentials 🌐', $inner);
    return send_email($email, $username, 'Your Internet Login Credentials – Inovatech', $html, $plain);
}