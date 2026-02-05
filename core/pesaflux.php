<?php

function stkPush($amount, $phone, $reference)
{
    $api_key = "PSFXUoq8DOc2";
    $email   = "siladennis1256@gmail.com";

    $payload = json_encode([
        "api_key" => $api_key,
        "email" => $email,
        "amount" => $amount,
        "msisdn" => $phone,
        "reference" => $reference
    ]);

    $ch = curl_init('https://api.pesaflux.co.ke/v1/initiatestk');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}



function verifySTK($transaction_request_id)
{
    $api_key = "PSFXUoq8DOc2";
    $email   = "siladennis1256@gmail.com";

    $payload = json_encode([
        "api_key" => $api_key,
        "email" => $email,
        "transaction_request_id" => $transaction_request_id
    ]);

    $ch = curl_init('https://api.pesaflux.co.ke/v1/transactionstatus');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
