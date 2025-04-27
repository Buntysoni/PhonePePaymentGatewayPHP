<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['Email'] ?? '';
    $phone = $_POST['Phone'] ?? '';

    $order_id = uniqid('order_', true);
    $customer_id = time() . rand(1000, 9999);

    $clientId = 'YOURCLIENTID';    // Replace with your client ID
    $secretId = 'YOURSECRETID';    // Replace with your client secret

    $data = [
        "order_id" => $order_id,
        "order_amount" => 100.00,
        "order_currency" => "INR",
        "customer_details" => [
            "customer_id" => $customer_id,
            "customer_email" => $email,
            "customer_phone" => $phone,
            "customer_name" => $name
        ]
    ];

    $ch = curl_init('https://sandbox.cashfree.com/pg/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-version: 2022-09-01',
        'x-client-id: ' . $clientId,
        'x-client-secret: ' . $secretId
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $responseData = json_decode($response, true);
        $sessionId = $responseData['payment_session_id'] ?? null;
        echo json_encode([
            "payment_session_id" => $sessionId,
            "order_id" => $order_id
        ]);
    } else {
        echo json_encode([
            "error" => $response,
            "order_id" => $order_id
        ]);
    }
} else {
    echo json_encode(["error" => "Invalid request method."]);
}
?>