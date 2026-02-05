<?php
/**
 * Display a user-friendly error page
 * 
 * @param string $type - Type of error (access, connection, database, session, general)
 * @param string $title - Error title
 * @param string $message - Detailed error message
 * @param array $actions - Optional action buttons [['text' => 'Button Text', 'link' => 'url']]
 */
function showError($type = 'general', $title = 'Oops! Something went wrong', $message = 'An unexpected error occurred.', $actions = []) {
    
    // Default actions if none provided
    if (empty($actions)) {
        $actions = [
            ['text' => '← Go Back', 'link' => 'javascript:history.back()']
        ];
    }
    
    // Icon based on error type
    $icons = [
        'access' => '🔒',
        'connection' => '🔌',
        'database' => '💾',
        'session' => '⏱️',
        'payment' => '💳',
        'network' => '📡',
        'general' => '⚠️'
    ];
    
    $icon = $icons[$type] ?? $icons['general'];
    
    // Color scheme based on error type
    $colors = [
        'access' => ['primary' => '#FF6B6B', 'secondary' => '#C92A2A'],
        'connection' => ['primary' => '#FFA94D', 'secondary' => '#E8590C'],
        'database' => ['primary' => '#845EF7', 'secondary' => '#5F3DC4'],
        'session' => ['primary' => '#20C997', 'secondary' => '#0CA678'],
        'payment' => ['primary' => '#339AF0', 'secondary' => '#1864AB'],
        'network' => ['primary' => '#FF8787', 'secondary' => '#FA5252'],
        'general' => ['primary' => '#868E96', 'secondary' => '#495057']
    ];
    
    $colorScheme = $colors[$type] ?? $colors['general'];
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($title) ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                position: relative;
                overflow: hidden;
            }
            
            /* Animated background particles */
            body::before {
                content: '';
                position: absolute;
                width: 200%;
                height: 200%;
                background-image: 
                    radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
                    radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
                background-size: 50px 50px;
                animation: float 20s linear infinite;
            }
            
            @keyframes float {
                0% {
                    transform: translate(0, 0);
                }
                100% {
                    transform: translate(50px, 50px);
                }
            }
            
            .error-container {
                position: relative;
                background: white;
                border-radius: 24px;
                padding: 50px 40px;
                max-width: 520px;
                width: 100%;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                text-align: center;
                animation: slideIn 0.5s ease;
            }
            
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .error-icon {
                font-size: 80px;
                margin-bottom: 25px;
                display: inline-block;
                animation: bounce 2s ease-in-out infinite;
            }
            
            @keyframes bounce {
                0%, 100% {
                    transform: translateY(0);
                }
                50% {
                    transform: translateY(-10px);
                }
            }
            
            .error-code {
                display: inline-block;
                background: linear-gradient(135deg, <?= $colorScheme['primary'] ?>, <?= $colorScheme['secondary'] ?>);
                color: white;
                padding: 8px 20px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: 0.5px;
                margin-bottom: 20px;
                text-transform: uppercase;
            }
            
            h1 {
                font-size: 28px;
                color: #212529;
                margin-bottom: 15px;
                font-weight: 700;
            }
            
            .error-message {
                font-size: 16px;
                color: #6C757D;
                line-height: 1.6;
                margin-bottom: 35px;
            }
            
            .actions {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .btn {
                display: inline-block;
                padding: 14px 28px;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                font-size: 15px;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, <?= $colorScheme['primary'] ?>, <?= $colorScheme['secondary'] ?>);
                color: white;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            }
            
            .btn-secondary {
                background: #F1F3F5;
                color: #495057;
            }
            
            .btn-secondary:hover {
                background: #E9ECEF;
            }
            
            .divider {
                height: 1px;
                background: linear-gradient(to right, transparent, #DEE2E6, transparent);
                margin: 30px 0;
            }
            
            .help-text {
                font-size: 13px;
                color: #ADB5BD;
                margin-top: 25px;
            }
            
            .help-text a {
                color: <?= $colorScheme['primary'] ?>;
                text-decoration: none;
                font-weight: 600;
            }
            
            .help-text a:hover {
                text-decoration: underline;
            }
            
            /* Responsive */
            @media (max-width: 600px) {
                .error-container {
                    padding: 40px 25px;
                }
                
                .error-icon {
                    font-size: 60px;
                }
                
                h1 {
                    font-size: 24px;
                }
                
                .error-message {
                    font-size: 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon"><?= $icon ?></div>
            <div class="error-code"><?= strtoupper($type) ?> Error</div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <p class="error-message"><?= htmlspecialchars($message) ?></p>
            
            <div class="actions">
                <?php foreach ($actions as $index => $action): ?>
                    <a href="<?= htmlspecialchars($action['link']) ?>" 
                       class="btn <?= $index === 0 ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= htmlspecialchars($action['text']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <div class="divider"></div>
            
            <p class="help-text">
                Need help? Contact our support team at<br>
                <a href="mailto:support@inovatech.com">support@inovatech.com</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
