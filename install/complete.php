<?php
// Create config file and setup database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    // Generate config.php
    $config_content = '<?php
// Database Configuration
define("DB_HOST", "' . addslashes($_SESSION['db_host']) . '");
define("DB_NAME", "' . addslashes($_SESSION['db_name']) . '");
define("DB_USER", "' . addslashes($_SESSION['db_user']) . '");
define("DB_PASS", "' . addslashes($_SESSION['db_pass']) . '");

// Router Configuration
define("ROUTER_IP", "' . addslashes($_SESSION['router_ip']) . '");
define("ROUTER_USER", "' . addslashes($_SESSION['router_user']) . '");
define("ROUTER_PASS", "' . addslashes($_SESSION['router_pass']) . '");
define("ROUTER_PORT", "' . addslashes($_SESSION['router_port']) . '");

// Application Settings
define("SITE_NAME", "' . addslashes($_SESSION['site_name']) . '");
define("BASE_URL", "' . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '");

// Security
define("ENCRYPTION_KEY", "' . bin2hex(random_bytes(32)) . '");

// Timezone
date_default_timezone_set("' . addslashes($_SESSION['timezone'] ?? 'UTC') . '");

// Error Reporting
error_reporting(E_ALL);
ini_set("display_errors", 0);
ini_set("log_errors", 1);
ini_set("error_log", dirname(__FILE__) . "/logs/error.log");
?>';

    // Write config file
    file_put_contents('../config.php', $config_content);
    
    // Create database tables
    try {
        $conn = new mysqli(
            $_SESSION['db_host'],
            $_SESSION['db_user'],
            $_SESSION['db_pass'],
            $_SESSION['db_name']
        );
        
        // Read SQL file
        $sql = file_get_contents('install.sql');
        
        // Execute SQL
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
        }
        
        // Insert admin user
        $stmt = $conn->prepare("INSERT INTO admin_users (username, email, password, full_name, role, status, created_at) VALUES (?, ?, ?, ?, 'superadmin', 'active', NOW())");
        $stmt->bind_param("ssss", 
            $_SESSION['admin_username'],
            $_SESSION['admin_email'],
            password_hash($_SESSION['admin_password'], PASSWORD_DEFAULT),
            $_SESSION['site_name'] . ' Admin'
        );
        $stmt->execute();
        
        $conn->close();
        
        // Configure router automatically
        if (isset($_SESSION['auto_configure']) && $_SESSION['auto_configure']) {
            configureRouter();
        }
        
        // Create .htaccess file
        createHtaccess();
        
        // Set installation complete flag
        $_SESSION['installation_complete'] = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function configureRouter() {
    try {
        // Include RouterOS API
        require_once '../vendor/autoload.php';
        
        $client = new RouterOS\Client(
            $_SESSION['router_ip'],
            $_SESSION['router_user'],
            $_SESSION['router_pass'],
            $_SESSION['router_port']
        );
        
        // Configure Hotspot
        $commands = [
            // Create hotspot user profile
            '/ip/hotspot/user/profile/add name="default" session-timeout=none idle-timeout=none keepalive-timeout=2m status-autorefresh=1m shared-users=1 rate-limit=""',
            
            // Add hotspot server
            '/ip/hotspot/add name=hotspot1 interface=' . $_SESSION['hotspot_interface'] . ' address-pool=hotspot_pool disabled=no',
            
            // Create address pool
            '/ip/pool/add name=hotspot_pool ranges=192.168.100.10-192.168.100.254',
            
            // Configure DHCP server
            '/ip/dhcp-server/add name=dhcp_hotspot interface=' . $_SESSION['hotspot_interface'] . ' address-pool=hotspot_pool disabled=no',
            '/ip/dhcp-server/network/add address=192.168.100.0/24 gateway=192.168.100.1 dns-server=8.8.8.8,8.8.4.4',
            
            // Add firewall rules
            '/ip/firewall/nat/add chain=srcnat action=masquerade out-interface=' . $_SESSION['hotspot_interface'],
            '/ip/firewall/filter/add chain=input protocol=tcp dst-port=80,443 action=accept comment="Allow HTTP/HTTPS"',
            
            // Add walled garden for billing system
            '/ip/hotspot/walled-garden/ip/add dst-host=' . $_SERVER['HTTP_HOST'] . ' action=accept',
            '/ip/hotspot/walled-garden/ip/add dst-host=*.google.com action=accept',
            '/ip/hotspot/walled-garden/ip/add dst-host=*.facebook.com action=accept',
            
            // Create default hotspot user
            '/ip/hotspot/user/add name="demo" password="demo123" profile=default server=hotspot1',
            
            // Enable API
            '/ip/service/set api disabled=no',
            '/ip/service/set winbox disabled=no',
        ];
        
        foreach ($commands as $command) {
            $client->write($command);
        }
        
    } catch (Exception $e) {
        // Log error but don't stop installation
        error_log("Router configuration failed: " . $e->getMessage());
    }
}

function createHtaccess() {
    $htaccess = 'RewriteEngine On
RewriteBase /

# Prevent directory listing
Options -Indexes

# Protect sensitive files
<FilesMatch "^(config\.php|install\.sql|\.htaccess)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Redirect to installer if not installed
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/install
RewriteCond %{DOCUMENT_ROOT}/config.php !-f
RewriteRule ^(.*)$ /install/index.php [L,R=302]

# Redirect away from installer if installed
RewriteCond %{REQUEST_URI} ^/install
RewriteCond %{DOCUMENT_ROOT}/config.php -f
RewriteRule ^(.*)$ /index.php [L,R=302]

# API routes
RewriteRule ^api/(.*)$ api/index.php?route=$1 [QSA,L]

# Clean URLs
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?route=$1 [QSA,L]';
    
    file_put_contents('../.htaccess', $htaccess);
}
?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Installation Error</h4>
        <p><?php echo htmlspecialchars($error); ?></p>
        <hr>
        <p class="mb-0">Please check your database credentials and try again.</p>
    </div>
    
    <div class="text-center mt-4">
        <a href="?step=4" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Go Back
        </a>
    </div>
    
<?php elseif (isset($_SESSION['installation_complete'])): ?>
    <div class="text-center py-5">
        <div class="mb-4">
            <i class="fas fa-check-circle text-success" style="font-size: 5rem;"></i>
        </div>
        
        <h2 class="text-success mb-3">Installation Complete!</h2>
        <p class="lead mb-4">Your Hotspot Billing System has been successfully installed.</p>
        
        <div class="card border-success mb-4">
            <div class="card-header bg-success text-white">
                <i class="fas fa-key me-2"></i>Login Credentials
            </div>
            <div class="card-body text-start">
                <p><strong>Admin URL:</strong> <code><?php echo (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])); ?>/admin</code></p>
                <p><strong>Username:</strong> <code><?php echo htmlspecialchars($_SESSION['admin_username']); ?></code></p>
                <p><strong>Password:</strong> <code>●●●●●●●●</code> (The password you set)</p>
            </div>
        </div>
        
        <div class="alert alert-warning">
            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Important Security Steps</h5>
            <ol class="mb-0">
                <li>Delete the <code>/install</code> directory</li>
                <li>Change the default router admin password</li>
                <li>Configure SSL certificate for secure connections</li>
                <li>Set up regular database backups</li>
            </ol>
        </div>
        
        <div class="mt-5">
            <a href="../admin/login.php" class="btn btn-install btn-lg me-3">
                <i class="fas fa-sign-in-alt me-2"></i>Go to Admin Panel
            </a>
            <a href="../index.php" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-home me-2"></i>Visit Homepage
            </a>
        </div>
    </div>
    
<?php else: ?>
    <div class="text-center py-5">
        <h3 class="mb-4">Ready to Install</h3>
        <p class="lead mb-4">Click the button below to complete the installation process.</p>
        
        <div class="alert alert-info text-start mb-4">
            <h5><i class="fas fa-info-circle me-2"></i>What will be installed:</h5>
            <ul class="mb-0">
                <li>Database tables and initial data</li>
                <li>Configuration file with your settings</li>
                <li>Administrator account</li>
                <li>Router hotspot configuration (if selected)</li>
                <li>URL rewrite rules</li>
            </ul>
        </div>
        
        <form method="POST" action="">
            <button type="submit" name="install" class="btn btn-install btn-lg">
                <i class="fas fa-rocket me-2"></i>Complete Installation
            </button>
        </form>
        
        <div class="mt-4">
            <a href="?step=4" class="btn btn-link">
                <i class="fas fa-arrow-left me-2"></i>Back to Previous Step
            </a>
        </div>
    </div>
<?php endif; ?>