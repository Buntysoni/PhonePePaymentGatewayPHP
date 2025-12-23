<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = file_get_contents('php://input');
if (empty($input)) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

$data = json_decode($input, true);
$sessionId = $data['session_id'] ?? null;

if (!$sessionId) {
    echo json_encode(['status' => 'error', 'message' => 'session_id is required']);
    exit;
}

require_once __DIR__ . '/config.php';

function logMessage($message) {
    $logFile = __DIR__ . "/api_log.txt";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - " . $message . PHP_EOL, FILE_APPEND);
}

$url = STRIPE_API_BASE . '/checkout/sessions/' . urlencode($sessionId) . '?expand[]=payment_intent';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ":");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
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
logMessage('VERIFY SESSION RESPONSE for ' . $sessionId . ': ' . $response);

if (!$decoded) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid response from Stripe', 'raw' => $response]);
    exit;
}

$result = [
    'status' => 'success',
    'session' => $decoded,
];

// Provide a concise status summary if payment_intent exists
if (isset($decoded['payment_intent']) && is_array($decoded['payment_intent'])) {
    $pi = $decoded['payment_intent'];
    $result['payment_status'] = $pi['status'] ?? null;
    $result['amount_received'] = $pi['amount_received'] ?? null;
    $result['currency'] = $pi['currency'] ?? null;
}

echo json_encode($result, JSON_PRETTY_PRINT);
exit;

?>
