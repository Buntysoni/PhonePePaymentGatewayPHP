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

require_once __DIR__ . '/config.php';

$username = $data['username'] ?? 'Customer';
$email = $data['email'] ?? '';
$phone = $data['phone'] ?? '';
$amount = isset($data['amount']) ? floatval($data['amount']) : 1.00; // INR amount

function logMessage($message) {
    $logFile = __DIR__ . "/api_log.txt";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - " . $message . PHP_EOL, FILE_APPEND);
}

// Build success/cancel URLs based on current host (best-effort)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $scheme . '://' . $host;
$successUrl = $base . STRIPE_SUCCESS_PATH;
$cancelUrl = $base . STRIPE_CANCEL_PATH;

// Stripe expects amount in smallest currency unit (paise for INR)
$unitAmount = intval(round($amount * 100));

$postData = http_build_query([
    'payment_method_types[]' => 'card',
    'line_items[0][price_data][currency]' => 'inr',
    'line_items[0][price_data][product_data][name]' => 'Website Payment',
    'line_items[0][price_data][unit_amount]' => $unitAmount,
    'line_items[0][quantity]' => 1,
    'mode' => 'payment',
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl
]);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ":");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

// Handle CA bundle on Windows/PHP environments. If a local cacert.pem exists use it,
// otherwise disable verification for local testing (INSECURE - change for production).
$caPath = defined('STRIPE_CACERT_PATH') ? STRIPE_CACERT_PATH : (__DIR__ . '/cacert.pem');
if (file_exists($caPath)) {
    curl_setopt($ch, CURLOPT_CAINFO, $caPath);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
} else {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    logMessage('Warning: cacert.pem not found; SSL verification disabled. Install a CA bundle and enable verification for production.');
}

$response = curl_exec($ch);
if (curl_errno($ch)) {
    logMessage('CURL ERROR: ' . curl_error($ch));
}
curl_close($ch);

$decoded = json_decode($response, true);
logMessage('CREATE SESSION RESPONSE: ' . $response);

if (isset($decoded['id'])) {
    echo json_encode([
        'success' => true,
        'session_id' => $decoded['id'],
        'session' => $decoded
    ], JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    'success' => false,
    'error' => $decoded
], JSON_PRETTY_PRINT);
exit;

?>
