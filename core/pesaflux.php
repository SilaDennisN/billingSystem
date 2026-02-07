<?php

require_once __DIR__ . "/config/config.php";


/* =================================================
   SEND STK PUSH
================================================= */

function stkPush($amount, $phone, $reference)
{
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

function verifySTK($transaction_request_id)
{
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
