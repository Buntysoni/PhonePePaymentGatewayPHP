<?php
require_once __DIR__ . '/config.php';

$firstname = $_POST['firstname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$amount = $_POST['amount'] ?? '';
$productinfo = $_POST['productinfo'] ?? 'Product';

if (!$firstname || !$email || !$phone || !$amount) {
    echo 'Missing required fields.';
    exit;
}

// generate txnid
$txnid = 'txn' . substr(hash('sha256', mt_rand() . microtime()), 0, 20);

// prepare hash (PayU standard: key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT)
// Build udf1..udf10 as empty strings
$udf = array_fill(1, 10, '');

// According to PayU, the payment request hash should be:
// sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10|salt)
$hash_elems = [PAYU_MERCHANT_KEY, $txnid, $amount, $productinfo, $firstname, $email];
for ($i = 1; $i <= 10; $i++) $hash_elems[] = $udf[$i];
$hash_elems[] = PAYU_MERCHANT_SALT;
$hash_string = implode('|', $hash_elems);
$hash = strtolower(hash('sha512', $hash_string));

// log payload
file_put_contents(PAYU_LOG_PATH, date('c') . " CREATE_REQUEST: " . json_encode([ 'txnid'=>$txnid, 'amount'=>$amount, 'firstname'=>$firstname, 'email'=>$email, 'productinfo'=>$productinfo, 'hash_string'=>$hash_string ]) . "\n", FILE_APPEND);

// compute base URL for surl/furl
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/');

// Build an auto-submitting form to PayU
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Redirecting to PayU...</title></head>
<body>
  <p>Redirecting to PayU test gateway...</p>
  <form id="payu_form" method="post" action="<?php echo PAYU_ACTION_URL; ?>">
    <input type="hidden" name="key" value="<?php echo htmlspecialchars(PAYU_MERCHANT_KEY); ?>" />
    <input type="hidden" name="txnid" value="<?php echo htmlspecialchars($txnid); ?>" />
    <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>" />
    <input type="hidden" name="productinfo" value="<?php echo htmlspecialchars($productinfo); ?>" />
    <input type="hidden" name="firstname" value="<?php echo htmlspecialchars($firstname); ?>" />
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>" />
    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>" />
    <input type="hidden" name="surl" value="<?php echo htmlspecialchars($base_url . '/VerifyPayment.php'); ?>" />
    <input type="hidden" name="furl" value="<?php echo htmlspecialchars($base_url . '/VerifyPayment.php'); ?>" />
    <?php for ($i=1;$i<=10;$i++): ?>
      <input type="hidden" name="udf<?php echo $i; ?>" value="" />
    <?php endfor; ?>
    <input type="hidden" name="hash" value="<?php echo htmlspecialchars($hash); ?>" />
    <noscript><input type="submit" value="Click here if not redirected"></noscript>
  </form>
  <script>document.getElementById('payu_form').submit();</script>
</body>
</html>
