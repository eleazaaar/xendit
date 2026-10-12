<?php

require_once "config.php";

$referenceId = "TEST-" . time();

$data = [
    "reference_id" => $referenceId,
    "type" => "PAY",
    "country" => "PH",
    "currency" => "PHP",
    "request_amount" => 100,
    "capture_method" => "AUTOMATIC",
    "channel_code" => "GCASH",
    "channel_properties" => [
        "failure_return_url" => "http://localhost/xendit/index.php",
        "success_return_url" => "http://localhost/xendit/success.php"
    ],
    "description" => "Xendit PHP Test Payment"
];

$ch = curl_init("https://api.xendit.co/v3/payment_requests");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode(XENDIT_SECRET_KEY . ":"),
    "Content-Type: application/json",
    "api-version: 2024-11-11"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    die("cURL Error: " . curl_error($ch));
}

curl_close($ch);

$result = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300) {
    echo "<h2>Xendit API Error</h2>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    exit;
}

/*
 * Xendit returns an action containing the URL
 * where the customer should continue the payment.
 */
$paymentUrl = null;

if (!empty($result["actions"])) {
    foreach ($result["actions"] as $action) {
        if (
            isset($action["type"]) &&
            $action["type"] === "REDIRECT_CUSTOMER"
        ) {
            $paymentUrl = $action["value"];
            break;
        }
    }
}

if (!$paymentUrl) {
    echo "<h2>Payment request created</h2>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    exit;
}

header("Location: " . $paymentUrl);
exit;