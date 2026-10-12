<?php
require_once "database.php";
require_once "config.php";

date_default_timezone_set("Asia/Manila");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$conn = Database::connection();

$amount = isset($_POST["amount"]) ? (float) $_POST["amount"] : 0;
$channelCode = isset($_POST["channel_code"]) ? strtoupper(trim($_POST["channel_code"])) : "";

$allowedChannels = ["GCASH", "PAYMAYA", "CARDS"];

if (!is_finite($amount) || $amount < 1 || $amount > 50000) {
    die("Invalid payment amount.");
}

if (!in_array($channelCode, $allowedChannels, true)) {
    die("Invalid payment method.");
}

if (!defined("XENDIT_SECRET_KEY") || !defined("BASE_URL")) {
    die("Xendit configuration is missing.");
}

$walletNumber = "";

if ($channelCode === "GCASH") {
    $walletNumber = trim($_POST["gcash_number"] ?? "");
} elseif ($channelCode === "PAYMAYA") {
    $walletNumber = trim($_POST["maya_number"] ?? "");
}

if ($channelCode !== "CARDS" && !preg_match('/^09[0-9]{9}$/', $walletNumber)) {
    die("Enter a valid 11-digit mobile number.");
}

if ($channelCode !== "CARDS" && ($_POST["wallet_confirmed"] ?? "") !== "1") {
    die("Please confirm your wallet payment before continuing.");
}

$referenceId = "ORDER-" . strtoupper(bin2hex(random_bytes(8)));
$paymentMethod = $channelCode === "PAYMAYA" ? "MAYA" : $channelCode;
$escapedReference = $conn->real_escape_string($referenceId);
$escapedMethod = $conn->real_escape_string($paymentMethod);
$escapedWallet = $conn->real_escape_string($walletNumber);
$formattedAmount = number_format($amount, 2, ".", "");

$sql = "INSERT INTO orders (reference_id, amount, payment_method, wallet_number, status) VALUES ('$escapedReference', '$formattedAmount', '$escapedMethod', " . ($walletNumber === "" ? "NULL" : "'$escapedWallet'") . ", 'PENDING')";

if (!$conn->query($sql)) {
    error_log("Order insert failed: " . $conn->error);
    $conn->close();
    die("Could not create the order.");
}

$successUrl = BASE_URL . "/success.php?reference_id=" . urlencode($referenceId);
$cancelUrl = BASE_URL . "/failure.php?reference_id=" . urlencode($referenceId);

if ($channelCode === "CARDS") {
    $payload = [
        "reference_id" => $referenceId,
        "session_type" => "PAY",
        "mode" => "PAYMENT_LINK",
        "amount" => (float) $formattedAmount,
        "currency" => "PHP",
        "country" => "PH",
        "locale" => "en",
        "capture_method" => "AUTOMATIC",
        "allowed_payment_channels" => ["CARDS"],
        "description" => "Test card payment " . $referenceId,
        "success_return_url" => $successUrl,
        "cancel_return_url" => $cancelUrl
    ];

    $endpoint = "https://api.xendit.co/sessions";
} else {
    $payload = [
        "reference_id" => $referenceId,
        "type" => "PAY",
        "country" => "PH",
        "currency" => "PHP",
        "request_amount" => (float) $formattedAmount,
        "capture_method" => "AUTOMATIC",
        "channel_code" => $channelCode,
        "channel_properties" => [
            "success_return_url" => $successUrl,
            "failure_return_url" => $cancelUrl,
            "cancel_return_url" => $cancelUrl
        ],
        "description" => "Payment for order " . $referenceId
    ];

    $endpoint = "https://api.xendit.co/v3/payment_requests";
}

$ch = curl_init($endpoint);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "api-version: 2024-11-11",
        "Authorization: Basic " . base64_encode(XENDIT_SECRET_KEY . ":")
    ],
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 45
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError !== "") {
    error_log("Xendit connection error: " . $curlError);
    $conn->close();
    die("Unable to connect to Xendit.");
}

$result = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300 || !is_array($result)) {
    error_log("Xendit API error HTTP " . $httpCode . ": " . $response);
    $sql = "UPDATE orders SET status = 'FAILED' WHERE reference_id = '$escapedReference' AND status = 'PENDING'";
    $conn->query($sql);
    $conn->close();
    die("Xendit could not create the payment. Check your Test Mode settings and server error log.");
}

$redirectUrl = "";
$paymentId = "";

if ($channelCode === "CARDS") {
    $redirectUrl = $result["payment_link_url"] ?? "";
    $paymentId = $result["payment_session_id"] ?? "";
} else {
    $paymentId = $result["payment_request_id"] ?? "";

    if (isset($result["actions"]) && is_array($result["actions"])) {
        foreach ($result["actions"] as $action) {
            if (($action["type"] ?? "") === "REDIRECT_CUSTOMER" && !empty($action["value"])) {
                $candidate = $action["value"];

                if (filter_var($candidate, FILTER_VALIDATE_URL) && parse_url($candidate, PHP_URL_SCHEME) === "https") {
                    $redirectUrl = $candidate;
                    break;
                }
            }
        }
    }
}

if ($paymentId === "" || $redirectUrl === "") {
    error_log("Missing Xendit checkout URL or ID: " . $response);
    $conn->close();
    die("Xendit did not return a checkout URL. Check your Xendit configuration.");
}

$escapedPaymentId = $conn->real_escape_string($paymentId);
$sql = "UPDATE orders SET xendit_payment_id = '$escapedPaymentId' WHERE reference_id = '$escapedReference'";

if (!$conn->query($sql)) {
    error_log("Could not save Xendit ID: " . $conn->error);
    $conn->close();
    die("Could not save the payment reference.");
}

$conn->close();

header("Location: " . $redirectUrl, true, 303);
exit;