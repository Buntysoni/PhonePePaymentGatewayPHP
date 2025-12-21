<?php
/**
 * Quick test endpoint to verify whether a given MID/KEY are valid on Paytm staging.
 * Usage (GET): /paytm/TestMID.php?mid=...&key=...&orderId=ORDER123
 */
require_once __DIR__ . '/PaytmChecksum.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$mid = $_GET['mid'] ?? 'WorldP64425807474247';
$key = $_GET['key'] ?? 'kbzk0W%2A2oG';
$orderId = $_GET['orderId'] ?? ('TESTORDER' . time());

// Build request
$reqParams = ['mid' => $mid, 'orderId' => $orderId];
$signature = PaytmChecksum::generateSignature($reqParams, $key);
$postData = ['body' => $reqParams, 'head' => ['signature' => $signature]];

$json = json_encode($postData);

// send to Paytm status URL
$ch = curl_init(PAYTM_STATUS_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$errstr = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// log
file_put_contents(__DIR__ . '/api_log.txt', date('Y-m-d H:i:s') . ' - TEST MID: ' . json_encode(['mid' => $mid, 'orderId' => $orderId, 'http_code' => $http_code, 'errno' => $errno, 'err' => $errstr, 'resp' => $resp]) . PHP_EOL, FILE_APPEND | LOCK_EX);

if ($errno) {
    echo json_encode(['status' => 'error', 'message' => 'Curl error', 'error' => $errstr]);
    exit;
}

$decoded = json_decode($resp, true);

echo json_encode(['status' => 'ok', 'mid_test' => $mid, 'orderId' => $orderId, 'http_code' => $http_code, 'response' => $decoded, 'raw' => $resp]);

?>