<?php

function makePayment($api_key, $email, $amount, $msisdn, $reference) {
    $payload = json_encode(compact('api_key', 'email', 'amount', 'msisdn', 'reference'));

    $ch = curl_init('https://api.pesaflux.co.ke/v1/initiatestk');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    echo $response;
}

$api_key = '';
$email = 'siladennis1256@gmail.com'; 
$amount = '1'; 
$msisdn = '0740770212';
$reference = 'test payment';
makePayment($api_key, $email, $amount, $msisdn, $reference);
?>
    