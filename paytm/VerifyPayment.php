<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PaytmChecksum.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = file_get_contents('php://input');
$data = [];
if (!empty($input)) $data = json_decode($input, true);

// Paytm typically posts back via POST form fields
$post = $_POST;
// allow both POST form or JSON body
$payload = !empty($post) ? $post : $data;

$orderId = $payload['ORDERID'] ?? $payload['orderId'] ?? $payload['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['status' => 'error', 'message' => 'ORDERID is required']);
    exit;
}

// If checksum present in callback, verify it
if (isset($payload['CHECKSUMHASH'])) {
    $isValid = PaytmChecksum::verifySignature($payload, PAYTM_MERCHANT_KEY, $payload['CHECKSUMHASH']);
    paytm_log('CALLBACK RECEIVED: ' . json_encode(['payload' => $payload, 'checksum_valid' => $isValid]));
}

// 1) First try v3 order status API (preferred)
$v3_body = ['mid' => PAYTM_MERCHANT_ID, 'orderId' => $orderId];
$v3_signature = PaytmChecksum::generateSignature(json_encode($v3_body, JSON_UNESCAPED_SLASHES), PAYTM_MERCHANT_KEY);
$v3_post = ['body' => $v3_body, 'head' => ['signature' => $v3_signature]];
$v3_json = json_encode($v3_post, JSON_UNESCAPED_SLASHES);

paytm_log('V3 STATUS REQUEST SENT: ' . $v3_json);

$ch = curl_init('https://securestage.paytmpayments.com/v3/order/status');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $v3_json);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$resp = curl_exec($ch);
$curl_error = null;
if (curl_errno($ch)) {
    $curl_error = curl_error($ch);
    paytm_log('CURL ERROR V3 VerifyPayment: ' . $curl_error);
}
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded = json_decode($resp, true);
paytm_log('V3 VERIFY RESPONSE: ' . json_encode(['http_code' => $http_code, 'resp' => $decoded, 'raw' => $resp, 'err' => $curl_error]));

// If v3 returned a meaningful body, parse and return
if (is_array($decoded) && isset($decoded['body'])) {
    $body = $decoded['body'];
    // common fields
    $orderIdResp = $body['orderId'] ?? $orderId;
    $txnId = $body['txnId'] ?? ($body['txnId'] ?? '');
    $amount = $body['txnAmount'] ?? $body['txnAmt'] ?? 0;
    $status = strtoupper($body['orderStatus'] ?? $body['resultInfo']['resultStatus'] ?? ($body['status'] ?? 'FAILED'));

    // Normalize state
    $state = ($status === 'SUCCESS' || $status === 'TXN_SUCCESS' || $status === 'COMPLETED') ? 'COMPLETED' : $status;

    echo json_encode(['status' => 'success', 'wpResponse' => ['orderId' => $orderIdResp, 'state' => $state, 'amount' => $amount, 'errorCode' => $body['resultInfo']['resultCode'] ?? null, 'udf1' => $txnId, 'raw' => $body]]);
    exit;
}

// 2) Fallback: older status API
// Use lowercase keys 'mid' and 'orderId' as expected by the status API
$reqParams = ['mid' => PAYTM_MERCHANT_ID, 'orderId' => $orderId];
$checksum = PaytmChecksum::generateSignature($reqParams, PAYTM_MERCHANT_KEY);

$postData = ['body' => $reqParams, 'head' => ['signature' => $checksum]];
$json = json_encode($postData);

paytm_log('STATUS REQUEST SENT: ' . $json);

$ch = curl_init(PAYTM_STATUS_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$resp = curl_exec($ch);
if (curl_errno($ch)) {
    $curl_error = curl_error($ch);
    paytm_log('CURL ERROR VerifyPayment: ' . $curl_error);
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded = json_decode($resp, true);
paytm_log('VERIFY REQUEST: ' . json_encode(['req' => $postData, 'http_code' => $http_code, 'resp' => $decoded, 'raw' => $resp]));

if (!is_array($decoded)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid response from Paytm', 'http_code' => $http_code, 'raw' => $resp]);
    exit;
}

$payment = $decoded['body'] ?? $decoded;

// map response for compatibility
$orderStatus = strtoupper($payment['resultInfo']['resultStatus'] ?? $payment['STATUS'] ?? ($payment['status'] ?? 'FAILED'));
$txnAmount = $payment['TXNAMOUNT'] ?? $payment['txnAmount'] ?? ($payment['body']['txnAmount'] ?? 0);

$state = ($orderStatus === 'TXN_SUCCESS' || $orderStatus === 'SUCCESS') ? 'COMPLETED' : $orderStatus;

echo json_encode(['status' => 'success', 'wpResponse' => ['orderId' => $orderId, 'state' => $state, 'amount' => $txnAmount, 'errorCode' => $payment['resultInfo']['resultCode'] ?? ($payment['STATUS'] ?? null), 'udf1' => $payment['txnId'] ?? '', 'raw' => $decoded]]);
exit;
?>