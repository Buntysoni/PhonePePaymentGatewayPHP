<?php
// Paytm test configuration (Staging)
define('PAYTM_MERCHANT_ID', 'UbQBHp36072387143767');
define('PAYTM_MERCHANT_KEY', 'A4llzOvIqSa5kr&J');
define('PAYTM_WEBSITE', 'WEBSTAGING');
define('PAYTM_INDUSTRY_TYPE', 'Retail');
define('PAYTM_CHANNEL_ID_WEB', 'WEB');
define('PAYTM_CHANNEL_ID_WAP', 'WAP');

// Staging endpoints (Using securestage.paytmpayments.com endpoints)
define('PAYTM_INITIATE_TXN_URL', 'https://securestage.paytmpayments.com/theia/api/v1/initiateTransaction');
define('PAYTM_SHOW_PAYMENT_PAGE', 'https://securestage.paytmpayments.com/theia/api/v1/showPaymentPage');
define('PAYTM_PROCESS_URL', 'https://securegw-stage.paytm.in/theia/processTransaction');
define('PAYTM_STATUS_URL', 'https://securegw-stage.paytm.in/merchant-status/getTxnStatus');

define('PAYTM_LOG_PATH', __DIR__ . '/api_log.txt');

function paytm_log($message) {
    file_put_contents(PAYTM_LOG_PATH, date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

?>