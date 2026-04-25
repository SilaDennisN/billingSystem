<?php
require_once "error_handler.php";

// Access Error Demo
showError(
    'access',
    'Invalid Hotspot Access',
    'It looks like you didn\'t connect through the hotspot login page. Please connect to our WiFi network and try accessing the internet again.',
    [
        ['text' => '📶 Connect to WiFi', 'link' => '#'],
        ['text' => '🔄 Refresh Page', 'link' => 'javascript:location.reload()']
    ]
);
?>