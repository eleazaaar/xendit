<?php
date_default_timezone_set("Asia/Manila");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$amount = isset($_POST["amount"]) ? (float) $_POST["amount"] : 0;
$channelCode = strtoupper(trim($_POST["channel_code"] ?? ""));

if (!is_finite($amount) || $amount < 1 || $amount > 50000) {
    die("Invalid payment amount. Please return to checkout.");
}

if (!in_array($channelCode, ["GCASH", "PAYMAYA"], true)) {
    die("Invalid wallet payment method.");
}

if ($channelCode === "GCASH") {
    $walletNumber = trim($_POST["gcash_number"] ?? "");
    $walletName = "GCash";
} else {
    $walletNumber = trim($_POST["maya_number"] ?? "");
    $walletName = "Maya";
}

if (!preg_match('/^09[0-9]{9}$/', $walletNumber)) {
    die("Invalid mobile number. Please return to checkout.");
}

$maskedNumber = substr($walletNumber, 0, 2) . "****" . substr($walletNumber, -4);
$formattedAmount = number_format($amount, 2, ".", "");
$escapedAmount = htmlspecialchars($formattedAmount, ENT_QUOTES, "UTF-8");
$escapedMethod = htmlspecialchars($walletName, ENT_QUOTES, "UTF-8");
$escapedNumber = htmlspecialchars($maskedNumber, ENT_QUOTES, "UTF-8");
$escapedChannel = htmlspecialchars($channelCode, ENT_QUOTES, "UTF-8");
$escapedWalletNumber = htmlspecialchars($walletNumber, ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Wallet Payment</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 30px 15px; background: #f3f5f9; font-family: Arial, sans-serif; color: #202938; }
        .container { max-width: 460px; margin: 0 auto; padding: 28px; background: #fff; border-radius: 14px; box-shadow: 0 5px 24px rgba(0,0,0,.08); }
        .badge { display: inline-block; padding: 7px 10px; background: #fff4db; color: #825500; border-radius: 6px; font-size: 12px; font-weight: bold; }
        h1 { font-size: 24px; margin: 18px 0 8px; }
        .subtitle { color: #667085; font-size: 14px; line-height: 1.5; }
        .amount { margin: 25px 0; padding: 22px; text-align: center; border-radius: 10px; background: #f4f7ff; }
        .amount small { display: block; color: #667085; margin-bottom: 8px; }
        .amount strong { font-size: 32px; }
        .detail { display: flex; justify-content: space-between; gap: 12px; padding: 14px 0; border-bottom: 1px solid #eaecf0; font-size: 14px; }
        .detail span:first-child { color: #667085; }
        .detail span:last-child { font-weight: bold; text-align: right; }
        .notice { margin-top: 20px; padding: 12px; background: #fef3f2; color: #912018; border-radius: 8px; font-size: 13px; line-height: 1.5; }
        button, .back { display: block; width: 100%; padding: 14px; margin-top: 18px; border: 0; border-radius: 8px; text-align: center; text-decoration: none; font-size: 15px; font-weight: bold; cursor: pointer; }
        button { background: #3867d6; color: white; }
        .back { background: #eef1f5; color: #344054; }
    </style>
</head>
<body>
    <div class="container">
        <span class="badge">PAYMENT CONFIRMATION</span>
        <h1>Confirm your <?= $escapedMethod ?> payment</h1>
        <p class="subtitle">Review your payment details before continuing to Xendit's payment page.</p>

        <div class="amount">
            <small>Total payment</small>
            <strong>₱<?= $escapedAmount ?></strong>
        </div>

        <div class="detail"><span>Payment method</span><span><?= $escapedMethod ?></span></div>
        <div class="detail"><span>Mobile number</span><span><?= $escapedNumber ?></span></div>

        <div class="notice"><strong>Important:</strong> This is a confirmation screen for your checkout. No payment has been made yet. You will continue to Xendit to complete the actual payment. Never enter your wallet PIN or OTP on this page.</div>

        <form action="create_payment.php" method="POST">
            <input type="hidden" name="amount" value="<?= $escapedAmount ?>">
            <input type="hidden" name="channel_code" value="<?= $escapedChannel ?>">
            <input type="hidden" name="wallet_confirmed" value="1">
            <?php if ($channelCode === "GCASH"): ?>
                <input type="hidden" name="gcash_number" value="<?= $escapedWalletNumber ?>">
            <?php else: ?>
                <input type="hidden" name="maya_number" value="<?= $escapedWalletNumber ?>">
            <?php endif; ?>
            <button type="submit">Confirm and Continue to Xendit</button>
        </form>

        <a class="back" href="index.php">Cancel and Return to Checkout</a>
    </div>
</body>
</html>