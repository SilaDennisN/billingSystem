<?php
session_start();
date_default_timezone_set('Africa/Nairobi');
require_once '../core/db.php';

$email = trim($_POST['email'] ?? '');

if ($email == '') {
    $_SESSION['error'] = "Email is required";
    header("Location: ../auth/reset.php");
    exit;
}

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "Email not found";
    header("Location: ../auth/resetPassword");
    exit;
}

$token = bin2hex(random_bytes(32));
$expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

$pdo->prepare("
INSERT INTO password_resets (email, token, expires_at)
VALUES (?, ?, ?)
")->execute([$email, $token, $expires]);

$reset_link = "http://billing.inovatech.co.ke/auth/new_password.php?token=$token";



/* Send email here */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'infor.inovatech@gmail.com';
    $mail->Password   = 'jlliodosnqhpghnw';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('noreply@inovatech.co.ke', 'Inovatech Billing');
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request – Inovatech';
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset</title>
    </head>
    <body style='margin:0; padding:0; background-color:#f4f6f9; font-family: Arial, sans-serif;'>

        <table width='100%' cellpadding='0' cellspacing='0' style='background-color:#f4f6f9; padding: 40px 0;'>
            <tr>
                <td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);'>

                        <!-- Header -->
                        <tr>
                            <td style='background-color:#1a73e8; padding: 32px 40px; text-align:center;'>
                                <h1 style='margin:0; color:#ffffff; font-size:22px; font-weight:700; letter-spacing:1px;'>INOVATECH</h1>
                                <p style='margin:4px 0 0; color:#d0e4ff; font-size:13px;'>Billing & Account Services</p>
                            </td>
                        </tr>

                        <!-- Body -->
                        <tr>
                            <td style='padding: 40px 40px 32px;'>
                                <h2 style='margin:0 0 12px; color:#1a1a1a; font-size:20px;'>Password Reset Request</h2>
                                <p style='margin:0 0 16px; color:#555555; font-size:15px; line-height:1.6;'>
                                    We received a request to reset the password for your Inovatech account.
                                    If you made this request, click the button below to proceed.
                                </p>
                                <p style='margin:0 0 28px; color:#555555; font-size:15px; line-height:1.6;'>
                                    If you did <strong>not</strong> request a password reset, you can safely ignore this email.
                                    Your password will remain unchanged.
                                </p>

                                <!-- CTA Button -->
                                <table cellpadding='0' cellspacing='0' width='100%'>
                                    <tr>
                                        <td align='center'>
                                            <a href='$reset_link'
                                               style='display:inline-block; background-color:#1a73e8; color:#ffffff;
                                                      text-decoration:none; font-size:15px; font-weight:600;
                                                      padding:14px 36px; border-radius:6px; letter-spacing:0.3px;'>
                                                Reset My Password
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Expiry notice -->
                                <p style='margin:28px 0 0; color:#888888; font-size:13px; text-align:center;'>
                                    ⏱ This link expires in <strong>30 minutes</strong>.
                                </p>

                                <!-- Fallback link -->
                                <div style='margin-top:28px; padding:16px; background-color:#f4f6f9; border-radius:6px;'>
                                    <p style='margin:0 0 6px; color:#888888; font-size:12px;'>
                                        If the button doesn't work, copy and paste this link into your browser:
                                    </p>
                                    <p style='margin:0; word-break:break-all; font-size:12px; color:#1a73e8;'>$reset_link</p>
                                </div>
                            </td>
                        </tr>

                        <!-- Divider -->
                        <tr>
                            <td style='padding: 0 40px;'>
                                <hr style='border:none; border-top:1px solid #eeeeee; margin:0;'>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style='padding: 24px 40px; text-align:center;'>
                                <p style='margin:0 0 4px; color:#aaaaaa; font-size:12px;'>
                                    This email was sent by <strong>Inovatech Billing</strong> · noreply@inovatech.co.ke
                                </p>
                                <p style='margin:0; color:#aaaaaa; font-size:12px;'>
                                    &copy; " . date('Y') . " Inovatech. All rights reserved.
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>

    </body>
    </html>
    ";

    // Plain-text fallback
    $mail->AltBody = "Password Reset Request\n\nWe received a request to reset your Inovatech account password.\n\nReset your password here: $reset_link\n\nThis link expires in 30 minutes.\n\nIf you did not request this, ignore this email.\n\n© " . date('Y') . " Inovatech.";

    $mail->send();

    $_SESSION['success'] = "Password reset link sent to your email. Please check your email.";
    header("Location: ../auth/login");
    exit;
} catch (Exception $e) {
    error_log("Mailer Error: " . $mail->ErrorInfo);
    $_SESSION['error'] = "Could not send email. Please try again later.";
    header("Location: ../auth/reset.php");
    exit;
}
//     $_SESSION['success'] = "Password reset link sent to your email. Please Check your Email.";
//     header("Location: ../auth/login");
//     exit;
// // } catch (Exception $e) {
// //     error_log($mail->ErrorInfo);
// // }
