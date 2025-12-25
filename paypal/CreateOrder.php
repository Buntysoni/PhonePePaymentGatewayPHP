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
$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$amount = isset($data['amount']) ? floatval($data['amount']) : 1.00;

if (empty($username) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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

    // log HTTP status for debugging (do not log tokens)
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    logMessage('OAuth HTTP CODE: ' . $httpCode);

    curl_close($ch);
    $json = json_decode($response, true);
    return $json['access_token'] ?? null;
}

function createPayPalOrder($accessToken, $amount, $currency = 'USD') {
    global $apiBase;
    $url = $apiBase . "/v2/checkout/orders";
    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($amount, 2, '.', '')
            ]
        ]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ]);

    // Enforce SSL verification using the system CA bundle for order call
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        logMessage('Create Order CURL ERROR: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    logMessage('Create Order HTTP CODE: ' . $httpCode);

    curl_close($ch);
    return json_decode($response, true);
}

$token = getAccessToken($clientId, $clientSecret);
if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Unable to get access token']);
    exit;
}

$orderResp = createPayPalOrder($token, $amount, 'USD');
logMessage('CREATE ORDER RESPONSE: ' . json_encode($orderResp));

if (isset($orderResp['id'])) {
    echo json_encode(['id' => $orderResp['id']]);
    exit;
}

echo json_encode(['error' => $orderResp]);

exit;
