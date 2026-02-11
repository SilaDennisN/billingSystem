<?php
// === SANDBOX CONFIG ===
$api_key = 'SFTPdmWzsvoU';
$email   = 'pesafluxsandbox@gmail.com';
$amount  = 10; // KES
$msisdn  = '0740770212'; // use 07XXXXXXXX format
$reference = 'TEST_PAYMENT';

// === BUILD PAYLOAD ===
$payload = json_encode(compact('api_key', 'email', 'amount', 'msisdn', 'reference'));

// === CURL ===
$ch = curl_init('https://api.pesaflux.co.ke/v1/initiatestk');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Expect:' // fixes some 415 errors
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);

if ($response === false) {
    die('cURL error: ' . curl_error($ch));
}

curl_close($ch);

// === OUTPUT ===
echo "Payload sent:\n$payload\n\n";
echo "Response received:\n$response";
