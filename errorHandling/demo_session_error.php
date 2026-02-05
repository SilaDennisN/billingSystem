<?php
require_once "error_handler.php";

showError(
    'session',
    'Session Expired',
    'Your session has expired due to inactivity. Please start over and connect to the hotspot again.',
    [
        ['text' => '🔄 Start Over', 'link' => '/'],
        ['text' => '📶 Reconnect', 'link' => '#']
    ]
);
?>