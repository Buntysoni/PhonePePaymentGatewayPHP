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

// Log function
function logMessage($message) {
    $logFile = __DIR__ . "/api_log.txt";  // Log file in the same folder as the script
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - " . $message . "\n", FILE_APPEND);
}

$TestTokenUrl = "https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token";
$LiveTokenUrl = "https://api.phonepe.com/apis/identity-manager/v1/oauth/token";

$TestOrderUrl = "https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/order/";
$LiveOrderUrl = "https://api.phonepe.com/apis/pg/checkout/v2/order/";

// Function to get access token
function getToken() {
    global $LiveTokenUrl;

    $curl = curl_init();    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $LiveTokenUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'client_id=SU2505222105162650976562&client_version=1&client_secret=09241490-50b2-4570-83ce-22fe4a1feaa3&grant_type=client_credentials',
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/x-www-form-urlencoded'
        ),
    ));
      
    $response = curl_exec($curl);
    curl_close($curl);

    $res = json_decode($response, true);
    
    return $res['access_token'] ?? null;
}

// Function to check payment status
function checkStatus($token, $txnid) {
    global $LiveOrderUrl;

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $LiveOrderUrl . $txnid .'/status',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: O-Bearer ' . $token
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}

// Function to send order status to WordPress API
function sendToWordPress($orderData) {
    $wp_api_url = 'https://jdfindia.com/wp-json/custom-api/v1/save-order/';

    $payload = json_encode([
        'orderId' => $orderData['orderId'],
        'state' => $orderData['state'],
        'amount' => $orderData['amount'],
        'errorCode' => $orderData['errorCode'] ?? null,
        'metaInfo' => [
            'udf1' => $orderData['udf1'] ?? '',
            'udf2' => $orderData['udf2'] ?? '',
        ]
    ]);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $wp_api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    logMessage("WORDPRESS API RESPONSE: $response");

    return json_decode($response, true);
}

function isOrderAlreadyProcessed($orderId) {
    $logFile = __DIR__ . "/processed_orders.txt";
    $processedOrders = file_exists($logFile) ? file($logFile, FILE_IGNORE_NEW_LINES) : [];

    return in_array($orderId, $processedOrders);
}

function markOrderAsProcessed($orderId) {
    $logFile = __DIR__ . "/processed_orders.txt";
    file_put_contents($logFile, $orderId . PHP_EOL, FILE_APPEND);
}

// Main Execution
$token = getToken();
if ($token) {
    $txnid = $data['txnid'] ?? null;

    if (!$txnid) {
        logMessage("ERROR: Transaction ID missing");
        echo json_encode(["error" => "Transaction ID missing"]);
        exit;
    }

    $statusResponse = checkStatus($token, $txnid);

    if (isset($statusResponse['orderId'])) {
        $orderData = [
            'orderId' => $statusResponse['orderId'],
            'state' => $statusResponse['state'],
            'amount' => $statusResponse['amount'],
            'errorCode' => $statusResponse['errorCode'] ?? null,
            'udf1' => $statusResponse['metaInfo']['udf1'] ?? '',
            'udf2' => $statusResponse['metaInfo']['udf2'] ?? ''
        ];

        // Send data to WordPress API
        $wpResponse = sendToWordPress($orderData);

        if (isOrderAlreadyProcessed($orderData['orderId']) == '1') {
	        if ($orderData['state'] === 'COMPLETED') {
                $amount = $orderData['amount'] ?? 0;
                $transactionId = $orderData['orderId'] ?? 'UNKNOWN';

                $wpResponse["username"] = $orderData['udf1'] ?? 'Unknown User';
                $wpResponse["phone"] = $orderData['udf2'] ?? 'Unknown Phone';
                $wpResponse["amount"] = $amount;
	        }
            markOrderAsProcessed($orderData['orderId']);
        }
        $wpResponse["state"] = $orderData['state'];
        echo json_encode(['status' => 'success', 'wpResponse' => $wpResponse]);
    } else {
        logMessage("ERROR: Invalid response from PhonePe");
        echo json_encode(["error" => "Invalid response from PhonePe"]);
    }
} else {
    logMessage("ERROR: Failed to get access token");
    echo json_encode(["error" => "Failed to get access token"]);
}

?>
