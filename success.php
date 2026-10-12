<?php
require_once "database.php";

date_default_timezone_set("Asia/Manila");

$referenceId = trim($_GET["reference_id"] ?? "");
$order = null;

if ($referenceId !== "" && strlen($referenceId) <= 100) {
    $conn = Database::connection();
    $escapedReference = $conn->real_escape_string($referenceId);

    $sql = "SELECT reference_id, amount, payment_method, status, created_at, paid_at FROM orders WHERE reference_id = '$escapedReference' LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
    }

    $conn->close();
}

if (!$order) {
    http_response_code(404);
    $pageTitle = "Order Not Found";
    $statusTitle = "Order Not Found";
    $statusMessage = "We could not find this payment reference. Please return to checkout and try again.";
    $statusIcon = "!";
    $statusClass = "pending";
    $amount = "0.00";
    $method = "Unknown";
    $reference = "Unavailable";
} else {
    $status = strtoupper($order["status"]);

    if ($status === "PAID") {
        $pageTitle = "Payment Successful";
        $statusTitle = "Payment Successful!";
        $statusMessage = "Your payment has been confirmed. Thank you for your transaction.";
        $statusIcon = "✓";
        $statusClass = "success";
    } elseif ($status === "PENDING") {
        $pageTitle = "Payment Pending";
        $statusTitle = "Payment Pending";
        $statusMessage = "Your payment has not been confirmed yet. Please wait for the payment provider to update the status.";
        $statusIcon = "…";
        $statusClass = "pending";
    } else {
        $pageTitle = "Payment Not Completed";
        $statusTitle = "Payment Not Completed";
        $statusMessage = "This order is currently marked as " . $status . ". If you believe this is incorrect, please check your payment provider.";
        $statusIcon = "!";
        $statusClass = "failed";
    }

    $amount = number_format((float) $order["amount"], 2);
    $method = $order["payment_method"];
    $reference = $order["reference_id"];
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 30px 15px; display: flex; align-items: center; justify-content: center; font-family: Arial, sans-serif; color: #202938; background: #f3f5f9; }
        .card { width: 100%; max-width: 480px; overflow: hidden; background: #fff; border-radius: 18px; box-shadow: 0 10px 40px rgba(16,24,40,.09); }
        .top { padding: 32px 25px 25px; text-align: center; }
        .icon { width: 76px; height: 76px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; border-radius: 50%; font-size: 38px; font-weight: bold; }
        .success .icon { color: #16803c; background: #dcfce7; }
        .pending .icon { color: #946200; background: #fff1c2; }
        .failed .icon { color: #b42318; background: #fee4e2; }
        h1 { margin: 0 0 10px; font-size: 27px; }
        .message { margin: 0 auto; max-width: 350px; color: #667085; font-size: 14px; line-height: 1.7; }
        .amount { margin-top: 25px; padding: 20px; border-radius: 12px; background: #f8fafc; }
        .amount-label { color: #667085; font-size: 13px; }
        .amount-value { margin-top: 7px; font-size: 34px; font-weight: bold; }
        .details { padding: 5px 25px 20px; }
        .detail { display: flex; justify-content: space-between; gap: 15px; padding: 14px 0; border-bottom: 1px solid #eaecf0; font-size: 13px; }
        .detail:last-child { border-bottom: none; }
        .detail-label { color: #667085; }
        .detail-value { max-width: 65%; overflow-wrap: anywhere; text-align: right; font-weight: bold; }
        .footer { padding: 0 25px 28px; }
        .button { display: block; padding: 14px 18px; border-radius: 9px; background: #3867d6; color: #fff; text-align: center; text-decoration: none; font-size: 15px; font-weight: bold; }
        .button:hover { background: #2854bd; }
        .note { margin-top: 14px; color: #98a2b3; font-size: 11px; line-height: 1.6; text-align: center; }
    </style>
</head>
<body>
    <main class="card <?= e($statusClass) ?>">
        <section class="top">
            <div class="icon"><?= e($statusIcon) ?></div>
            <h1><?= e($statusTitle) ?></h1>
            <p class="message"><?= e($statusMessage) ?></p>

            <?php if ($order): ?>
                <div class="amount">
                    <div class="amount-label">Order Amount</div>
                    <div class="amount-value">₱<?= e($amount) ?></div>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($order): ?>
            <section class="details">
                <div class="detail"><span class="detail-label">Payment Method</span><span class="detail-value"><?= e($method) ?></span></div>
                <div class="detail"><span class="detail-label">Order Reference</span><span class="detail-value"><?= e($reference) ?></span></div>
                <div class="detail"><span class="detail-label">Payment Status</span><span class="detail-value"><?= e(strtoupper($order["status"])) ?></span></div>
                <?php if (!empty($order["paid_at"])): ?>
                    <div class="detail"><span class="detail-label">Paid At</span><span class="detail-value"><?= e($order["paid_at"]) ?></span></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="footer">
            <a class="button" href="index.php">Return to Checkout</a>
            <p class="note">Payment status is based on your order record. A redirect alone does not confirm that payment was received.</p>
        </div>
    </main>
</body>
</html>