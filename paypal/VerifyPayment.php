<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$input = file_get_contents('php://input');
if (empty($input)) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$data = json_decode($input, true);
$orderID = $data['orderID'] ?? '';

if (empty($orderID)) {
    echo json_encode(['success' => false, 'message' => 'Missing orderID']);
    exit;
}

function logMessage($message) {
    $logFile = __DIR__ . "/api_log.txt";
    $logEntry = "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Load config
$config = include __DIR__ . '/config.php';

// PayPal credentials from config
$clientId = $config['client_id'];
$clientSecret = $config['client_secret'];
$apiBase = rtrim($config['api_base'], '/');

// Log the client id being used (safe to log)
logMessage('Using client_id: ' . ($clientId ?: '[empty]'));

function getAccessToken($clientId, $clientSecret) {
    global $apiBase;
    $url = $apiBase . "/v1/oauth2/token";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $clientSecret);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: application/json",
        "Accept-Language: en_US"
    ]);

    // Enforce SSL verification using the system CA bundle
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    // Log the raw response for debugging but redact access_token if present
    if ($response !== false) {
        $safe = preg_replace('/"access_token"\s*:\s*"[^"]+"/i', '"access_token":"[REDACTED]"', $response);
        logMessage('OAuth RESPONSE: ' . $safe);
    }
    if (curl_errno($ch)) {
        logMessage('OAuth CURL ERROR: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);
    $json = json_decode($response, true);
    return $json['access_token'] ?? null;
}

function captureOrder($accessToken, $orderID) {
    global $apiBase;
    $url = $apiBase . "/v2/checkout/orders/" . urlencode($orderID) . "/capture";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);

    // Enforce SSL verification using the system CA bundle for capture call
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        logMessage('Capture CURL ERROR: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    logMessage('Capture HTTP CODE: ' . $httpCode);

    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($clientId, $clientSecret);
if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Unable to get access token']);
    exit;
}

$captureResp = captureOrder($token, $orderID);
logMessage('CAPTURE RESPONSE for ' . $orderID . ': ' . json_encode($captureResp));

// Basic success check
if (isset($captureResp['status']) && $captureResp['status'] === 'COMPLETED') {
    echo json_encode($captureResp);
    exit;
}

if (isset($captureResp['details'][0]['issue']) 
    && $captureResp['details'][0]['issue'] === 'ORDER_ALREADY_CAPTURED') {

    // Fetch order details instead of trying again
    $orderUrl = $apiBase . "/v2/checkout/orders/" . urlencode($orderID);

    $ch = curl_init($orderUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $orderResp = curl_exec($ch);
    curl_close($ch);

    echo $orderResp;
    exit;
}


http_response_code(400);
echo json_encode($captureResp);

exit;
