<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$input = file_get_contents('php://input');

if (empty($input)) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

$data = json_decode($input, true);

$TestTokenUrl = "https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token";
$LiveTokenUrl = "https://api.phonepe.com/apis/identity-manager/v1/oauth/token";

$TestCheckoutUrl = "https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/pay";
$LiveCheckoutUrl = "https://api.phonepe.com/apis/pg/checkout/v2/pay";

// Log function
function logMessage($message) {
    $logFile = __DIR__ . "/api_log.txt";  // Log file in the same folder as the script
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - " . $message . "\n", FILE_APPEND);
}

// Function to get access token
function getToken() {
    global $TestTokenUrl;

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $TestTokenUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'client_id=TEST-M22TSYZ1WMZLU_25041&client_version=1&client_secret=NjU4ZjFkZjAtNDY3ZC00NzcyLTg2OGMtM2Q1MmFkOThlOGY2&grant_type=client_credentials',
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/x-www-form-urlencoded'
        ),
      ));
      
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
      
      curl_close($curl);

    if ($error) {
        logMessage("CURL ERROR: $error");
    }

    $res = json_decode($response, true);
    
    return $res['access_token'] ?? null;
}

// Function to make payment request
function makePayment($token, $data) {

    global $TestCheckoutUrl;

    $amount = $data['amount'];
    $username = $data['username'];
    $phone = $data['phone'];

    logMessage("amount: $amount, username: $username, phone: $phone");

    $merchantOrderId = 'merch_' . date('YmdHis') . rand(1000, 9999);

    $paymentData = json_encode([
        "merchantOrderId" => $merchantOrderId,
        "amount" => $amount,
        "expireAfter" => 1200,
        "metaInfo" => [
            "udf1" => "$username",
            "udf2" => "$phone"
        ],
        "paymentFlow" => [
            "type" => "PG_CHECKOUT",
            "message" => "Payment message used for collect requests",
            "merchantUrls" => [
                "redirectUrl" => "http://localhost:8000/success.html?txnId=" . $merchantOrderId
            ]
        ]
    ]);

    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $TestCheckoutUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $paymentData,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: O-Bearer ' . $token
        ),
    ));

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    logMessage("PAYMENT REQUEST: HTTP Code: $httpCode, Response: $response");

    if ($error) {
        logMessage("CURL ERROR: $error");
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['redirectUrl'])) {
        echo json_encode(["redirectUrl" => $responseData['redirectUrl']]);
    } else {
        echo json_encode(["error" => "Failed to initiate payment"]);
    }
}

// Main Execution
$token = getToken();
if ($token) {
    logMessage("CURL TOKEN IS: $token");
    makePayment($token, $data);
} else {
    logMessage("ERROR: Failed to get access token");
    echo json_encode(["error" => "Failed to get access token"]);
}

?>
