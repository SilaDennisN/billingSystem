<?php
// Test router connection and setup
$router_error = null;
$router_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_router'])) {
    try {
        // Include RouterOS API class
        require_once '../vendor/autoload.php'; // For composer installation
        // Or include directly if not using composer
        if (!class_exists('RouterOS\Client')) {
            require_once '../lib/routeros-api.class.php';
        }
        
        $router_ip = $_POST['router_ip'];
        $router_user = $_POST['router_user'];
        $router_pass = $_POST['router_pass'];
        $router_port = $_POST['router_port'] ?? 8728;
        
        // Test connection
        if (class_exists('RouterOS\Client')) {
            // Using PEAR2_Net_RouterOS
            $client = new RouterOS\Client($router_ip, $router_user, $router_pass, $router_port);
            $response = $client->write('/system/resource/get')->read();
            $router_success = "Connected to RouterOS " . $response[0]['version'];
        } else {
            // Using original RouterOS API class
            $api = new RouterosAPI();
            if ($api->connect($router_ip, $router_user, $router_pass, $router_port)) {
                $api->write('/system/resource/get');
                $response = $api->read();
                $router_success = "Connected to RouterOS " . $response[0]['version'];
                $api->disconnect();
            } else {
                $router_error = "Failed to connect to router";
            }
        }
        
        $_SESSION['router_test_success'] = true;
        
    } catch (Exception $e) {
        $router_error = $e->getMessage();
    }
}
?>
<form method="POST" action="" class="needs-validation" novalidate>
    <input type="hidden" name="step" value="3">
    
    <h3 class="mb-4">MikroTik Router Configuration</h3>
    <p class="text-muted mb-4">Enter your MikroTik router credentials. The installer will configure hotspot settings automatically.</p>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Ensure your router has API enabled (IP > Services > API) and the user has full permissions.
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="router_ip" class="form-label">Router IP Address *</label>
                <input type="text" class="form-control" id="router_ip" name="router_ip" 
                       value="<?php echo isset($_SESSION['router_ip']) ? $_SESSION['router_ip'] : ''; ?>" 
                       placeholder="192.168.88.1" required>
                <div class="form-text">Router's LAN IP address</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="router_port" class="form-label">API Port *</label>
                <input type="number" class="form-control" id="router_port" name="router_port" 
                       value="<?php echo isset($_SESSION['router_port']) ? $_SESSION['router_port'] : '8728'; ?>" 
                       required>
                <div class="form-text">Default is 8728 (API) or 8729 (API-SSL)</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label for="router_user" class="form-label">Router Username *</label>
                <input type="text" class="form-control" id="router_user" name="router_user" 
                       value="<?php echo isset($_SESSION['router_user']) ? $_SESSION['router_user'] : 'admin'; ?>" 
                       required>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="form-group">
                <label for="router_pass" class="form-label">Router Password *</label>
                <input type="password" class="form-control" id="router_pass" name="router_pass" 
                       value="<?php echo isset($_SESSION['router_pass']) ? $_SESSION['router_pass'] : ''; ?>" 
                       required>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="form-group">
                <label for="hotspot_interface" class="form-label">Hotspot Interface</label>
                <select class="form-select" id="hotspot_interface" name="hotspot_interface">
                    <option value="bridge-local">bridge-local</option>
                    <option value="ether2">ether2</option>
                    <option value="wlan1">wlan1</option>
                    <option value="all">All Interfaces</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="auto_configure" name="auto_configure" checked>
            <label class="form-check-label" for="auto_configure">
                Automatically configure hotspot settings on router
            </label>
            <div class="form-text">This will setup DHCP, hotspot server, user profiles, and firewall rules</div>
        </div>
    </div>
    
    <?php if ($router_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Router connection failed: <?php echo htmlspecialchars($router_error); ?>
        </div>
    <?php elseif ($router_success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($router_success); ?>
        </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between mt-4">
        <a href="?step=2" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
        <div>
            <button type="submit" name="test_router" class="btn btn-info me-2">
                <i class="fas fa-wifi me-2"></i>Test Router Connection
            </button>
            <button type="submit" class="btn btn-install" <?php echo !isset($_SESSION['router_test_success']) ? 'disabled' : ''; ?>>
                Continue <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>
    </div>
</form>