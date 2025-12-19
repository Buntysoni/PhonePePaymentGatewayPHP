<?php
require_once __DIR__ . '/config.php';

// PayU posts back parameters to this URL (surl/furl). Validate hash to ensure integrity.
$posted = $_POST;
file_put_contents(PAYU_LOG_PATH, date('c') . " VERIFY_POST: " . json_encode($posted) . "\n", FILE_APPEND);

$status = $posted['status'] ?? '';
$firstname = $posted['firstname'] ?? '';
$amount = $posted['amount'] ?? '';
$txnid = $posted['txnid'] ?? '';
$posted_hash = $posted['hash'] ?? '';
$key = $posted['key'] ?? '';
$productinfo = $posted['productinfo'] ?? '';
$email = $posted['email'] ?? '';

// Recreate hash sequence: salt|status|udf10..udf1|email|firstname|productinfo|amount|txnid|key
$udf = [];
for ($i=10;$i>=1;$i--) {
    $k = 'udf' . $i;
    $udf[] = isset($posted[$k]) ? $posted[$k] : '';
}
$reverse_seq = PAYU_MERCHANT_SALT . '|' . $status . '|' . implode('|', $udf) . '|' . $email . '|' . $firstname . '|' . $productinfo . '|' . $amount . '|' . $txnid . '|' . $key;
$calc_hash = strtolower(hash('sha512', $reverse_seq));

$is_hash_valid = ($calc_hash === $posted_hash);
$is_success = (strtolower($status) === 'success' || strtolower($status) === 'completed');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PayU Payment Status</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin:0; height:100vh; display:flex; justify-content:center; align-items:center; background: linear-gradient(135deg, #4a1e9e, #1f074f); }
        .result-container { background:white; border-radius:16px; padding:2rem; width:90%; max-width:420px; text-align:center; box-shadow:0 6px 20px rgba(0,0,0,0.1); margin:20px; }
        .icon { width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; color:white; font-size:36px; }
        .success { background:#4CAF50; }
        .failed { background:#ad0808; }
        .title { font-size:22px; margin-bottom:8px; }
        .details { color:#666; margin-bottom:18px; }
        .home-button { padding:12px 22px; background:#1f074f; color:white; border:none; border-radius:8px; text-decoration:none; display:inline-block; }
        pre { text-align:left; background:#f6f6f6; padding:12px; border-radius:8px; overflow:auto; }
    </style>
</head>
<body>
    <div class="result-container">
        <?php if ($is_hash_valid && $is_success): ?>
            <div class="icon success">✓</div>
            <div class="title">Payment Successful</div>
            <div class="details">Transaction ID: <strong><?php echo htmlspecialchars($txnid); ?></strong><br>Amount: <strong><?php echo htmlspecialchars($amount); ?></strong></div>
            <a class="home-button" href="/payu/index.html">Back to Home</a>
        <?php elseif ($is_hash_valid && !$is_success): ?>
            <div class="icon failed">✕</div>
            <div class="title">Payment Failed</div>
            <div class="details">Status: <strong><?php echo htmlspecialchars($status); ?></strong><br>Transaction ID: <strong><?php echo htmlspecialchars($txnid); ?></strong></div>
            <a class="home-button" href="/payu/index.html">Back to Home</a>
        <?php else: ?>
            <div class="icon failed">!</div>
            <div class="title">Verification Error</div>
            <div class="details">Hash mismatch or invalid response.</div>
            <h4>Debug Info</h4>
            <pre><?php echo htmlspecialchars("calc_hash: $calc_hash\nposted_hash: $posted_hash\n\n" . json_encode($posted, JSON_PRETTY_PRINT)); ?></pre>
            <a class="home-button" href="/payu/index.html">Back to Home</a>
        <?php endif; ?>
    </div>
</body>
</html>

