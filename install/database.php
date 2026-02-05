<?php
// Test database connection
$connection_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    try {
        $conn = new mysqli(
            $_POST['db_host'], 
            $_POST['db_user'], 
            $_POST['db_pass'], 
            $_POST['db_name']
        );
        
        if ($conn->connect_error) {
            $connection_error = $conn->connect_error;
        } else {
            $_SESSION['db_test_success'] = true;
            $conn->close();
        }
    } catch (Exception $e) {
        $connection_error = $e->getMessage();
    }
}
?>
<form method="POST" action="" class="needs-validation" novalidate>
    <input type="hidden" name="step" value="2">
    
    <h3 class="mb-4">Database Configuration</h3>
    <p class="text-muted mb-4">Enter your MySQL database credentials. The installer will create the necessary tables.</p>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="db_host" class="form-label">Database Host *</label>
                <input type="text" class="form-control" id="db_host" name="db_host" 
                       value="<?php echo isset($_SESSION['db_host']) ? $_SESSION['db_host'] : 'localhost'; ?>" 
                       required>
                <div class="form-text">Usually "localhost" or "127.0.0.1"</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="db_name" class="form-label">Database Name *</label>
                <input type="text" class="form-control" id="db_name" name="db_name" 
                       value="<?php echo isset($_SESSION['db_name']) ? $_SESSION['db_name'] : 'hotspot_billing'; ?>" 
                       required>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="db_user" class="form-label">Database Username *</label>
                <input type="text" class="form-control" id="db_user" name="db_user" 
                       value="<?php echo isset($_SESSION['db_user']) ? $_SESSION['db_user'] : 'root'; ?>" 
                       required>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="db_pass" class="form-label">Database Password</label>
                <input type="password" class="form-control" id="db_pass" name="db_pass"
                       value="<?php echo isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : ''; ?>">
            </div>
        </div>
    </div>
    
    <?php if ($connection_error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Connection failed: <?php echo htmlspecialchars($connection_error); ?>
        </div>
    <?php elseif (isset($_SESSION['db_test_success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            Database connection successful!
        </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between mt-4">
        <a href="?step=1" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
        <div>
            <button type="submit" name="test_connection" class="btn btn-info me-2">
                <i class="fas fa-plug me-2"></i>Test Connection
            </button>
            <button type="submit" class="btn btn-install" <?php echo !isset($_SESSION['db_test_success']) ? 'disabled' : ''; ?>>
                Continue <i class="fas fa-arrow-right ms-2"></i>
            </button>
        </div>
    </div>
</form>