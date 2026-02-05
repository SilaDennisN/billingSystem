<?php
require_once "error_handler.php";

showError(
    'connection',
    'Router Connection Failed',
    'We couldn\'t establish a connection to the network router. This is usually temporary. Please wait a moment and try again.',
    [
        ['text' => '🔄 Retry Connection', 'link' => 'javascript:location.reload()'],
        ['text' => '📞 Report Issue', 'link' => 'mailto:support@inovatech.com?subject=Router Connection Failed']
    ]
);
?>