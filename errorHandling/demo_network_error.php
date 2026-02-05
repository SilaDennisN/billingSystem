<?php
require_once "error_handler.php";

showError(
    'network',
    'Network Communication Error',
    'There was an error communicating with the router. The network might be experiencing issues. Please try again shortly.',
    [
        ['text' => '🔄 Try Again', 'link' => 'javascript:location.reload()'],
        ['text' => '📊 Check Status', 'link' => '#']
    ]
);
?>