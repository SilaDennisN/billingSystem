<?php

require_once "../../core/db.php";

define('APP_KEY', 'CLUwIlfqr5+QjC7Frk5ejfd9p7IDjGs22PgoGcGH9eM=');

/* ===============================
   DECRYPT FUNCTION
=============================== */

function decrypt_key($encrypted)
{
    $key = hash('sha256', APP_KEY);
    $iv  = substr(hash('sha256', APP_KEY), 0, 16);

    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

/* ===============================
   LOAD INTASEND CONFIG
=============================== */

function loadIntasendConfig($router_id)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT public_key_encrypted, secret_key_encrypted
        FROM user_api_keys uak
        JOIN user_router_access ura 
            ON ura.user_id = uak.user_id
        WHERE ura.router_id = ?
        AND uak.provider = 'intasend'
        LIMIT 1
    ");

    $stmt->execute([$router_id]);
    $api = $stmt->fetch();

    if (!$api) {
        die("❌ IntaSend config not found\n");
    }

    return [
        'public_key' => decrypt_key($api['public_key_encrypted']),
        'secret_key' => decrypt_key($api['secret_key_encrypted'])
    ];
}

/* ===============================
   TEST STK PUSH
=============================== */

function testIntasendSTK($router_id)
{
    $config = loadIntasendConfig($router_id);

    $phone = "254740770212"; // 🔥 change to real test number
    $amount = 10;
    $reference = "TEST-" . time();

    $payload = json_encode([
        "public_key"   => $config['public_key'],
        "currency"     => "KES",
        "amount"       => $amount,
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
        die("❌ CURL ERROR: " . curl_error($ch) . "\n");
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP CODE: " . $httpCode . "\n\n";
    echo "RAW RESPONSE:\n";
    echo $response . "\n\n";

    $decoded = json_decode($response, true);

    echo "PARSED RESPONSE:\n";
    print_r($decoded);
}

/* ===============================
   RUN TEST
=============================== */

$router_id = 1; // change to your router ID

testIntasendSTK($router_id);