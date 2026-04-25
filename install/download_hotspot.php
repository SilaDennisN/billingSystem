<?php
error_reporting(0);
ini_set('display_errors', '0');

ob_start();
require_once "../core/db.php";
require_once "../core/auth.php";
ob_end_clean();

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$router_id = $_POST['router_id'] ?? null;
if (!$router_id) {
    echo json_encode(['success' => false, 'message' => 'Missing router_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM routers WHERE router_id=?");
$stmt->execute([$router_id]);
$router = $stmt->fetch();

if (!$router) {
    echo json_encode(['success' => false, 'message' => 'Router not found']);
    exit;
}

$vpn_ip   = $router['vpn_ip'];
$api_user = $router['api_user'];
$api_pass = $router['api_pass'];

/* -------------------------------------------------------
 Build the login.html content for this router
-------------------------------------------------------- */
$billing_portal = "https://billing.inovatech.co.ke/portal/index";
$login_html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Connecting...</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    body { background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; color: #fff; padding: 20px; }
    .container { width: 100%; max-width: 380px; }
    .card { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 30px 20px; border-radius: 16px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
    .loader { width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.3); border-top: 4px solid #fff; border-radius: 50%; animation: spin 1s linear infinite; margin: 20px auto; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .small { font-size: 13px; color: #e0e0e0; }
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2 id="title">Loading Packages...</h2>
        <div class="loader"></div>
        <p class="small">This will only take a moment</p>
    </div>
</div>
<form id="redirectForm" action="{$billing_portal}" method="post">
    <input type="hidden" name="mac"             value="\$(mac)">
    <input type="hidden" name="ip"              value="\$(ip)">
    <input type="hidden" name="username"        value="\$(username)">
    <input type="hidden" name="link-login"      value="\$(link-login)">
    <input type="hidden" name="link-login-only" value="\$(link-login-only)">
    <input type="hidden" name="link-orig"       value="\$(link-orig)">
    <input type="hidden" name="error"           value="\$(error)">
    <input type="hidden" name="router_id"       value="{$router['router_id']}">
</form>
<script>
    const isReconnect = "\$(username)" !== "";
    if (isReconnect) { document.getElementById("title").innerText = "Welcome back \u{1F44B}"; }
    setTimeout(() => { document.getElementById("redirectForm").submit(); }, 600);
</script>
</body>
</html>
HTML;

/* -------------------------------------------------------
 RouterOS API class (plain TCP, no external dependencies)
-------------------------------------------------------- */
class RouterOSAPI {
    private $socket;
    private $error;

    public function connect(string $host, string $user, string $pass, int $port = 8728, int $timeout = 10): bool {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->socket) {
            $this->error = "Cannot connect to {$host}:{$port} — {$errstr}";
            return false;
        }
        stream_set_timeout($this->socket, $timeout);

        // Login
        $response = $this->talk(['/login', '=name=' . $user, '=password=' . $pass]);

        if (empty($response) || ($response[0][0] ?? '') !== '!done') {
            // Try legacy MD5 login (older RouterOS)
            if (!empty($response[0]['=ret'] ?? '')) {
                $challenge = pack('H*', $response[0]['=ret']);
                $md5pass   = '00' . md5("\x00" . $pass . $challenge);
                $response  = $this->talk(['/login', '=name=' . $user, '=response=' . $md5pass]);
            }
            if (($response[0][0] ?? '') !== '!done') {
                $this->error = 'Login failed';
                return false;
            }
        }
        return true;
    }

    public function talk(array $words): array {
        $this->writeWords($words);
        return $this->readSentence();
    }

    private function writeWords(array $words): void {
        foreach ($words as $word) {
            $this->writeLen(strlen($word));
            fwrite($this->socket, $word);
        }
        $this->writeLen(0); // end of sentence
    }

    private function writeLen(int $len): void {
        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } else {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        }
    }

    private function readSentence(): array {
        $sentence = [];
        while (true) {
            $word = $this->readWord();
            if ($word === '') break;
            $sentence[] = $word;
        }
        // Parse into associative
        $parsed = [];
        $current = ['type' => '', 'attrs' => []];
        foreach ($sentence as $word) {
            if ($word[0] === '!') {
                if ($current['type']) $parsed[] = $current;
                $current = ['type' => $word, 'attrs' => []];
            } elseif (strpos($word, '=') !== false) {
                [, $k, $v] = explode('=', $word, 3) + ['', '', ''];
                $current['attrs'][$k] = $v;
            }
        }
        if ($current['type']) $parsed[] = $current;
        return $parsed;
    }

    private function readWord(): string {
        $len = $this->readLen();
        if ($len === 0) return '';
        $word = '';
        while (strlen($word) < $len) {
            $word .= fread($this->socket, $len - strlen($word));
        }
        return $word;
    }

    private function readLen(): int {
        $b = ord(fread($this->socket, 1));
        if (($b & 0x80) === 0) return $b;
        if (($b & 0xC0) === 0x80) return (($b & 0x3F) << 8) | ord(fread($this->socket, 1));
        $b2 = ord(fread($this->socket, 1));
        $b3 = ord(fread($this->socket, 1));
        return (($b & 0x3F) << 16) | ($b2 << 8) | $b3;
    }

    public function disconnect(): void {
        if ($this->socket) fclose($this->socket);
    }

    public function getError(): string { return $this->error ?? ''; }
}

/* -------------------------------------------------------
 Connect to router and upload login.html via FTP (port 21)
 MikroTik exposes a built-in FTP server on port 21
-------------------------------------------------------- */

// Method: use RouterOS API to run a /tool fetch from the VPS,
// but simpler — MikroTik has built-in FTP on port 21, use that.

function uploadViaFTP(string $host, string $user, string $pass, string $remotePath, string $content): array {
    $conn = @ftp_connect($host, 21, 10);
    if (!$conn) {
        return ['success' => false, 'message' => "FTP: Cannot connect to {$host}:21 — is the router reachable over VPN?"];
    }

    if (!@ftp_login($conn, $user, $pass)) {
        ftp_close($conn);
        return ['success' => false, 'message' => "FTP: Login failed. Check api_user/api_pass for this router."];
    }

    ftp_pasv($conn, true);

    // Write content to a temp stream
    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, $content);
    rewind($tmp);

    // Delete existing login.html first if it exists
    @ftp_delete($conn, $remotePath);

    // Upload new file
    $ok = ftp_fput($conn, $remotePath, $tmp, FTP_BINARY);
    fclose($tmp);
    ftp_close($conn);

    if (!$ok) {
        return ['success' => false, 'message' => "FTP: File upload failed. Make sure the hotspot folder exists on the router."];
    }

    return ['success' => true, 'message' => 'login.html uploaded successfully to ' . $remotePath];
}

/* -------------------------------------------------------
 Run the upload
-------------------------------------------------------- */
$remote_path = 'hotspot/login.html';
$result = uploadViaFTP($vpn_ip, $api_user, $api_pass, $remote_path, $login_html);

echo json_encode($result);