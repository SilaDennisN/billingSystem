<?php
// Validate admin setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_admin'])) {
    if ($_POST['admin_password'] !== $_POST['admin_password_confirm']) {
        $password_error = "Passwords do not match";
    } else {
        // All good, proceed
        header("Location: ?step=5");
        exit;
    }
}
?>
<form method="POST" action="" class="needs-validation" novalidate>
    <input type="hidden" name="step" value="4">
    
    <h3 class="mb-4">Administrator Account</h3>
    <p class="text-muted mb-4">Create the main administrator account for the billing system.</p>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="site_name" class="form-label">Site Name *</label>
                <input type="text" class="form-control" id="site_name" name="site_name" 
                       value="<?php echo isset($_SESSION['site_name']) ? $_SESSION['site_name'] : 'Hotspot Billing System'; ?>" 
                       required>
                <div class="form-text">Display name for your hotspot system</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="admin_email" class="form-label">Admin Email *</label>
                <input type="email" class="form-control" id="admin_email" name="admin_email" 
                       value="<?php echo isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : ''; ?>" 
                       required>
                <div class="form-text">Used for login and notifications</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="admin_username" class="form-label">Admin Username *</label>
                <input type="text" class="form-control" id="admin_username" name="admin_username" 
                       value="<?php echo isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'admin'; ?>" 
                       required>
                <div class="form-text">Username for system login</div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="admin_password" class="form-label">Admin Password *</label>
                <input type="password" class="form-control" id="admin_password" name="admin_password" 
                       minlength="8" required>
                <div class="form-text">Minimum 8 characters</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="admin_password_confirm" class="form-label">Confirm Password *</label>
                <input type="password" class="form-control" id="admin_password_confirm" name="admin_password_confirm" 
                       minlength="8" required>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="timezone" class="form-label">Timezone *</label>
                <select class="form-select" id="timezone" name="timezone" required>
                    <option value="UTC">UTC</option>
                    <option value="Africa/Nairobi" selected>Africa/Nairobi</option>
                    <option value="America/New_York">America/New_York</option>
                    <option value="Europe/London">Europe/London</option>
                    <option value="Asia/Dubai">Asia/Dubai</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="enable_registration" name="enable_registration" checked>
            <label class="form-check-label" for="enable_registration">
                Enable user self-registration
            </label>
        </div>
    </div>
    
    <?php if (isset($password_error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $password_error; ?>
        </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between mt-4">
        <a href="?step=3" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
        <button type="submit" name="setup_admin" class="btn btn-install">
            Complete Setup <i class="fas fa-check ms-2"></i>
        </button>
    </div>
</form>