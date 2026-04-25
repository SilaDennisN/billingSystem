<?php

/*
|--------------------------------------------------------------------------
| M-PESA CONFIG (HARDCODED)
|--------------------------------------------------------------------------
*/

define('MPESA_CONSUMER_KEY', 'YOUR_CONSUMER_KEY');
define('MPESA_CONSUMER_SECRET', 'YOUR_CONSUMER_SECRET');

define('MPESA_SHORTCODE', '174379'); // paybill or till
define('MPESA_PASSKEY', 'YOUR_PASSKEY');

define('MPESA_BASE_URL', 'https://api.safaricom.co.ke');

// change to your domain
define('MPESA_CALLBACK_URL', 'https://billing.inovatech.co.ke/core/webhooks.php');