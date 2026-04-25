<?php
date_default_timezone_set('Africa/Nairobi');
session_start();
require_once "../core/db.php";

// ── Validate session token ───────────────────────────────────
$token = $_SESSION['payment_token'] ?? null;
if (!$token) {
    http_response_code(403);
    exit("Session expired");
}

// ── Load payment (must be 'used' = webhook confirmed it) ─────
$stmt = $pdo->prepare("
    SELECT p.*, hp.profile_name
    FROM payments p
    LEFT JOIN hotspot_profiles hp ON hp.id = p.plan_id
    WHERE p.payment_token = ?
    AND   p.status = 'used'
    LIMIT 1
");
$stmt->execute([$token]);
$payment = $stmt->fetch();

if (!$payment) {
    http_response_code(403);
    // Payment not confirmed yet — tell the JS poller to keep waiting
    exit("Payment not activated yet");
}

// ── Credentials ──────────────────────────────────────────────
// These MUST match exactly what webhooks.php wrote to the router
$username         = $payment['username'];   // mac_AABBCCDDEEFF
$password         = '123456';               // same hardcoded value as webhooks.php

// ── MikroTik captive portal links (saved in session by login.html) ──
$link_login_only  = $_SESSION['link-login-only'] ?? null;
$link_orig        = $_SESSION['link-orig']        ?? 'http://google.com';

if (!$link_login_only) {
    exit("Login link missing — please reconnect to the WiFi network.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connecting…</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --brand:  #0a0a14;
    --accent: #00c6ff;
    --accent2:#0072ff;
    --glass:  rgba(255,255,255,0.10);
    --gb:     rgba(255,255,255,0.18);
    --text:   #ffffff;
    --muted:  rgba(255,255,255,0.55);
}

body {
    font-family: 'Sora', sans-serif;
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    background: var(--brand);
    overflow: hidden;
}

body::before {
    content: ''; position: fixed; inset: 0;
    background:
        radial-gradient(ellipse 80% 60% at 20% 20%, rgba(0,114,255,0.22) 0%, transparent 60%),
        radial-gradient(ellipse 60% 80% at 80% 80%, rgba(0,198,255,0.15) 0%, transparent 60%);
    z-index: 0;
}

.orb { position: fixed; border-radius: 50%; filter: blur(70px); opacity: .15; z-index: 0; animation: floatOrb linear infinite; }
.orb-1 { width:320px;height:320px;background:var(--accent2);top:-100px;left:-100px;animation-duration:20s; }
.orb-2 { width:220px;height:220px;background:var(--accent);bottom:-80px;right:-80px;animation-duration:16s;animation-delay:-8s; }
@keyframes floatOrb { 0%,100%{transform:translate(0,0);}50%{transform:translate(30px,-30px);} }

.card {
    position: relative; z-index: 1;
    width: 100%; max-width: 340px; margin: 20px;
    background: var(--glass);
    border: 1px solid var(--gb);
    border-radius: 28px;
    padding: 44px 32px 36px;
    backdrop-filter: blur(28px);
    -webkit-backdrop-filter: blur(28px);
    box-shadow: 0 8px 64px rgba(0,0,0,.5), 0 1px 0 rgba(255,255,255,.07) inset;
    text-align: center;
    animation: cardIn .6s cubic-bezier(.22,1,.36,1) both;
}
@keyframes cardIn { from{opacity:0;transform:translateY(28px) scale(.97);}to{opacity:1;transform:none;} }

.icon-wrap { width:90px;height:90px;margin:0 auto 28px;position:relative; }
.icon-ring {
    position:absolute;inset:0;border-radius:50%;
    border:2px solid transparent;
    background:linear-gradient(var(--brand),var(--brand)) padding-box,
               linear-gradient(135deg,var(--accent2),var(--accent)) border-box;
    animation:spinRing 2s linear infinite;
}
.icon-ring-2 { position:absolute;inset:6px;border-radius:50%;border:1.5px solid rgba(255,255,255,.08);animation:spinRing 4s linear infinite reverse; }
@keyframes spinRing { to{transform:rotate(360deg);} }
.icon-inner {
    position:absolute;inset:12px;border-radius:50%;
    background:linear-gradient(135deg,var(--accent2),var(--accent));
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 24px rgba(0,114,255,.5);
}
.icon-inner svg { width:28px;height:28px;fill:white; }

.pulse-dots { display:flex;justify-content:center;gap:6px;margin-bottom:20px; }
.dot {
    width:7px;height:7px;border-radius:50%;
    background:var(--accent);opacity:.3;
    animation:pulseDot 1.4s ease-in-out infinite;
    transition:background .3s;
}
.dot:nth-child(2){animation-delay:.2s;}
.dot:nth-child(3){animation-delay:.4s;}
@keyframes pulseDot { 0%,100%{opacity:.2;transform:scale(.8);}50%{opacity:1;transform:scale(1.2);} }

.brand        { font-size:11px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:var(--muted);margin-bottom:8px; }
.status-text  { font-size:18px;font-weight:600;color:var(--text);margin-bottom:8px;min-height:28px; }
.sub-text     { font-size:12px;color:var(--muted);min-height:18px; }

.progress-track { height:3px;background:rgba(255,255,255,.08);border-radius:99px;margin:24px 0 0;overflow:hidden; }
.progress-fill  { height:100%;width:0%;background:linear-gradient(90deg,var(--accent2),var(--accent));border-radius:99px;transition:width .5s ease; }

.footer { font-size:10px;color:rgba(255,255,255,.18);margin-top:24px; }
</style>
</head>
<body>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<!-- ── Hidden MikroTik login form ───────────────────────────────
     action  = link-login-only  (e.g. http://192.168.88.1/login)
     MikroTik accepts plain PAP here; CHAP is only needed in the
     captive-portal page where chap-id/chap-challenge are available.
     Since we are on an external server those variables are gone, so
     we post plain credentials — make sure your hotspot profile
     allows http-pap OR set login-by=http-chap,http-pap
─────────────────────────────────────────────────────────────── -->
<form id="mikrotik-form" method="post" action="<?= htmlspecialchars($link_login_only) ?>">
    <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
    <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
    <input type="hidden" name="dst"      value="<?= htmlspecialchars($link_orig) ?>">
    <input type="hidden" name="popup"    value="true">
</form>

<div class="card">
    <div class="icon-wrap">
        <div class="icon-ring"></div>
        <div class="icon-ring-2"></div>
        <div class="icon-inner">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                <path d="M634.91 154.88C457.74-8.99 182.19-8.93 5.09 154.88c-6.66 6.16-6.79 16.52-.35 22.96l34.24 33.97c6.14 6.09 16.02 6.23 22.4.38 145.92-135.71 370.5-135.71 516.26 0 6.38 5.85 16.26 5.71 22.4-.38l34.24-33.97c6.44-6.44 6.31-16.8-.37-22.96zM320 352c-35.35 0-64 28.65-64 64s28.65 64 64 64 64-28.65 64-64-28.65-64-64-64zm202.13-96.11c-115.94-107.62-296.91-107.31-412.26 0-6.67 6.17-6.77 16.54-.32 22.97l34.18 33.9c6.11 6.07 15.95 6.24 22.28.45 87.98-81.91 220.6-81.99 308.56 0 6.33 5.79 16.17 5.62 22.28-.45l34.18-33.9c6.45-6.43 6.35-16.8-.32-22.97z"/>
            </svg>
        </div>
    </div>

    <div class="pulse-dots">
        <div class="dot"></div><div class="dot"></div><div class="dot"></div>
    </div>

    <p class="brand">Inovatech Networks</p>
    <p class="status-text" id="status-text">Payment confirmed ✓</p>
    <p class="sub-text"    id="sub-text">Signing you in…</p>

    <div class="progress-track">
        <div class="progress-fill" id="progress-fill"></div>
    </div>

    <p class="footer">billing.Inovatech.co.ke</p>
</div>

<script>
    const $fill   = document.getElementById('progress-fill');
    const $status = document.getElementById('status-text');
    const $sub    = document.getElementById('sub-text');

    // Animate progress bar then submit
    let pct = 0;
    const ticker = setInterval(() => {
        pct = Math.min(pct + 8, 92);
        $fill.style.width = pct + '%';
    }, 120);

    setTimeout(() => {
        clearInterval(ticker);
        $fill.style.width = '100%';
        $status.textContent = 'Connected!';
        $sub.textContent    = 'Redirecting to internet…';

        // Submit the hidden form → MikroTik logs the user in
        document.getElementById('mikrotik-form').submit();

    }, 1400);
</script>
</body>
</html>