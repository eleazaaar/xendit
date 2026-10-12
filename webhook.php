<?php
require_once "config.php";
require_once "database.php";

header("Content-Type: application/json");

$callbackToken = $_SERVER["HTTP_X_CALLBACK_TOKEN"] ?? "";

if (!defined("XENDIT_CALLBACK_TOKEN") || !hash_equals(XENDIT_CALLBACK_TOKEN, $callbackToken)) {
    http_response_code(401);
    echo json_encode(["message" => "Invalid callback token"]);
    exit;
}

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!is_array($data) || !is_array($data["data"] ?? null)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid JSON"]);
    exit;
}

$event = $data["event"] ?? "";
$payload = $data["data"];
$referenceId = trim($payload["reference_id"] ?? "");
$status = strtoupper(trim($payload["status"] ?? ""));
$paymentRequestId = trim($payload["payment_request_id"] ?? "");

if ($referenceId === "" && $paymentRequestId === "") {
    http_response_code(200);
    echo json_encode(["message" => "No order reference; ignored"]);
    exit;
}

$conn = Database::connection();

$conditions = [];

if ($referenceId !== "") {
    $referenceId = $conn->real_escape_string($referenceId);
    $conditions[] = "reference_id = '$referenceId'";
}

if ($paymentRequestId !== "") {
    $paymentRequestId = $conn->real_escape_string($paymentRequestId);
    $conditions[] = "xendit_payment_id = '$paymentRequestId'";
}

$where = "(" . implode(" OR ", $conditions) . ")";

if ($event === "payment_session.completed" && $status === "COMPLETED") {
    $sql = "UPDATE orders SET status = 'PAID', paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP) WHERE $where AND status = 'PENDING'";
} elseif ($event === "payment_session.expired" && $status === "EXPIRED") {
    $sql = "UPDATE orders SET status = 'FAILED' WHERE $where AND status = 'PENDING'";
} elseif (($event === "payment.capture" && $status === "SUCCEEDED") || ($event === "payment.succeeded" && $status === "SUCCEEDED")) {
    $sql = "UPDATE orders SET status = 'PAID', paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP) WHERE $where AND status = 'PENDING'";
} elseif (($event === "payment.failure" || $event === "payment.failed") && $status === "FAILED") {
    $sql = "UPDATE orders SET status = 'FAILED' WHERE $where AND status = 'PENDING'";
} else {
    $conn->close();
    http_response_code(200);
    echo json_encode(["message" => "Event received; no update required"]);
    exit;
}

if (!$conn->query($sql)) {
    error_log("Webhook database error: " . $conn->error);
    $conn->close();
    http_response_code(500);
    echo json_encode(["message" => "Database update failed"]);
    exit;
}

$updated = $conn->affected_rows;
$conn->close();

http_response_code(200);
echo json_encode(["message" => "Webhook processed", "updated_orders" => $updated]);