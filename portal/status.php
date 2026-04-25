<?php
require_once "../core/db.php";
require_once "../core/router.php";
use RouterOS\Query;

date_default_timezone_set('Africa/Nairobi');

// ─── Validate username ────────────────────────────────────────────────────────
$username = trim($_GET['username'] ?? '');

// Detect if MikroTik failed to substitute the variable (literal placeholder)
if (empty($username) || $username === '$(username)') {
    http_response_code(400);
    die(renderError("Session Error", "Could not retrieve your session from the router. Please disconnect and reconnect to the WiFi network."));
}

// Sanitize: only allow alphanumeric, underscores, hyphens, colons
if (!preg_match('/^[a-zA-Z0-9_\-:]+$/', $username)) {
    http_response_code(400);
    die(renderError("Invalid Request", "The username provided is not valid."));
}

// ─── Convert username → MAC address (format: mac_76C03CCF6A17 → 76:C0:3C:CF:6A:17) ──
$macRouter = null;
if (str_starts_with(strtolower($username), 'mac_')) {
    $macRaw    = substr($username, 4);
    $macRouter = strtoupper(implode(":", str_split($macRaw, 2)));
}

// ─── Connect to MikroTik router ───────────────────────────────────────────────
$routerId = 1;
$client   = router_connect($routerId);
$isActive = false;
$sessionData = [];

if ($client && $macRouter) {
    try {
        $query = new Query('/ip/hotspot/active/print');
        $query->where('mac-address', $macRouter);
        $result  = $client->query($query)->read();
        $isActive    = !empty($result);
        $sessionData = $result[0] ?? [];
    } catch (Exception $e) {
        // Router query failed — still show DB info
        $isActive = false;
    }
}

// ─── Fetch user from database ─────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT hu.*, hp.profile_name, hp.speed_limit
    FROM hotspot_users hu
    JOIN hotspot_profiles hp ON hu.plan_id = hp.id
    WHERE hu.username = ?
    LIMIT 1
");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die(renderError("Account Not Found", "No active package was found for this account. Please purchase a package to continue."));
}

// ─── Time calculations ────────────────────────────────────────────────────────
$now              = new DateTime();
$expire           = new DateTime($user['expires_at']);
$remainingSeconds = max(0, $expire->getTimestamp() - $now->getTimestamp());
$status           = $remainingSeconds > 0 ? 'ACTIVE' : 'EXPIRED';

// Percentage of time remaining (needs created_at in DB, fallback to 100%)
$percentRemaining = 100;
if (!empty($user['created_at'])) {
    $created  = new DateTime($user['created_at']);
    $total    = max(1, $expire->getTimestamp() - $created->getTimestamp());
    $elapsed  = $expire->getTimestamp() - $now->getTimestamp();
    $percentRemaining = max(0, min(100, round(($elapsed / $total) * 100)));
}

function formatTime(int $seconds): string {
    if ($seconds <= 0) return "Expired";
    $days    = floor($seconds / 86400);
    $hours   = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($days > 0)  return "{$days}d {$hours}h remaining";
    if ($hours > 0) return "{$hours}h {$minutes}m remaining";
    return "{$minutes} minute(s) remaining";
}

function formatBytes(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . " GB";
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2)    . " MB";
    if ($bytes >= 1024)       return round($bytes / 1024, 2)       . " KB";
    return "{$bytes} B";
}

function renderError(string $title, string $message): string {
    return <<<HTML
    <!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error — WiFi Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
               font-family:'DM Sans',sans-serif; display:flex; align-items:center;
               justify-content:center; min-height:100vh; color:white; margin:0; }
        .card { background:rgba(255,255,255,0.08); backdrop-filter:blur(16px);
                border:1px solid rgba(255,255,255,0.12); border-radius:20px;
                padding:40px 32px; max-width:380px; width:90%; text-align:center; }
        h2 { color:#FF5252; margin-bottom:12px; }
        p  { opacity:0.75; line-height:1.6; }
        a  { display:inline-block; margin-top:24px; padding:10px 24px;
             background:rgba(255,255,255,0.1); border-radius:10px; color:white;
             text-decoration:none; }
    </style></head><body>
    <div class="card">
        <div style="font-size:48px;margin-bottom:16px">⚠️</div>
        <h2>{$title}</h2>
        <p>{$message}</p>
        <a href="/">← Back to Portal</a>
    </div></body></html>
    HTML;
}

// Session upload/download from router
$uploaded   = isset($sessionData['bytes-out']) ? formatBytes((int)$sessionData['bytes-out']) : null;
$downloaded = isset($sessionData['bytes-in'])  ? formatBytes((int)$sessionData['bytes-in'])  : null;
$sessionTime = $sessionData['uptime'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Status — <?= htmlspecialchars($user['profile_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,700;1,400&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --green:  #22c55e;
            --red:    #ef4444;
            --blue:   #38bdf8;
            --bg1:    #0a1628;
            --bg2:    #0f2033;
            --card:   rgba(255,255,255,0.06);
            --border: rgba(255,255,255,0.10);
            --text:   #e2e8f0;
            --muted:  rgba(226,232,240,0.55);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg1);
            font-family: 'DM Sans', sans-serif;
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            overflow-x: hidden;
        }

        /* Background aurora effect */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 10%, rgba(34,197,94,0.08) 0%, transparent 60%),
                radial-gradient(ellipse 60% 60% at 80% 80%, rgba(56,189,248,0.07) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 24px;
            animation: fadeDown 0.5s ease both;
        }
        .header .logo {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
        }

        /* ── Status badge ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-top: 8px;
        }
        .status-badge.active {
            background: rgba(34,197,94,0.15);
            color: var(--green);
            border: 1px solid rgba(34,197,94,0.3);
        }
        .status-badge.expired {
            background: rgba(239,68,68,0.15);
            color: var(--red);
            border: 1px solid rgba(239,68,68,0.3);
        }
        .status-badge .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }
        .status-badge.active .dot {
            animation: pulse 1.5s ease infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.4; transform: scale(1.3); }
        }

        /* ── Card ── */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            backdrop-filter: blur(20px);
            animation: fadeUp 0.5s ease both;
        }
        .card + .card { margin-top: 12px; }

        /* ── Time ring ── */
        .ring-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px 0 20px;
        }
        .ring-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin-bottom: 12px;
        }
        .ring-container svg {
            transform: rotate(-90deg);
        }
        .ring-bg   { fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 8; }
        .ring-fill {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 377;
            stroke-dashoffset: <?= 377 - (377 * $percentRemaining / 100) ?>;
            stroke: <?= $status === 'ACTIVE' ? 'var(--green)' : 'var(--red)' ?>;
            transition: stroke-dashoffset 1.2s ease;
            filter: drop-shadow(0 0 6px <?= $status === 'ACTIVE' ? 'rgba(34,197,94,0.5)' : 'rgba(239,68,68,0.5)' ?>);
        }
        .ring-text {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 16px;
        }
        .ring-text .time-val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.3;
            color: var(--text);
        }
        .ring-text .pct {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }
        .plan-name {
            font-size: 17px;
            font-weight: 700;
        }
        .plan-sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── Info rows ── */
        .info-grid {
            display: grid;
            gap: 10px;
        }
        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,0.04);
            border-radius: 12px;
            padding: 12px 16px;
            gap: 8px;
        }
        .info-row .label {
            font-size: 12px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .info-row .label .icon { font-size: 15px; }
        .info-row .value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            text-align: right;
        }

        /* ── Stats row ── */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .stat-box {
            background: rgba(255,255,255,0.04);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
        }
        .stat-box .stat-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 5px;
        }
        .stat-box .stat-val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 600;
        }
        .stat-box .stat-val.up   { color: var(--blue); }
        .stat-box .stat-val.down { color: var(--green); }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 20px 0;
        }

        /* ── Buttons ── */
        .btn-group {
            display: grid;
            gap: 10px;
            margin-top: 14px;
        }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 20px;
            border: none;
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.15s, transform 0.15s;
        }
        .btn:active { transform: scale(0.98); opacity: 0.85; }
        .btn-primary {
            background: var(--green);
            color: #0a1628;
        }
        .btn-danger {
            background: rgba(239,68,68,0.15);
            color: var(--red);
            border: 1px solid rgba(239,68,68,0.25);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.07);
            color: var(--text);
            border: 1px solid var(--border);
        }

        /* ── Countdown ── */
        #countdown {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--muted);
            text-align: center;
            margin-top: 14px;
        }

        /* ── Footer ── */
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: var(--muted);
            animation: fadeUp 0.6s 0.2s ease both;
        }

        /* ── Animations ── */
        @keyframes fadeDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:none; } }
        @keyframes fadeUp   { from { opacity:0; transform:translateY(14px);  } to { opacity:1; transform:none; } }
    </style>
</head>
<body>
<div class="wrapper">

    <!-- Header -->
    <div class="header">
        <div class="logo">📶 Inovatech WiFi</div>
        <h1><?= htmlspecialchars($user['profile_name']) ?></h1>
        <div class="status-badge <?= strtolower($status) ?>">
            <span class="dot"></span>
            <?= $status ?>
            <?= $isActive ? '· Online' : '' ?>
        </div>
    </div>

    <!-- Main card -->
    <div class="card">

        <!-- Time ring -->
        <div class="ring-wrap">
            <div class="ring-container">
                <svg width="140" height="140" viewBox="0 0 140 140">
                    <circle class="ring-bg"   cx="70" cy="70" r="60"/>
                    <circle class="ring-fill" cx="70" cy="70" r="60"/>
                </svg>
                <div class="ring-text">
                    <span class="time-val"><?= formatTime($remainingSeconds) ?></span>
                    <span class="pct"><?= $percentRemaining ?>% left</span>
                </div>
            </div>
            <div class="plan-name"><?= htmlspecialchars($user['profile_name']) ?></div>
            <?php if (!empty($user['speed_limit'])): ?>
            <div class="plan-sub">⚡ <?= htmlspecialchars($user['speed_limit']) ?></div>
            <?php endif; ?>
        </div>

        <hr class="divider">

        <!-- Info rows -->
        <div class="info-grid">
            <div class="info-row">
                <span class="label"><span class="icon">📅</span> Expires</span>
                <span class="value"><?= $expire->format('d M Y, H:i') ?></span>
            </div>

            <div class="info-row">
                <span class="label"><span class="icon">👤</span> User</span>
                <span class="value" style="font-size:11px"><?= htmlspecialchars($username) ?></span>
            </div>

            <?php if ($macRouter): ?>
            <div class="info-row">
                <span class="label"><span class="icon">🖥️</span> Device MAC</span>
                <span class="value" style="font-size:11px"><?= htmlspecialchars($macRouter) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($sessionTime): ?>
            <div class="info-row">
                <span class="label"><span class="icon">⏱️</span> Session</span>
                <span class="value"><?= htmlspecialchars($sessionTime) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($uploaded || $downloaded): ?>
        <hr class="divider">
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-label">⬆ Uploaded</div>
                <div class="stat-val up"><?= $uploaded ?? '—' ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">⬇ Downloaded</div>
                <div class="stat-val down"><?= $downloaded ?? '—' ?></div>
            </div>
        </div>
        <?php endif; ?>

        <hr class="divider">

        <!-- Buttons -->
        <div class="btn-group">
            <?php if ($status === 'ACTIVE' && $isActive): ?>
                <a class="btn btn-primary" href="<?= htmlspecialchars($sessionData['url'] ?? '#') ?>">
                    🔄 Reconnect / Refresh
                </a>
            <?php endif; ?>

            <?php if ($status === 'EXPIRED'): ?>
                <a class="btn btn-primary" href="https://wifi.inovatech.co.ke/portal/buy.php?username=<?= urlencode($username) ?>">
                    🛒 Buy a Package
                </a>
            <?php endif; ?>

            <a class="btn btn-secondary" href="https://wifi.inovatech.co.ke/portal/buy.php?username=<?= urlencode($username) ?>">
                ➕ Top Up / Upgrade
            </a>

            <a class="btn btn-danger" href="logout.php?username=<?= urlencode($username) ?>"
               onclick="return confirm('Are you sure you want to logout?')">
                🚪 Logout
            </a>
        </div>

        <!-- Live countdown -->
        <?php if ($remainingSeconds > 0): ?>
        <div id="countdown">Refreshing in <span id="ctdown">30</span>s</div>
        <?php endif; ?>

    </div>

    <div class="footer">
        Powered by Inovatech WiFi Portal &nbsp;·&nbsp;
        <?= $now->format('d M Y, H:i') ?>
    </div>

</div>

<script>
    // Auto-refresh countdown
    var secs = 30;
    var el   = document.getElementById('ctdown');
    if (el) {
        setInterval(function() {
            secs--;
            el.textContent = secs;
            if (secs <= 0) {
                window.location.reload();
            }
        }, 1000);
    }

    // Animate ring on load
    document.addEventListener('DOMContentLoaded', function() {
        var ring = document.querySelector('.ring-fill');
        if (ring) {
            var target = ring.style.strokeDashoffset || ring.getAttribute('stroke-dashoffset');
            ring.style.strokeDashoffset = 377;
            requestAnimationFrame(function() {
                ring.style.strokeDashoffset = target;
            });
        }
    });
</script>
</body>
</html>