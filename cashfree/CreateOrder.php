<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$input = file_get_contents('php://input');

// Validate required fields
if (empty($input)) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

$data = json_decode($input, true);

if (
    empty($data['username']) ||
    empty($data['email']) ||
    empty($data['phone']) ||
    !is_numeric($data['phone'])
) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing fields']);
    exit;
}

// ------------------- FUNCTIONS ----------------------

function generateUniqueOrderId($prefix = "UNLEIN") {
    return $prefix . time() . rand(100, 999);
}

function logMessage($message) {
    $logFile = __DIR__ . "/api_log.txt";
    $logEntry = "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function createCashfreeOrder($username, $email, $phone, $amount) {
    $appId = '221824db5358d75f20a38abd628122';
    $secretKey = 'cfsk_ma_test_c2396f942ba6b54192c36e27acef6ed9_1bb935c6';
    $orderId = generateUniqueOrderId();

    $url = "https://sandbox.cashfree.com/pg/orders";

    $payload = [
        "order_id" => $orderId,
        "order_amount" => $amount,
        "order_currency" => "INR",
        "order_note" => "Product Payment",
        "customer_details" => [
            "customer_id" => "USER_" . rand(1000, 9999),
            "customer_email" => $email,
            "customer_phone" => $phone,
            "customer_name" => $username
        ],
        "order_meta" => [
            "return_url" => "http://localhost:8080/verify.html?txnId=" . $orderId
        ]
    ];

    $headers = [
        "Content-Type: application/json",
        "x-client-id: $appId",
        "x-client-secret: $secretKey",
        "x-api-version: 2022-09-01"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        logMessage("CURL ERROR: " . curl_error($ch));
    }

    curl_close($ch);
    return json_decode($response, true);
}

// ------------------- MAIN EXECUTION ----------------------

$username = $data['username'];
$email    = $data['email'];
$phone    = $data['phone'];
$amount   = 1.00; // static or dynamic

$response = createCashfreeOrder($username, $email, $phone, $amount);
logMessage("PAYMENT REQUEST: " . json_encode($response));

// Success
if (isset($response['payment_session_id']) && isset($response['order_id'])) {
    echo json_encode([
        "success" => true,
        "order_id" => $response['order_id'],
        "payment_session_id" => $response['payment_session_id'],
        "redirect_url" => "https://sandbox.cashfree.com/pg/checkout?payment_session_id=" . $response['payment_session_id']
    ], JSON_PRETTY_PRINT);
    exit;
}

// Error
echo json_encode([
    "success" => false,
    "error_code" => $response['code'] ?? 'unknown_error',
    "error_message" => $response['message'] ?? 'Unexpected error from Cashfree'
], JSON_PRETTY_PRINT);
exit;