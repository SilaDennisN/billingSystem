<?php

/* ================================
   ENV CONFIG
================================ */

define('MPESA_ENV', 'live'); // change to 'live' when going production

function mpesaBaseUrl()
{
    return MPESA_ENV === 'live'
        ? "https://api.safaricom.co.ke"
        : "https://sandbox.safaricom.co.ke";
}

/* ================================
   GET ACCESS TOKEN
================================ */

function mpesaAccessToken($consumerKey, $consumerSecret)
{
    $credentials = base64_encode($consumerKey . ":" . $consumerSecret);

    $ch = curl_init(mpesaBaseUrl() . "/oauth/v1/generate?grant_type=client_credentials");

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: Basic $credentials"],
        CURLOPT_RETURNTRANSFER => true
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception("Token Error: " . curl_error($ch));
    }

    curl_close($ch);

    $data = json_decode($response, true);

    return $data['access_token'] ?? null;
}

/* ================================
   STK PUSH
================================ */

function stkPushMpesa($router_id, $amount, $phone, $reference)
{
    $config = loadMpesaConfig($router_id);

    $token = mpesaAccessToken(
        $config['consumer_key'],
        $config['consumer_secret']
    );

    if (!$token) {
        throw new Exception("Failed to get M-Pesa token");
    }

    $timestamp = date("YmdHis");

    $password = base64_encode(
        $config['shortcode'] .
        $config['passkey'] .
        $timestamp
    );

    $payload = [
        "BusinessShortCode" => $config['shortcode'],
        "Password" => $password,
        "Timestamp" => $timestamp,
        "TransactionType" => "CustomerBuyGoodsOnline",
        "Amount" => $amount,
        "PartyA" => $phone,
        "PartyB" => 4566091,
        "PhoneNumber" => $phone,
        "CallBackURL" => "https://billing.inovatech.co.ke/core/webhooks.php",
        "AccountReference" => $reference,
        "TransactionDesc" => "WiFi Payment"
    ];

    $ch = curl_init(mpesaBaseUrl() . "/mpesa/stkpush/v1/processrequest");

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception("STK Error: " . curl_error($ch));
    }

    curl_close($ch);

    return json_decode($response, true);
}