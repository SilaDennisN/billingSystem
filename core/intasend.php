<?php

function stkPushIntasend($router_id, $amount, $phone, $reference)
{
    $config = loadIntasendConfig($router_id);

    error_log("USING INTASEND");

    // FIX PHONE FORMAT
    $phone = preg_replace('/\D/', '', $phone);
    if (strpos($phone, "0") === 0) {
        $phone = "254" . substr($phone, 1);
    }

    $payload = json_encode([
        "public_key"   => $config['public_key'],
        "currency"     => "KES",
        "amount"       => (float)$amount,
        "phone_number" => $phone,
        "api_ref"      => $reference,
        "method"       => "M-PESA"
    ]);

    $ch = curl_init("https://api.intasend.com/api/v1/payment/collection/");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $config['secret_key']
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log("IntaSend CURL ERROR: " . curl_error($ch));
        return null;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (!$result || (isset($result['status']) && $result['status'] === 'error')) {
        error_log("IntaSend FAILED RESPONSE: " . $response);
        return null;
    }

    return $result;
}