<?php
require_once "error_handler.php";

showError(
    'payment',
    'Payment Processing Failed',
    'We couldn\'t process your M-Pesa payment. Please check your phone number and try again. Make sure you have sufficient balance.',
    [
        ['text' => '🔄 Try Again', 'link' => 'javascript:history.back()'],
        ['text' => '💬 Get Help', 'link' => 'mailto:support@inovatech.com?subject=Payment Issue']
    ]
);
?>