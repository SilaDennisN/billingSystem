<?php

/* =================================================
   SEND STK PUSH
================================================= */

define('APP_KEY', 'CLUwIlfqr5+QjC7Frk5ejfd9p7IDjGs22PgoGcGH9eM=');
/*
|--------------------------------------------------------------------------
| API CONSTANTS
|--------------------------------------------------------------------------
| NEVER expose this file publicly.
| Block access using .htaccess
*/

function decrypt_key($encrypted)
{
    $key = hash('sha256', APP_KEY);
    $iv  = substr(hash('sha256', APP_KEY), 0, 16);

    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

// require_once "../../core/db.php";

function loadPesafluxConfig($router_id)
{
    global $pdo;

   //  error_log("loadPesafluxConfig: router_id=$router_id"); // <-- log router ID

    $stmt = $pdo->prepare("
        SELECT uak.api_key_encrypted, uak.email
        FROM user_api_keys uak
        JOIN user_router_access ura ON ura.user_id = uak.user_id
        WHERE ura.router_id = ?
        AND uak.provider = 'pesaflux'
        LIMIT 1
    ");

    $stmt->execute([$router_id]);
    $api = $stmt->fetch();

    if (!$api) {
        error_log("Pesaflux API key not found for router_id=$router_id");
        throw new Exception("Pesaflux API key not found for router");
    }

    $decrypted = decrypt_key($api['api_key_encrypted']);
    if (!$decrypted) {
        error_log("Failed to decrypt API key for router_id=$router_id");
        throw new Exception("API key decryption failed");
    }

    if (!defined('PESAFLUX_API_KEY')) {
        define('PESAFLUX_API_KEY', $decrypted);
    }

    if (!defined('PESAFLUX_EMAIL')) {
        define('PESAFLUX_EMAIL', $api['email']);
    }

   //  error_log("PESAFLUX_API_KEY loaded successfully for router_id=$router_id");
}

// loadPesafluxConfig();

/* ===============================
   PESAFLUX
=============================== */

define('PESAFLUX_STK_URL', 'https://api.pesaflux.co.ke/v1/initiatestk');
define('PESAFLUX_VERIFY_URL', 'https://api.pesaflux.co.ke/v1/transactionstatus');


/* ===============================
   FLUX SMS
=============================== */

define('SMS_API_KEY', 'amllssboszcuonqrfqkifndypwgiqnwfghhcccix');
define('SMS_SENDER_ID', 'FLUXSMS');
define('SMS_ENDPOINT', 'https://api.fluxsms.co.ke/sendsms');


function stkPush($router_id, $amount, $phone, $reference)
{
    loadPesafluxConfig($router_id);
    
    $payload = json_encode([
        "api_key"   => PESAFLUX_API_KEY,
        "email"     => PESAFLUX_EMAIL,
        "amount"    => $amount,
        "msisdn"    => $phone,
        "reference" => $reference
    ]);

    $ch = curl_init(PESAFLUX_STK_URL);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if(curl_errno($ch)){
        error_log("PesaFlux STK Error: ".curl_error($ch));
    }

    curl_close($ch);

    return json_decode($response, true);
}



/* =================================================
   VERIFY STK
================================================= */

function verifySTK($router_id, $transaction_request_id)
{
    loadPesafluxConfig($router_id);
    $payload = json_encode([
        "api_key" => PESAFLUX_API_KEY,
        "email" => PESAFLUX_EMAIL,
        "transaction_request_id" => $transaction_request_id
    ]);

    $ch = curl_init(PESAFLUX_VERIFY_URL);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if(curl_errno($ch)){
        error_log("PesaFlux Verify Error: ".curl_error($ch));
    }

    curl_close($ch);

    return json_decode($response, true);
}
