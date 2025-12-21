<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PaytmChecksum.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
// Accept JSON or form
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = file_get_contents('php://input');
$data = [];
if (!empty($input)) {
    $data = json_decode($input, true);
}

$name = trim($data['username'] ?? $_POST['username'] ?? '');
$email = trim($data['email'] ?? $_POST['email'] ?? '');
$phone = trim($data['phone'] ?? $_POST['phone'] ?? '');
$amount = $data['amount'] ?? $_POST['amount'] ?? '1.00';

if (!$name || !$email || !$phone || !is_numeric($amount)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid fields']);
    exit;
}

// generate order id
$orderId = 'ORDER' . time() . rand(100, 999);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/');
// Redirect user to front-end Verify page after payment; the page will call server-side VerifyPayment.php to confirm status
$callbackUrl = $base . '/Verify.html?ORDERID=' . $orderId;

// Build initiate transaction request (per Paytm docs)
$body = [
    'requestType' => 'Payment',
    'mid' => PAYTM_MERCHANT_ID,
    'websiteName' => PAYTM_WEBSITE,
    'orderId' => $orderId,
    'callbackUrl' => $callbackUrl,
    'txnAmount' => [ 'value' => number_format((float)$amount, 2, '.', ''), 'currency' => 'INR' ],
    'userInfo' => [ 'custId' => 'CUST_' . rand(1000, 9999) ]
];

// Generate signature over JSON body string
$checksum = PaytmChecksum::generateSignature(json_encode($body, JSON_UNESCAPED_SLASHES), PAYTM_MERCHANT_KEY);

$paytmParams = [ 'body' => $body, 'head' => [ 'signature' => $checksum ] ];
$post_data = json_encode($paytmParams, JSON_UNESCAPED_SLASHES);

$url = PAYTM_INITIATE_TXN_URL . '?mid=' . urlencode(PAYTM_MERCHANT_ID) . '&orderId=' . urlencode($orderId);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

$response = curl_exec($ch);
$curl_err = null;
if (curl_errno($ch)) {
    $curl_err = curl_error($ch);
    paytm_log('CURL ERROR InitiateTxn: ' . $curl_err);
}
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded = json_decode($response, true);
paytm_log('INITIATE TXN REQUEST: ' . json_encode(['url' => $url, 'post' => $paytmParams, 'http_code' => $http_code, 'resp' => $decoded, 'err' => $curl_err]));

// Success -> txnToken in response.body
if (is_array($decoded) && isset($decoded['body']) && isset($decoded['body']['txnToken'])) {
    $txnToken = $decoded['body']['txnToken'];
    $showUrl = PAYTM_SHOW_PAYMENT_PAGE . '?mid=' . urlencode(PAYTM_MERCHANT_ID) . '&orderId=' . urlencode($orderId);
    echo json_encode(['success' => true, 'orderId' => $orderId, 'txnToken' => $txnToken, 'showPaymentPage' => $showUrl, 'raw' => $decoded]);
    exit;
}

// fallback error
echo json_encode(['success' => false, 'message' => $decoded['body']['resultInfo']['resultMsg'] ?? $decoded['message'] ?? 'Failed to initiate transaction', 'http_code' => $http_code, 'raw' => $decoded]);
exit;
?>