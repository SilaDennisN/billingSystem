<?php
require_once "../core/db.php";
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/pesaflux.php";
require_once __DIR__ . "/mpesa.php";
require_once __DIR__ . "/intasend.php";

/*
|--------------------------------------------------------------------------
| UNIFIED STK PUSH
|--------------------------------------------------------------------------
*/

function stkPush($provider, $router_id, $amount, $phone, $reference)
{
    $provider = strtolower(trim($provider));

    error_log("STEP 2 PROVIDER INSIDE stkPush: " . $provider);

    switch ($provider) {

        case 'mpesa':
            return stkPushMpesa($router_id, $amount, $phone, $reference);

        case 'intasend': // 🔥 ADD THIS
            return stkPushIntasend($router_id, $amount, $phone, $reference);

        case 'pesaflux':
            return stkPushPesaflux($router_id, $amount, $phone, $reference);

        default:
            throw new Exception("Unknown provider: " . $provider);
    }
}