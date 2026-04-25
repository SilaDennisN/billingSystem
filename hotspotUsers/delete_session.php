<?php
require_once "../core/db.php";
require_once "../core/router.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: /auth/login");
    exit;
}

if ($_SESSION['user']['role'] === "staff") {
    header("Location: index.php?error=Unauthorized");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Invalid request method");
}

$id = $_POST['id'] ?? 0;
$router_id = $_POST['router_id'] ?? null;
$mac_address = $_POST['mac_address'] ?? null;

if (!$id || !$router_id || !$mac_address) {
    header("Location: index.php?error=Missing required parameters");
    exit;
}

try {
    // Try to kick the session from router if still active
    try {
        $client = router_connect($router_id);
        if ($client) {
            // Convert MAC format if needed (from xx:xx:xx:xx:xx:xx to XXXXXXYYYYYY format for RouterOS)
            $mac_router = strtoupper(str_replace(':', '', $mac_address));
            
            // Remove from hotspot active
            $active = $client->query('/ip/hotspot/active/print')->read();
            foreach ($active as $a) {
                if (isset($a['mac-address']) && strtoupper(str_replace(':', '', $a['mac-address'])) === $mac_router) {
                    $remove = new RouterOS\Query('/ip/hotspot/active/remove');
                    $remove->equal('.id', $a['.id']);
                    $client->query($remove)->read();
                    break;
                }
            }
            
            // Remove from host cache
            $hosts = $client->query('/ip/hotspot/host/print')->read();
            foreach ($hosts as $h) {
                if (isset($h['mac-address']) && strtoupper(str_replace(':', '', $h['mac-address'])) === $mac_router) {
                    $remove = new RouterOS\Query('/ip/hotspot/host/remove');
                    $remove->equal('.id', $h['.id']);
                    $client->query($remove)->read();
                    break;
                }
            }
        }
    } catch (Exception $e) {
        // Ignore router errors, still delete from DB
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM hotspot_sessions WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: index.php?success=Session removed successfully");
    exit;
    
} catch (Exception $e) {
    header("Location: index.php?error=" . urlencode("Error removing session: " . $e->getMessage()));
    exit;
}