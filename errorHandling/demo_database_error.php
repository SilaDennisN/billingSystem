<?php
require_once "error_handler.php";

showError(
    'database',
    'Database Connection Failed',
    'We\'re having trouble connecting to our database. Please try again in a moment. If the problem persists, contact support.',
    [
        ['text' => '🔄 Retry', 'link' => 'javascript:location.reload()'],
        ['text' => '📞 Contact Support', 'link' => 'mailto:support@inovatech.com']
    ]
);
?>