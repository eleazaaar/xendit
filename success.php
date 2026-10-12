<?php
require_once "database.php";

$conn = Database::connection();
$referenceId = $_GET["reference_id"] ?? "";
$referenceId = $conn->real_escape_string($referenceId);

$order = null;

if ($referenceId !== "") {
    $sql = "SELECT reference_id, amount, payment_method, status, created_at, paid_at FROM orders WHERE reference_id = '$referenceId' LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Status</title>
<style>
body { margin: 0; padding: 25px; background: #f3f6fb; font-family: Arial, sans-serif; color: #172033; }
.container { max-width: 480px; margin: 50px auto; padding: 30px; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.07); }
h1 { margin-top: 0; }
.status { padding: 12px; background: #f0f4fa; border-radius: 8px; font-weight: bold; }
.row { display: flex; justify-content: space-between; gap: 16px; padding: 13px 0; border-bottom: 1px solid #edf0f5; }
.row span:first-child { color: #687386; }
a { display: block; margin-top: 25px; padding: 13px; text-align: center; background: #2767d8; color: white; text-decoration: none; border-radius: 9px; }
</style>
</head>
<body>
<div class="container">
<?php if (!$order): ?>
    <h1>Order Not Found</h1>
    <p>We could not find an order with that reference number.</p>
<?php else: ?>
    <h1><?php echo $order["status"] === "PAID" ? "Payment Successful" : "Payment Status"; ?></h1>
    <p class="status">Status: <?php echo e($order["status"]); ?></p>
    <div class="row"><span>Reference</span><strong><?php echo e($order["reference_id"]); ?></strong></div>
    <div class="row"><span>Amount</span><strong>₱<?php echo number_format((float)$order["amount"], 2); ?></strong></div>
    <div class="row"><span>Method</span><strong><?php echo e($order["payment_method"]); ?></strong></div>
    <div class="row"><span>Created</span><strong><?php echo e($order["created_at"]); ?></strong></div>
    <div class="row"><span>Paid at</span><strong><?php echo e($order["paid_at"] ?: "Not confirmed"); ?></strong></div>
    <?php if ($order["status"] === "PENDING"): ?>
        <p>Your payment has not yet been confirmed. Refresh this page after a short wait.</p>
    <?php elseif ($order["status"] === "FAILED"): ?>
        <p>The payment was not completed. You can return to checkout and try again.</p>
    <?php endif; ?>
<?php endif; ?>
<a href="index.php">Back to Checkout</a>
</div>
</body>
</html>