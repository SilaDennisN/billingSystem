<?php

require_once __DIR__ . "/config/config.php";


function sendSMS($phone, $message)
{
    $payload = json_encode([
        "message" => $message,
        "phone" => $phone,
        "sender_id" => SMS_SENDER_ID,
        "api_key" => SMS_API_KEY
    ]);

    $curl = curl_init(SMS_ENDPOINT);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($curl);

    if(curl_errno($curl)){
        error_log("SMS ERROR: ".curl_error($curl));
    }

    curl_close($curl);

    return $response;
}
