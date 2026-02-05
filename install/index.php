<?php
session_start();
ob_start();

// Disable direct access if already installed
if (file_exists('../config.php')) {
    header('Location: ../index.php');
    exit;
}

// Define installation steps
$steps = [
    1 => 'System Check',
    2 => 'Database Setup',
    3 => 'Router Configuration',
    4 => 'Admin Setup',
    5 => 'Complete'
];

$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step'])) {
        $step = (int)$_POST['step'];
        
        // Validate and process each step
        switch ($step) {
            case 2:
                $_SESSION['db_host'] = $_POST['db_host'];
                $_SESSION['db_name'] = $_POST['db_name'];
                $_SESSION['db_user'] = $_POST['db_user'];
                $_SESSION['db_pass'] = $_POST['db_pass'];
                break;
                
            case 3:
                $_SESSION['router_ip'] = $_POST['router_ip'];
                $_SESSION['router_user'] = $_POST['router_user'];
                $_SESSION['router_pass'] = $_POST['router_pass'];
                $_SESSION['router_port'] = $_POST['router_port'] ?? 8728;
                break;
                
            case 4:
                $_SESSION['admin_email'] = $_POST['admin_email'];
                $_SESSION['admin_password'] = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                $_SESSION['site_name'] = $_POST['site_name'];
                break;
        }
        
        // Move to next step
        header("Location: ?step=" . ($step + 1));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotspot Billing System - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .install-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-top: 50px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        .step-indicator:before {
            content: '';
            position: absolute;
            top: 24px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        .step {
            text-align: center;
            position: relative;
            z-index: 2;
        }
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 auto 10px;
            border: 4px solid white;
        }
        .step.active .step-number {
            background: #0d6efd;
            color: white;
        }
        .step.completed .step-number {
            background: #198754;
            color: white;
        }
        .step-title {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .step.active .step-title {
            color: #0d6efd;
            font-weight: bold;
        }
        .install-content {
            padding: 40px;
        }
        .btn-install {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 30px;
            border-radius: 25px;
        }
        .btn-install:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .requirement-list {
            list-style: none;
            padding-left: 0;
        }
        .requirement-list li {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .requirement-list li i {
            width: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="install-container">
                    <!-- Header -->
                    <div class="text-center pt-4">
                        <h1 class="text-primary"><i class="fas fa-wifi me-2"></i>Hotspot Billing System</h1>
                        <p class="text-muted">Complete the installation in 5 simple steps</p>
                    </div>

                    <!-- Step Indicators -->
                    <div class="px-5 pt-4">
                        <div class="step-indicator">
                            <?php foreach ($steps as $step_num => $step_name): ?>
                                <div class="step <?php echo $step_num < $current_step ? 'completed' : ($step_num == $current_step ? 'active' : ''); ?>">
                                    <div class="step-number"><?php echo $step_num; ?></div>
                                    <div class="step-title"><?php echo $step_name; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Installation Content -->
                    <div class="install-content">
                        <?php
                        switch ($current_step) {
                            case 1:
                                include 'check.php';
                                break;
                            case 2:
                                include 'database.php';
                                break;
                            case 3:
                                include 'router.php';
                                break;
                            case 4:
                                include 'admin.php';
                                break;
                            case 5:
                                include 'complete.php';
                                break;
                            default:
                                include 'check.php';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
        });
    </script>
</body>
</html>