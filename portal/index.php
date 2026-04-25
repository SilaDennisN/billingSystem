<?php
date_default_timezone_set('Africa/Nairobi');
session_start();

require_once "../core/db.php";
require_once "../core/router.php";
require_once "error_handler.php";

use RouterOS\Query;

/* =========================
   Validate hotspot access
   Accepts GET (from login.html JS redirect) or existing session
========================= */

$input = !empty($_GET['mac']) ? $_GET : $_POST;

// If fresh vars arriving, store them in session
if (!empty($input['mac']) && !empty($input['link-login-only'])) {
  $_SESSION['mac']             = $input['mac'];
  $_SESSION['ip']              = $input['ip'] ?? null;
  $_SESSION['link-login']      = $input['link-login'] ?? null;
  $_SESSION['link-login-only'] = $input['link-login-only'];
  $_SESSION['link-orig']       = $input['link-orig'] ?? 'http://google.com';
  $_SESSION['chap-id']         = $input['chap-id'] ?? null;
  $_SESSION['chap-challenge']  = $input['chap-challenge'] ?? null;

  if (!empty($input['router_id'])) {
    $_SESSION['router_id'] = $input['router_id'];
  }
}

if (empty($_SESSION['mac']) || empty($_SESSION['link-login-only'])) {
  showError(
    'access',
    'Invalid Hotspot Access',
    'It looks like you didn\'t connect through the hotspot login page. Please connect to our WiFi network and try again.',
    [
      ['text' => '📶 Connect to WiFi', 'link' => 'wifi://'],
      ['text' => '🔄 Refresh Page', 'link' => 'javascript:location.reload()']
    ]
  );
}

$mac             = $_SESSION['mac'];
$link_login_only = $_SESSION['link-login-only'];
$router_id       = $_SESSION['router_id'];
$linkorig = $_SESSION['link-orig'];

/* =========================
   Load router from router_id
========================= */
try {
  $stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id = ?");
  $stmt->execute([$router_id]);
  $router = $stmt->fetch();

  if (!$router) {
    showError(
      'database',
      'Router Not Recognized',
      'The router configuration could not be found. Contact the administrator.',
      [
        ['text' => '🔄 Try Again', 'link' => 'javascript:location.reload()'],
        ['text' => '📞 Contact Support', 'link' => 'mailto:support@inovatech.com']
      ]
    );
  }
} catch (Exception $e) {
  showError(
    'database',
    'Database Connection Failed',
    'We couldn\'t retrieve router info. ' . $e->getMessage(),
    [
      ['text' => '🔄 Retry', 'link' => 'javascript:location.reload()']
    ]
  );
}

try {
  $stmt = $pdo->prepare("
        SELECT u.phone_number
        FROM user_router_access ura
        JOIN users u ON u.user_id = ura.user_id
        WHERE ura.router_id = ?
        LIMIT 1
    ");
  $stmt->execute([$router_id]);
  $owner = $stmt->fetch();

  $support_phone = $owner['phone_number'] ?? null;
} catch (Exception $e) {
  $support_phone = null;
}

/* =========================
   Connect to MikroTik
========================= */
try {
  $client = router_connect($router['router_id']);
  if (!$client) throw new Exception("Cannot connect to router");
} catch (Exception $e) {
  showError(
    'network',
    'Router Connection Failed',
    'Could not connect to router: ' . $e->getMessage(),
    [
      ['text' => '🔄 Retry', 'link' => 'javascript:location.reload()']
    ]
  );
}

/* =========================
   Build username
========================= */
$username = 'mac_' . str_replace(':', '', $mac);
$password = '123456';

/* =========================
   Check if user exists
========================= */
try {
  $query = new Query('/ip/hotspot/user/print');
  $query->where('name', $username);
  $user = $client->query($query)->read();
} catch (Exception $e) {
  showError(
    'network',
    'Unable to Check User Status',
    'We encountered an error while checking your account status. Please try refreshing the page.',
    [
      ['text' => '🔄 Refresh', 'link' => 'javascript:location.reload()'],
      ['text' => '← Back', 'link' => 'javascript:history.back()']
    ]
  );
}

/* =========================
   If user exists, check time
========================= */
if (!empty($user)) {
  $limit = $user[0]['limit-uptime'] ?? null;
  $used  = $user[0]['uptime'] ?? '0s';

  if ($limit && $used !== $limit) {
    $link_orig = $_SESSION['link-orig'] ?? 'http://google.com';
?>
    <!DOCTYPE html>
    <html>

    <head>
      <meta charset="UTF-8">
      <title>Connecting…</title>
      <style>
        body {
          background: #0a0a14;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          font-family: sans-serif;
          color: #fff;
          margin: 0;
        }

        p {
          opacity: .6;
          font-size: 14px;
        }
      </style>
    </head>

    <body>
      <p>Reconnecting your session…</p>
      <form id="f" method="post" action="<?= htmlspecialchars($link_login_only) ?>">
        <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
        <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
        <input type="hidden" name="dst" value="<?= htmlspecialchars($link_orig) ?>">
        <input type="hidden" name="popup" value="true">
      </form>
      <script>
        document.getElementById('f').submit();
      </script>
    </body>

    </html>
<?php
    exit;
  }

  try {
    $client->query(
      (new Query('/ip/hotspot/user/remove'))
        ->equal('name', $username)
    )->read();
  } catch (Exception $e) {
    // Silent fail - continue to show plans
  }
}

/* =========================
   Load available plans
========================= */
try {
  $stmt = $pdo->prepare("
        SELECT * FROM hotspot_profiles
        WHERE router_id=? 
        AND plan_type='hotspot'
        ORDER BY CAST(price AS UNSIGNED) ASC;
    ");
  $stmt->execute([$router['router_id']]);
  $plans = $stmt->fetchAll();

  if (empty($plans)) {
    showError(
      'general',
      'No Plans Available',
      'There are currently no internet packages available for purchase. Please contact the administrator or try again later.',
      [
        ['text' => '🔄 Refresh', 'link' => 'javascript:location.reload()'],
        ['text' => '📞 Contact Admin', 'link' => 'mailto:support@inovatech.com']
      ]
    );
  }
} catch (Exception $e) {
  showError(
    'database',
    'Unable to Load Plans',
    'We couldn\'t retrieve the available internet packages. Please try refreshing the page.',
    [
      ['text' => '🔄 Refresh Page', 'link' => 'javascript:location.reload()'],
      ['text' => '← Go Back', 'link' => 'javascript:history.back()']
    ]
  );
}

function formatPhone($phone)
{
  if (!$phone) return null;

  // Convert 07XXXXXXXX → 2547XXXXXXXX
  if (preg_match('/^0(7|1)\d{8}$/', $phone)) {
    return '254' . substr($phone, 1);
  }

  // Already in 254 format
  return $phone;
}

$formatted_phone = formatPhone($support_phone);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Select Package</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --bg: #f7f3ec;
      --bg2: #f0ebe1;
      --surface: #fdfaf5;
      --border: rgba(120, 90, 50, 0.12);
      --border-md: rgba(120, 90, 50, 0.2);
      --accent: #2a7d4f;
      --accent-dk: #1f6040;
      --accent-lt: rgba(42, 125, 79, 0.1);
      --text: #1e1a14;
      --text2: #4a3f30;
      --muted: #8c7b65;
      --danger: #c0392b;
      --danger-lt: rgba(192, 57, 43, 0.08);
      --warning: #d97706;
      --warm1: #e8dfd0;
      --warm2: #d4c9b8;
      --shadow-sm: 0 2px 8px rgba(100, 70, 30, 0.07);
      --shadow-md: 0 4px 20px rgba(100, 70, 30, 0.1);
      --shadow-lg: 0 12px 40px rgba(100, 70, 30, 0.13);
    }

    body {
      font-family: 'Outfit', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 0 16px 0;
      padding-top: 78px;
      /* support-bar ~37px + ticker ~41px */
      padding-bottom: 68px;
      /* fixed footer height */
    }

    body::after {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='300' height='300' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
    }

    body::before {
      content: '';
      position: fixed;
      top: 60px;
      right: -80px;
      width: 340px;
      height: 340px;
      background: radial-gradient(ellipse, rgba(42, 125, 79, 0.07) 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
      border-radius: 50%;
    }

    /* ── SUPPORT BAR ── */
    .support-bar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 200;
      background: var(--surface);
      border-bottom: 1px solid var(--border-md);
      box-shadow: 0 1px 12px rgba(100, 70, 30, 0.08);
      padding: 10px 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      font-size: 13px;
      color: var(--muted);
    }

    .support-bar svg {
      width: 13px;
      height: 13px;
      fill: var(--accent);
      flex-shrink: 0;
    }

    .support-bar a {
      color: var(--accent);
      font-weight: 600;
      text-decoration: none;
    }

    .support-bar a:hover {
      text-decoration: underline;
    }

    /* ── PROMO TICKER ── */
    .promo-ticker {
      position: fixed;
      top: 37px;
      left: 0;
      right: 0;
      z-index: 199;
      background: var(--accent);
      overflow: hidden;
      height: 41px;
      display: flex;
      align-items: center;
      border-bottom: 1px solid var(--accent-dk);
      box-shadow: 0 2px 10px rgba(42, 125, 79, 0.25);
    }

    .ticker-track {
      display: flex;
      align-items: center;
      white-space: nowrap;
      animation: ticker-scroll 28s linear infinite;
      will-change: transform;
    }

    .ticker-track:hover {
      animation-play-state: paused;
    }

    .ticker-item {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 0 40px;
      font-size: 12.5px;
      font-weight: 500;
      color: rgba(255, 255, 255, 0.95);
      letter-spacing: 0.1px;
    }

    .ticker-item strong {
      font-weight: 700;
      color: #fff;
    }

    .ticker-dot {
      width: 4px;
      height: 4px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.4);
      flex-shrink: 0;
    }

    .ticker-icon {
      font-size: 13px;
    }

    @keyframes ticker-scroll {
      0% {
        transform: translateX(0);
      }

      100% {
        transform: translateX(-50%);
      }
    }

    .wrapper {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 440px;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    /* ── HEADER ── */
    .header {
      text-align: center;
      padding: 44px 0 32px;
      animation: fadeDown 0.6s ease both;
    }

    .logo-ring {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      border: 1.5px solid var(--border-md);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 18px;
      background: var(--surface);
      box-shadow: var(--shadow-md), inset 0 1px 0 rgba(255, 255, 255, 0.8);
      position: relative;
    }

    .logo-ring::before {
      content: '';
      position: absolute;
      inset: -7px;
      border-radius: 50%;
      border: 1px solid rgba(42, 125, 79, 0.12);
    }

    .logo-ring svg {
      width: 26px;
      height: 26px;
      fill: var(--accent);
    }

    .header h2 {
      font-family: 'Playfair Display', serif;
      font-size: 28px;
      font-weight: 700;
      color: var(--text);
      letter-spacing: -0.3px;
      margin: 0;
    }

    .subtitle {
      margin-top: 6px;
      color: var(--muted);
      font-size: 14px;
      font-weight: 300;
    }

    .section-label {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 14px;
    }

    /* ── PLAN CARDS ── */
    .plans {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .plan {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 17px 18px;
      cursor: pointer;
      transition: all 0.22s ease;
      animation: fadeUp 0.5s ease both;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }

    .plan::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(42, 125, 79, 0.04) 0%, transparent 60%);
      opacity: 0;
      transition: opacity 0.2s;
    }

    .plan:hover {
      border-color: rgba(42, 125, 79, 0.3);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .plan:hover::before {
      opacity: 1;
    }

    .plan:active {
      transform: translateY(0);
    }

    .plan:nth-child(1) {
      animation-delay: 0.05s;
    }

    .plan:nth-child(2) {
      animation-delay: 0.12s;
    }

    .plan:nth-child(3) {
      animation-delay: 0.19s;
    }

    .plan:nth-child(4) {
      animation-delay: 0.26s;
    }

    .plan:nth-child(5) {
      animation-delay: 0.33s;
    }

    .plan-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .plan-left {
      display: flex;
      align-items: center;
      gap: 13px;
    }

    .plan-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--accent);
      flex-shrink: 0;
      box-shadow: 0 0 8px rgba(42, 125, 79, 0.35);
    }

    .plan h3 {
      font-family: 'Playfair Display', serif;
      font-size: 16px;
      font-weight: 700;
      color: var(--text);
      line-height: 1.2;
      margin: 0;
    }

    .plan-badges {
      display: flex;
      gap: 6px;
      margin-top: 5px;
      flex-wrap: wrap;
    }

    .plan-badge {
      background: var(--bg2);
      border: 1px solid var(--border-md);
      border-radius: 6px;
      padding: 2px 8px;
      font-size: 11px;
      color: var(--muted);
      font-weight: 500;
    }

    .price {
      font-family: 'Playfair Display', serif;
      font-size: 21px;
      font-weight: 700;
      color: var(--accent);
      white-space: nowrap;
      text-align: right;
      line-height: 1;
      margin: 0;
    }

    .price-kes {
      font-family: 'Outfit', sans-serif;
      font-size: 11px;
      font-weight: 400;
      color: var(--muted);
      display: block;
      margin-top: 2px;
    }

    .validity {
      display: none;
    }

    .plan>button {
      display: none;
    }

    .plan-arrow {
      color: var(--muted);
      font-size: 20px;
      transition: transform 0.2s, color 0.2s;
    }

    .plan:hover .plan-arrow {
      transform: translateX(3px);
      color: var(--accent);
    }

    /* ═══════════════════════════════
   FOOTER — fixed bar
═══════════════════════════════ */
    .site-footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 200;
      background: var(--surface);
      border-top: 1px solid var(--border-md);
      box-shadow: 0 -4px 24px rgba(100, 70, 30, 0.09);
      padding: 0 20px env(safe-area-inset-bottom, 10px);
      height: 68px;
      display: flex;
      align-items: center;
    }

    .footer-inner {
      width: 100%;
      max-width: 440px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    /* Brand cluster */
    .footer-brand {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }

    .footer-brand-icon {
      width: 30px;
      height: 30px;
      border-radius: 9px;
      background: var(--accent-lt);
      border: 1px solid rgba(42, 125, 79, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .footer-brand-icon svg {
      width: 14px;
      height: 14px;
      fill: var(--accent);
    }

    .footer-brand-name {
      font-family: 'Playfair Display', serif;
      font-size: 12.5px;
      font-weight: 700;
      color: var(--text2);
      line-height: 1.2;
    }

    .footer-brand-tag {
      font-size: 10px;
      color: var(--muted);
      font-weight: 400;
    }

    /* Centre copyright — hides on very small screens */
    .footer-copy {
      font-size: 10.5px;
      color: var(--muted);
      font-weight: 300;
      text-align: center;
      line-height: 1.5;
      flex: 1;
    }

    .footer-copy strong {
      font-weight: 500;
    }

    @media (max-width: 360px) {
      .footer-copy {
        display: none;
      }
    }

    /* Contact pill — right side */
    .footer-contact {
      display: flex;
      align-items: center;
      gap: 6px;
      background: var(--accent);
      border: none;
      border-radius: 999px;
      padding: 7px 13px;
      box-shadow: 0 3px 14px rgba(42, 125, 79, 0.3);
      text-decoration: none;
      flex-shrink: 0;
      transition: background 0.18s, transform 0.18s, box-shadow 0.18s;
    }

    .footer-contact:hover {
      background: var(--accent-dk);
      transform: translateY(-1px);
      box-shadow: 0 5px 18px rgba(42, 125, 79, 0.38);
    }

    .footer-contact:active {
      transform: translateY(0);
    }

    .footer-contact svg {
      width: 12px;
      height: 12px;
      fill: #fff;
      flex-shrink: 0;
    }

    .footer-contact span {
      font-size: 12px;
      color: #fff;
      font-weight: 600;
      letter-spacing: 0.2px;
    }

    /* ═══════════════════════════════
   MODAL
═══════════════════════════════ */
    .modal {
      position: fixed;
      inset: 0;
      z-index: 300;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
      background: rgba(30, 20, 10, 0.45);
      backdrop-filter: blur(5px);
    }

    .modal.active {
      opacity: 1;
      pointer-events: all;
    }

    .modal-overlay {
      position: absolute;
      inset: 0;
      z-index: 0;
    }

    .modal-content {
      position: relative;
      z-index: 1;
      background: var(--surface);
      border: 1px solid var(--border-md);
      border-top: 2px solid rgba(42, 125, 79, 0.2);
      border-radius: 24px 24px 0 0;
      width: 100%;
      max-width: 480px;
      padding: 0 0 env(safe-area-inset-bottom, 16px);
      transform: translateY(40px);
      transition: transform 0.35s cubic-bezier(0.34, 1.26, 0.64, 1);
      max-height: 92vh;
      overflow-y: auto;
      box-shadow: 0 -8px 40px rgba(100, 70, 30, 0.12);
    }

    .modal.active .modal-content {
      transform: translateY(0);
    }

    .modal-handle {
      width: 36px;
      height: 4px;
      border-radius: 2px;
      background: var(--warm2);
      margin: 14px auto 0;
    }

    .modal-close {
      position: absolute;
      top: 14px;
      right: 20px;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: var(--bg2);
      border: 1px solid var(--border-md);
      color: var(--muted);
      font-size: 15px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      z-index: 2;
    }

    .modal-close:hover {
      color: var(--text);
      border-color: var(--accent);
    }

    .modal-inner {
      padding: 12px 24px 28px;
    }

    .modal-title {
      font-family: 'Playfair Display', serif;
      font-size: 18px;
      font-weight: 700;
      color: var(--text);
      padding: 16px 24px 0;
    }

    .order-card {
      background: var(--bg2);
      border: 1px solid var(--border-md);
      border-radius: 14px;
      padding: 15px 16px;
      margin: 14px 24px 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .order-left .order-label {
      font-size: 10px;
      color: var(--muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 3px;
    }

    #modalPlanName {
      font-family: 'Playfair Display', serif;
      font-size: 16px;
      font-weight: 700;
      color: var(--text);
      margin: 0;
    }

    .modal-subtitle {
      font-size: 12px;
      color: var(--muted);
      margin-top: 2px;
    }

    .order-right {
      text-align: right;
    }

    #modalAmount {
      font-family: 'Playfair Display', serif;
      font-size: 24px;
      font-weight: 700;
      color: var(--accent);
      line-height: 1;
      margin: 0;
    }

    .amount-note {
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px;
    }

    .form-area {
      padding: 16px 24px 0;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      font-size: 11px;
      font-weight: 600;
      color: var(--text2);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
      display: block;
    }

    .form-group small {
      font-size: 12px;
      color: var(--muted);
      margin-top: 6px;
      display: block;
    }

    .phone-wrap {
      display: flex;
      background: var(--bg);
      border: 1.5px solid var(--border-md);
      border-radius: 13px;
      overflow: hidden;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .phone-wrap:focus-within {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-lt);
    }

    .phone-prefix {
      padding: 14px 13px;
      background: var(--warm1);
      color: var(--accent);
      font-size: 14px;
      font-weight: 600;
      border-right: 1px solid var(--border-md);
      white-space: nowrap;
    }

    #phoneInput {
      flex: 1;
      background: transparent;
      border: none;
      outline: none;
      color: var(--text);
      font-family: 'Outfit', sans-serif;
      font-size: 16px;
      padding: 14px 13px;
      letter-spacing: 1px;
      width: 100%;
    }

    #phoneInput::placeholder {
      color: var(--muted);
      letter-spacing: normal;
      font-size: 14px;
    }

    .btn-pay {
      width: 100%;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 13px;
      font-family: 'Outfit', sans-serif;
      font-size: 15px;
      font-weight: 600;
      padding: 15px;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 4px 20px rgba(42, 125, 79, 0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-pay:hover {
      background: var(--accent-dk);
      transform: translateY(-1px);
    }

    .btn-pay svg {
      width: 17px;
      height: 17px;
      fill: #fff;
    }

    .mpesa-note {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 12px;
      color: var(--muted);
      margin-top: 12px;
      padding-bottom: 4px;
    }

    .mpesa-note svg {
      width: 13px;
      height: 13px;
      fill: var(--muted);
    }

    /* ── Loading ── */
    #loadingState .loading-container {
      text-align: center;
      padding: 36px 24px;
    }

    #loadingState h3 {
      font-family: 'Playfair Display', serif;
      font-size: 18px;
      color: var(--text);
      margin: 18px 0 8px;
    }

    #loadingState .loading-text {
      font-size: 13px;
      color: var(--muted);
    }

    .spinner {
      width: 44px;
      height: 44px;
      margin: 0 auto;
      border: 3px solid var(--warm2);
      border-top: 3px solid var(--accent);
      border-radius: 50%;
      animation: spin 0.9s linear infinite;
    }

    /* ── STK Waiting ── */
    #stkWaiting .stk-container {
      text-align: center;
      padding: 12px 24px 24px;
    }

    .arc-wrap {
      position: relative;
      width: 104px;
      height: 104px;
      margin: 0 auto 10px;
    }

    .arc-svg {
      position: absolute;
      inset: 0;
      width: 104px;
      height: 104px;
      overflow: visible;
    }

    .arc-track {
      fill: none;
      stroke: var(--warm2);
      stroke-width: 3.5;
    }

    .arc-fill {
      fill: none;
      stroke: var(--accent);
      stroke-width: 3.5;
      stroke-linecap: round;
      stroke-dasharray: 238;
      stroke-dashoffset: 0;
      transform: rotate(-90deg);
      transform-origin: 52px 52px;
      transition: stroke-dashoffset 1s linear, stroke 0.5s ease;
    }

    .arc-fill.warn {
      stroke: var(--warning);
    }

    .arc-fill.danger {
      stroke: var(--danger);
    }

    .arc-inner {
      position: absolute;
      inset: 15px;
      border-radius: 50%;
      background: var(--surface);
      border: 1px solid var(--border-md);
      box-shadow: var(--shadow-sm);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .arc-time {
      font-family: 'Playfair Display', serif;
      font-size: 21px;
      font-weight: 700;
      color: var(--text);
      line-height: 1;
      transition: color 0.5s;
    }

    .arc-label {
      font-size: 9px;
      color: var(--muted);
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-top: 2px;
    }

    .waiting-dots {
      display: flex;
      gap: 5px;
      justify-content: center;
      margin: 10px 0 14px;
    }

    .waiting-dots span {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--warm2);
      animation: dotBeat 1.5s ease-in-out infinite;
      display: inline-block;
    }

    .waiting-dots span:nth-child(2) {
      animation-delay: 0.25s;
    }

    .waiting-dots span:nth-child(3) {
      animation-delay: 0.5s;
    }

    @keyframes dotBeat {

      0%,
      80%,
      100% {
        transform: scale(1);
        background: var(--warm2);
      }

      40% {
        transform: scale(1.6);
        background: var(--accent);
      }
    }

    #stkWaiting h3 {
      font-family: 'Playfair Display', serif;
      font-size: 18px;
      color: var(--text);
      margin-bottom: 6px;
    }

    .stk-text {
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 4px;
    }

    .stk-subtext {
      font-size: 12px;
      color: var(--muted);
      margin-top: 8px;
    }

    .steps-list {
      display: flex;
      flex-direction: column;
      margin: 14px 0;
      text-align: left;
    }

    .step-row {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid var(--border);
    }

    .step-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }

    .sdot-num {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      flex-shrink: 0;
      margin-top: 1px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: 700;
    }

    .s-done {
      background: var(--accent-lt);
      color: var(--accent);
    }

    .s-active {
      background: rgba(42, 125, 79, 0.15);
      color: var(--accent);
      animation: stepRing 1.5s ease infinite;
    }

    .s-wait {
      background: var(--warm1);
      color: var(--muted);
    }

    @keyframes stepRing {

      0%,
      100% {
        box-shadow: 0 0 0 0 rgba(42, 125, 79, 0.4);
      }

      50% {
        box-shadow: 0 0 0 5px rgba(42, 125, 79, 0);
      }
    }

    .step-label {
      font-size: 13px;
      color: var(--text);
      font-weight: 500;
      line-height: 1.3;
    }

    .step-hint {
      font-size: 11px;
      color: var(--muted);
      margin-top: 1px;
    }

    .phone-animation,
    .pulse-ring {
      display: none;
    }

    /* ── Success ── */
    #successState .success-container {
      text-align: center;
      padding: 12px 24px 28px;
    }

    .confetti {
      font-size: 28px;
      margin-bottom: 10px;
      animation: bounceIn 0.5s ease both;
    }

    .success-icon {
      width: 66px;
      height: 66px;
      border-radius: 50%;
      background: var(--accent-lt);
      border: 2px solid rgba(42, 125, 79, 0.35);
      margin: 0 auto 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    }

    .success-icon svg {
      width: 28px;
      height: 28px;
      fill: var(--accent);
    }

    #successState h3 {
      font-family: 'Playfair Display', serif;
      font-size: 19px;
      color: var(--text);
      margin-bottom: 6px;
    }

    #successState p {
      font-size: 13px;
      color: var(--muted);
    }

    /* ── Error ── */
    #errorState .error-container {
      text-align: center;
      padding: 12px 24px 28px;
    }

    .error-icon {
      width: 66px;
      height: 66px;
      border-radius: 50%;
      background: var(--danger-lt);
      border: 1.5px solid rgba(192, 57, 43, 0.3);
      margin: 0 auto 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    }

    .error-icon svg {
      width: 26px;
      height: 26px;
      fill: var(--danger);
    }

    #errorState h3 {
      font-family: 'Playfair Display', serif;
      font-size: 19px;
      color: var(--text);
      margin-bottom: 6px;
    }

    #errorMessage {
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 18px;
    }

    .btn-retry {
      width: 100%;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 13px;
      font-family: 'Outfit', sans-serif;
      font-size: 14px;
      font-weight: 600;
      padding: 13px;
      cursor: pointer;
      box-shadow: 0 4px 20px rgba(42, 125, 79, 0.3);
      transition: all 0.2s;
    }

    .btn-retry:hover {
      background: var(--accent-dk);
    }

    @keyframes fadeDown {
      from {
        opacity: 0;
        transform: translateY(-14px);
      }

      to {
        opacity: 1;
        transform: none;
      }
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(14px);
      }

      to {
        opacity: 1;
        transform: none;
      }
    }

    @keyframes spin {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    @keyframes popIn {
      from {
        transform: scale(0.4);
        opacity: 0;
      }

      to {
        transform: scale(1);
        opacity: 1;
      }
    }

    @keyframes bounceIn {
      0% {
        transform: scale(0);
      }

      60% {
        transform: scale(1.2);
      }

      100% {
        transform: scale(1);
      }
    }
  </style>
</head>

<body>

  <div class="support-bar">
    <svg viewBox="0 0 24 24">
      <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
    </svg>
    Need help? Call <?php if ($formatted_phone): ?>
      <a href="tel:+<?= $formatted_phone ?>">
        <?= htmlspecialchars($support_phone) ?>
      </a>
    <?php else: ?>
      <a href="mailto:support@inovatech.com">Contact Support</a>
    <?php endif; ?>
  </div>

  <!-- ── PROMO TICKER ── -->
  <?php if ($router_id == 1): ?>
    <div class="promo-ticker" aria-label="Promotional offer">
      <div class="ticker-track">
        <!-- Segment 1 -->
        <span class="ticker-item">
          <span class="ticker-icon">📶</span>
          WiFi Installation — <strong>Router Included</strong>
        </span>
        <span class="ticker-dot"></span>
        <span class="ticker-item">
          <span class="ticker-icon">📅</span>
          Monthly subscription from <strong>KES 1,500</strong>
        </span>
        <span class="ticker-dot"></span>
        <span class="ticker-item">
          <span class="ticker-icon">📞</span>
          Call <strong>0701 625 882</strong> to get connected today
        </span>
        <span class="ticker-dot"></span>
        <!-- Segment 2 — duplicate for seamless loop -->
        <span class="ticker-item">
          <span class="ticker-icon">📶</span>
          WiFi Installation — <strong>Router Included</strong>
        </span>
        <span class="ticker-dot"></span>
        <span class="ticker-item">
          <span class="ticker-icon">📅</span>
          Monthly subscription from <strong>KES 1,500</strong>
        </span>
        <span class="ticker-dot"></span>
        <span class="ticker-item">
          <span class="ticker-icon">📞</span>
          Call <strong>0701 625 882</strong> to get connected today
        </span>
        <span class="ticker-dot"></span>
      </div>
    </div>
  <?php endif; ?>
  <div class="wrapper">

    <div class="header">
      <h2>Get Connected</h2>
      <p class="subtitle">Choose a package and pay via M-Pesa</p>
    </div>

    <div class="section-label">Available Packages</div>

    <div class="plans">
      <?php foreach ($plans as $plan): ?>
        <div class="plan" onclick="openPaymentModal(<?= htmlspecialchars(json_encode($plan)) ?>, <?= $router['router_id'] ?>)">
          <div class="plan-row">
            <div class="plan-left">
              <div class="plan-dot"></div>
              <div>
                <h3><?= htmlspecialchars($plan['profile_name']) ?></h3>
                <div class="plan-badges">
                  <?php if ($plan['validity_hours']): ?>
                    <span class="plan-badge">⏱ <?= $plan['validity_hours'] ?> hour<?= $plan['validity_hours'] > 1 ? 's' : '' ?></span>
                  <?php elseif ($plan['validity_days']): ?>
                    <span class="plan-badge">⏱ <?= $plan['validity_days'] ?> day<?= $plan['validity_days'] > 1 ? 's' : '' ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <div>
                <p class="price">KES <?= number_format($plan['price'], 2) ?></p>
                <span class="price-kes">KES</span>
              </div>
              <div class="plan-arrow">›</div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div><!-- /wrapper -->

  <!-- ═══════════════════════ FOOTER ═══════════════════════ -->
  <footer class="site-footer">
    <div class="footer-inner">

      <!-- Brand -->
      <div class="footer-brand">
        <div class="footer-brand-icon">
          <svg viewBox="0 0 24 24">
            <path d="M1.4 8.56C5.01 4.95 9.95 3 12 3s6.99 1.95 10.6 5.56l-2.1 2.1C17.6 7.7 14.95 6 12 6S6.4 7.7 3.5 10.66l-2.1-2.1zM12 11c-1.65 0-3.15.67-4.24 1.76L12 17l4.24-5.24A5.97 5.97 0 0012 11zM7.05 9.05C8.61 7.49 10.7 6.5 13 6.5c2.3 0 4.39.99 5.95 2.55l-2.12 2.12A4.97 4.97 0 0012 9.5c-1.4 0-2.67.58-3.58 1.5L7.05 9.05z" />
          </svg>
        </div>
        <div>
          <div class="footer-brand-name">Inovatech</div>
          <div class="footer-brand-tag">Hotspot Portal</div>
        </div>
      </div>

      <!-- Copyright -->
      <p class="footer-copy">
        &copy; <?= date('Y') ?> <strong>Inovatech Systems</strong><br>All rights reserved
      </p>

      <?php if ($formatted_phone): ?>
        <a href="tel:+<?= $formatted_phone ?>" class="footer-contact">
          <svg viewBox="0 0 24 24">
            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
          </svg>
          <span><?= htmlspecialchars($support_phone) ?></span>
        </a>
      <?php else: ?>
        <a href="mailto:support@inovatech.com" class="footer-contact">
          <span>Contact</span>
        <?php endif; ?>

    </div>
  </footer>

  <!-- ═══════════════════════ PAYMENT MODAL ═══════════════════════ -->
  <div id="paymentModal" class="modal">
    <div class="modal-overlay" onclick="closePaymentModal()"></div>
    <div class="modal-content">
      <div class="modal-handle"></div>
      <button class="modal-close" onclick="closePaymentModal()">✕</button>

      <!-- ── Screen: Payment form ── -->
      <div id="paymentForm">
        <div class="modal-title">Complete Purchase</div>

        <div class="order-card">
          <div class="order-left">
            <div class="order-label">Package</div>
            <p id="modalPlanName">—</p>
            <p class="modal-subtitle" id="modalDuration"></p>
          </div>
          <div class="order-right">
            <p id="modalAmount">—</p>
            <div class="amount-note">incl. tax</div>
          </div>
        </div>

        <div class="form-area">
          <form id="stkForm" onsubmit="sendSTK(event)">
            <div class="form-group">
              <label for="phoneInput">M-Pesa Phone Number</label>
              <div class="phone-wrap">
                <div class="phone-prefix">+254</div>
                <input
                  type="tel"
                  id="phoneInput"
                  name="phone"
                  placeholder="07XXXXXXXX or 2547XXXXXXXX"
                  value="07"
                  required
                  pattern="^(07|01|2547|2541)[0-9]{8}$">
              </div>
              <small>Enter your Safaricom number</small>
            </div>

            <button type="submit" class="btn-pay" id="payBtn">
              <svg viewBox="0 0 24 24">
                <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" />
              </svg>
              <span>Send Payment Request</span>
            </button>
          </form>

          <div class="mpesa-note">
            <svg viewBox="0 0 24 24">
              <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 6h2v2h-2V7zm0 4h2v6h-2v-6z" />
            </svg>
            You'll receive an M-Pesa prompt on your phone
          </div>
        </div>
      </div>

      <!-- ── Screen: Loading ── -->
      <div id="loadingState" style="display: none;">
        <div class="loading-container">
          <div class="spinner"></div>
          <h3>Processing Payment…</h3>
          <p class="loading-text">Initiating M-Pesa request</p>
        </div>
      </div>

      <!-- ── Screen: STK Waiting ── -->
      <div id="stkWaiting" style="display: none;">
        <div class="stk-container">

          <div class="arc-wrap">
            <svg class="arc-svg" viewBox="0 0 104 104">
              <circle class="arc-track" cx="52" cy="52" r="38" />
              <circle class="arc-fill" cx="52" cy="52" r="38" id="arcFill" />
            </svg>
            <div class="arc-inner">
              <div class="arc-time" id="arcCountdown">1:00</div>
              <div class="arc-label">left</div>
            </div>
          </div>

          <div class="waiting-dots">
            <span></span><span></span><span></span>
          </div>

          <h3>Check your phone</h3>
          <p class="stk-text">An M-Pesa STK push has been sent — enter your PIN to pay.</p>

          <div class="steps-list">
            <div class="step-row">
              <div class="sdot-num s-done">✓</div>
              <div>
                <div class="step-label">STK push sent</div>
                <div class="step-hint">Prompt delivered to your Safaricom number</div>
              </div>
            </div>
            <div class="step-row">
              <div class="sdot-num s-active">2</div>
              <div>
                <div class="step-label">Waiting for your PIN</div>
                <div class="step-hint" id="tickerMsg">Open M-Pesa and enter your PIN…</div>
              </div>
            </div>
            <div class="step-row">
              <div class="sdot-num s-wait">3</div>
              <div>
                <div class="step-label" style="color:var(--muted)">Session activation</div>
                <div class="step-hint">Happens automatically after payment</div>
              </div>
            </div>
          </div>

          <p class="stk-subtext">Waiting for payment confirmation…</p>

          <div class="phone-animation">
            <div class="phone-icon">📱</div>
            <div class="pulse-ring"></div>
          </div>
        </div>
      </div>

      <!-- ── Screen: Success ── -->
      <div id="successState" style="display: none;">
        <div class="success-container">
          <div class="confetti">🎉</div>
          <div class="success-icon">
            <svg viewBox="0 0 24 24">
              <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
            </svg>
          </div>
          <h3>Payment Successful!</h3>
          <p>Connecting you to the internet…</p>
        </div>
      </div>

      <!-- ── Screen: Error ── */-->
      <div id="errorState" style="display: none;">
        <div class="error-container">
          <div class="error-icon">
            <svg viewBox="0 0 24 24">
              <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
            </svg>
          </div>
          <h3>Payment Failed</h3>
          <p id="errorMessage"></p>
          <button onclick="resetModal()" class="btn-retry">Try Again</button>
        </div>
      </div>

    </div><!-- /modal-content -->
  </div><!-- /modal -->

  <script>
    let selectedPlan = null;
    let selectedRouterId = null;
    let paymentToken = null;
    let checkInterval = null;

    const TOTAL_SECS = 60;
    const ARC_CIRC = 2 * Math.PI * 38;
    const HINTS = [
      'Open M-Pesa and enter your PIN…',
      'Still waiting — check your phone…',
      'Confirming with M-Pesa servers…',
      'Almost there, hang tight…',
    ];
    let arcTimer = null,
      arcElapsed = 0;

    function startArc() {
      arcElapsed = 0;
      const arc = document.getElementById('arcFill');
      const timeEl = document.getElementById('arcCountdown');
      arc.className = 'arc-fill';
      arc.style.strokeDashoffset = '0';
      timeEl.style.color = 'var(--text)';
      timeEl.textContent = '1:00';
      arcTimer = setInterval(() => {
        arcElapsed++;
        const rem = TOTAL_SECS - arcElapsed;
        if (rem <= 0) {
          clearInterval(arcTimer);
          return;
        }
        const m = Math.floor(rem / 60),
          s = rem % 60;
        timeEl.textContent = m + ':' + String(s).padStart(2, '0');
        arc.style.strokeDashoffset = ((arcElapsed / TOTAL_SECS) * ARC_CIRC).toFixed(2);
        if (rem <= 15) {
          arc.className = 'arc-fill danger';
          timeEl.style.color = 'var(--danger)';
        } else if (rem <= 30) {
          arc.className = 'arc-fill warn';
          timeEl.style.color = 'var(--warning)';
        }
        document.getElementById('tickerMsg').textContent = HINTS[Math.floor(arcElapsed / 15) % HINTS.length];
      }, 1000);
    }

    function stopArc() {
      clearInterval(arcTimer);
    }

    function openPaymentModal(plan, routerId) {
      selectedPlan = plan;
      selectedRouterId = routerId;

      document.getElementById('modalPlanName').textContent = plan.profile_name;
      document.getElementById('modalAmount').textContent = `KES ${parseFloat(plan.price).toFixed(2)}`;

      let dur = '';
      if (plan.validity_hours) dur = plan.validity_hours + ' hour' + (plan.validity_hours > 1 ? 's' : '');
      else if (plan.validity_days) dur = plan.validity_days + ' day' + (plan.validity_days > 1 ? 's' : '');
      document.getElementById('modalDuration').textContent = dur;

      document.getElementById('paymentModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closePaymentModal() {
      document.getElementById('paymentModal').classList.remove('active');
      document.body.style.overflow = '';
      resetModal();
      stopArc();
      if (checkInterval) {
        clearInterval(checkInterval);
      }
    }

    function resetModal() {
      document.getElementById('paymentForm').style.display = 'block';
      document.getElementById('loadingState').style.display = 'none';
      document.getElementById('stkWaiting').style.display = 'none';
      document.getElementById('successState').style.display = 'none';
      document.getElementById('errorState').style.display = 'none';
      document.getElementById('stkForm').reset();
    }

    async function sendSTK(event) {
      event.preventDefault();

      const phone = document.getElementById('phoneInput').value;
      const payBtn = document.getElementById('payBtn');

      document.getElementById('paymentForm').style.display = 'none';
      document.getElementById('loadingState').style.display = 'block';

      try {
        const initiateResponse = await fetch('initiate_payment.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `router_id=${selectedRouterId}&plan_id=${selectedPlan.id}`
        });

        const initiateData = await initiateResponse.text();

        await new Promise(resolve => setTimeout(resolve, 800));

        const stkResponse = await fetch('pay.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `phone=${encodeURIComponent(phone)}`
        });

        const stkResult = await stkResponse.text();

        if (stkResult.includes('Failed') || stkResult.includes('Invalid') || stkResult.includes('expired')) {
          throw new Error(stkResult);
        }

        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('stkWaiting').style.display = 'block';
        startArc();

        startPaymentCheck();

      } catch (error) {
        console.error('Payment error:', error);
        showError(error.message || 'Failed to send payment request. Please try again.');
      }
    }

    function startPaymentCheck() {
      checkInterval = setInterval(async () => {
        try {
          const response = await fetch('payment_status.php');
          const status = await response.text();

          if (status === 'ACTIVE') {
            clearInterval(checkInterval);
            stopArc();
            document.getElementById('stkWaiting').style.display = 'none';
            document.getElementById('successState').style.display = 'block';
            setTimeout(() => {
              window.location.href = 'create.php';
            }, 2000);
          } else if (status.includes('FAILED') || status.includes('CANCELLED')) {
            clearInterval(checkInterval);
            stopArc();
            showError('Payment was cancelled or failed. Please try again.');
          }
        } catch (error) {
          console.error('Status check error:', error);
        }
      }, 3000);
    }

    function showError(message) {
      document.getElementById('loadingState').style.display = 'none';
      document.getElementById('stkWaiting').style.display = 'none';
      document.getElementById('errorState').style.display = 'block';
      document.getElementById('errorMessage').textContent = message;
    }
  </script>

</body>

</html>