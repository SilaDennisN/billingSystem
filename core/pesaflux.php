<?php



/* =================================================
   SEND STK PUSH
================================================= */

function stkPushPesaflux($router_id, $amount, $phone, $reference)
{
    loadPesafluxConfig($router_id);
    

    error_log("USING PESAFLUX");
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
        CURLOPT_TIMEOUT => 30,

        // 🔥 ADD THESE (VERY IMPORTANT)
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);

    // 🔥 CHECK HARD FAILURE
    if ($response === false) {
        $error = curl_error($ch);
        error_log("PesaFlux CURL ERROR: " . $error);
        curl_close($ch);
        return null;
    }

    // 🔥 CHECK HTTP STATUS
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    error_log("PesaFlux HTTP CODE: " . $httpCode);

    // 🔥 LOG RAW RESPONSE
    error_log("PesaFlux RAW RESPONSE: " . $response);

    curl_close($ch);

    // 🔥 HANDLE EMPTY RESPONSE
    if (empty($response)) {
        error_log("PesaFlux EMPTY RESPONSE");
        return null;
    }

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
