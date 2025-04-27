<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['orderId'] ?? '';

    if (empty($orderId)) {
        echo json_encode(["error" => "Order ID is required"]);
        exit;
    }

    $clientId = 'YOURCLIENTID';    // Replace with your client ID
    $secretId = 'YOURSECRETID';    // Replace with your client secret

    $url = "https://sandbox.cashfree.com/pg/orders/" . urlencode($orderId);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-version: 2022-09-01',
        'x-client-id: ' . $clientId,
        'x-client-secret: ' . $secretId
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        echo $response;
    } else {
        echo json_encode([
            "error" => "Failed to verify payment",
            "details" => $response
        ]);
    }
} else {
    echo json_encode(["error" => "Invalid request method."]);
}
?>
