
<?php
require_once "database.php";

$conn = Database::connection();

$referenceId = isset($_GET["reference_id"]) ? trim($_GET["reference_id"]) : "";
$status = "UNKNOWN";
$amount = 0;

if ($referenceId !== "") {
    $escapedReferenceId = $conn->real_escape_string($referenceId);
    $sql = "SELECT amount, status FROM orders WHERE reference_id = '$escapedReferenceId' LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
        $amount = (float) $order["amount"];
        $status = $order["status"];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Status</title>
<style>
body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 30px; }
.container { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 12px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,.08); }
h1 { color: #b45309; }
p { color: #374151; line-height: 1.6; overflow-wrap: anywhere; }
a { display: inline-block; margin-top: 15px; padding: 12px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 7px; }
</style>
</head>
<body>
<div class="container">
<h1>Payment Not Completed</h1>
<p>Order reference: <?php echo htmlspecialchars($referenceId, ENT_QUOTES, "UTF-8"); ?></p>
<p>Amount: ₱<?php echo number_format($amount, 2); ?></p>
<p>Current order status: <strong><?php echo htmlspecialchars($status, ENT_QUOTES, "UTF-8"); ?></strong></p>
<p>If you did not complete the payment, you may return and try again.</p>
<a href="index.php">Return to Payment Page</a>
</div>
</body>
</html>
