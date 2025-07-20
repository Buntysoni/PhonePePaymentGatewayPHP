<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Read request body
$input = file_get_contents('php://input');
if (empty($input)) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

$data = json_decode($input, true);

// Extract order ID
$orderId = $data['txnid'] ?? null;
if (!$orderId) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID is required']);
    exit;
}

// ---------- Config ----------

$clientId    = '221824db5358d75f20a38abd628122';
$clientSecret = 'cfsk_ma_test_c2396f942ba6b54192c36e27acef6ed9_1bb935c6';
$apiVersion   = '2022-09-01';
$apiUrl       = "https://sandbox.cashfree.com/pg/orders/$orderId/payments";

// ---------- Logger ----------
function logMessage($message) {
    $logFile = __DIR__ . "/api_log.txt";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - " . $message . PHP_EOL, FILE_APPEND);
}

// ---------- API CALL ----------
function getPaymentStatus($url, $clientId, $clientSecret, $apiVersion) {
    $headers = [
        "Accept: application/json",
        "x-client-id: $clientId",
        "x-client-secret: $clientSecret",
        "x-api-version: $apiVersion"
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        logMessage("CURL Error: " . curl_error($curl));
        return null;
    }

    curl_close($curl);
    return json_decode($response, true);
}

// ---------- Main Execution ----------

$response = getPaymentStatus($apiUrl, $clientId, $clientSecret, $apiVersion);
logMessage("CHECK PAYMENT STATUS for Order ID $orderId: " . json_encode($response));

if (is_array($response) && count($response) > 0) {
    $payment = $response[0];

    $methodKey = '';
    $methodDetails = [];

    if (isset($payment['payment_method']) && is_array($payment['payment_method'])) {
        $methodKey = array_key_first($payment['payment_method']);
        $methodDetails = $payment['payment_method'][$methodKey];
    }

    $orderData = [
            'orderId' => $payment['order_id'] ?? '',
            'state' => ($payment['payment_status'] == 'SUCCESS' ? 'COMPLETED' : $payment['payment_status']) ?? 'FAILED',
            'amount' => $payment['payment_amount'],
            'errorCode' => null,
            'udf1' => $payment['payment_time'] ?? '',
            'udf2' => $payment['bank_reference'] ?? ''
        ];
        
        echo json_encode(['status' => 'success', 'wpResponse' => $orderData]);
} else {
    echo json_encode([
        "status"  => "error",
        "message" => "No payment found or invalid response",
        "raw"     => $response
    ]);
}

?>