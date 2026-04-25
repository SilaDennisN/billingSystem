<?php

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/config/config.php";

/* =================================================
   INTASEND STATUS CHECK
================================================= */

function checkIntasendStatus($invoice_id, $secret_key)
{
    $url = "https://api.intasend.com/api/v1/payment/status/" . $invoice_id;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $secret_key,
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log("IntaSend VERIFY CURL ERROR: " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    return json_decode($response, true);
}

/* =================================================
   AUTO ACTIVATE PAYMENT
================================================= */

function verifyAndActivateIntasend($payment)
{
    $config = loadIntasendConfig($payment['router_id']);

    $invoice_id = $payment['transaction_request_id'];

    $data = checkIntasendStatus($invoice_id, $config['secret_key']);

    if (!$data || !isset($data['invoice'])) {
        return "PENDING";
    }

    $state = $data['invoice']['state'] ?? 'PENDING';

    if ($state !== 'COMPLETE') {
        return $state;
    }

    global $pdo;

    // Mark payment as used
    $pdo->prepare("
        UPDATE payments
        SET status='used', confirmed_at=NOW()
        WHERE payment_id=?
    ")->execute([$payment['payment_id']]);

    return "ACTIVE";
}