<?php
// Stripe configuration
// Replace these with your own test/live keys as appropriate
define('STRIPE_PUBLISHABLE_KEY', 'PublishableKeyHere');
define('STRIPE_SECRET_KEY', 'SecretKeyHere');

// API base (usually https://api.stripe.com/v1)
define('STRIPE_API_BASE', 'https://api.stripe.com/v1');

// Paths used for redirect; these will be appended to detected host
define('STRIPE_SUCCESS_PATH', '/stripe/success.html?session_id={CHECKOUT_SESSION_ID}');
define('STRIPE_CANCEL_PATH', '/stripe/index.html');

// Optional: path to cacert.pem for cURL on Windows. If empty, code may disable SSL verification (not recommended).
define('STRIPE_CACERT_PATH', __DIR__ . '/cacert.pem');

?>
