<?php
// PayPal configuration - keeps credentials and API base in one place
// Raw values (edit these to match your PayPal app). Whitespace will be trimmed.
$config = [
    'client_id' => '--client_id--',
    'client_secret' => '--client_secret--',
    // Use the sandbox REST API base. If your credentials require a different host,
    // try 'https://api.sandbox.paypal.com' instead of 'api-m'.
    'api_base' => 'https://api.sandbox.paypal.com',
    'currency' => 'USD'
];

// Trim whitespace from values to avoid copy/paste issues
array_walk($config, function (&$v, $k) {
    if (is_string($v)) {
        $v = trim($v);
    }
});

// If this file is requested directly, return JSON for client use.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    echo json_encode($config);
    exit;
}

// When included, return the array for server-side use
return $config;
