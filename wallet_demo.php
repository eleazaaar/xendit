<?php
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$method = strtoupper(trim($_POST['payment_method'] ?? ''));
$walletNumber = '';

if (!in_array($method, ['GCASH', 'PAYMAYA'], true)) {
    header('Location: index.php');
    exit;
}

if ($amount === false || $amount === null || $amount < 1 || $amount > 50000) {
    exit('Invalid payment amount. Please return to checkout.');
}

$walletNumber = trim($method === 'GCASH' ? ($_POST['gcash_number'] ?? '') : ($_POST['maya_number'] ?? ''));
if (!preg_match('/^09[0-9]{9}$/', $walletNumber)) {
    exit('Invalid mobile number. Please return to checkout and try again.');
}

$provider = $method === 'GCASH' ? 'GCash' : 'Maya';
$maskedNumber = substr($walletNumber, 0, 4) . '****' . substr($walletNumber, -3);
$formattedAmount = number_format($amount, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Payment | Xendit</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 30px 15px; font-family: Arial, sans-serif; color: #172033; background: #f3f6fc; }
        .container { max-width: 500px; margin: 35px auto; padding: 30px; background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 15px 45px rgba(31,54,92,.09); }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; font-size: 19px; font-weight: 800; }
        .brand-icon { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; background: #2563eb; color: #fff; }
        .icon { display: grid; place-items: center; width: 58px; height: 58px; margin-bottom: 18px; border-radius: 16px; background: #eff6ff; color: #2563eb; font-size: 26px; font-weight: bold; }
        h1 { margin: 0 0 9px; font-size: 26px; letter-spacing: -.5px; }
        .subtitle { margin: 0 0 25px; color: #64748b; font-size: 14px; line-height: 1.6; }
        .amount-box { padding: 20px; text-align: center; border-radius: 13px; background: #f6f9ff; border: 1px solid #e2eaff; }
        .muted { color: #64748b; font-size: 13px; }
        .amount { margin-top: 8px; font-size: 32px; font-weight: 800; letter-spacing: -.7px; }
        .details { margin: 23px 0; }
        .detail { display: flex; justify-content: space-between; gap: 15px; padding: 14px 0; border-bottom: 1px solid #edf1f6; font-size: 14px; }
        .detail:last-child { border-bottom: 0; }
        .detail strong { text-align: right; overflow-wrap: anywhere; }
        .notice { padding: 13px; border-radius: 10px; background: #fff7ed; color: #9a3412; font-size: 12px; line-height: 1.6; }
        .btn { display: block; width: 100%; margin-top: 18px; padding: 15px; border: 0; border-radius: 10px; background: #2563eb; color: #fff; text-align: center; text-decoration: none; font-size: 15px; font-weight: 700; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .back { display: block; margin-top: 17px; color: #64748b; text-align: center; text-decoration: none; font-size: 13px; }
        .footer { margin-top: 24px; padding-top: 17px; border-top: 1px solid #edf1f6; color: #64748b; text-align: center; font-size: 11px; }
        @media (max-width: 480px) { .container { margin: 5px auto; padding: 23px 18px; } .amount { font-size: 28px; } }
    </style>
</head>
<body>
    <main class="container">
        <div class="brand"><span class="brand-icon">X</span> Xendit Checkout</div>

        <div class="icon"><?= $method === 'GCASH' ? 'G' : 'M' ?></div>
        <h1>Confirm your payment</h1>
        <p class="subtitle">Review your payment details before continuing to the payment provider.</p>

        <div class="amount-box">
            <div class="muted">Total payment amount</div>
            <div class="amount">₱<?= htmlspecialchars($formattedAmount, ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div class="details">
            <div class="detail"><span class="muted">Payment method</span><strong><?= htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="detail"><span class="muted">Mobile number</span><strong><?= htmlspecialchars($maskedNumber, ENT_QUOTES, 'UTF-8') ?></strong></div>
            <div class="detail"><span class="muted">Payment status</span><strong>Awaiting payment</strong></div>
        </div>

        <div class="notice">Please verify that the mobile number belongs to your <?= htmlspecialchars($provider, ENT_QUOTES, 'UTF-8') ?> account. Your payment is not complete until the provider confirms it.</div>

        <form action="app/classes/controllers/XenditControlller.php?action=createPayment" method="POST" id="confirmForm">
            <input type="hidden" name="amount" value="<?= htmlspecialchars((string) $amount, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="payment_method" value="<?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="wallet_number" value="<?= htmlspecialchars($walletNumber, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="wallet_confirmed" value="1">
            <button class="btn" id="confirmBtn" type="submit">Confirm and Continue →</button>
        </form>

        <a class="back" href="index.php">← Go back to checkout</a>
        <div class="footer">Payments are processed securely by Xendit.</div>
    </main>

    <script>
        document.getElementById('confirmForm').addEventListener('submit', function () {
            const button = document.getElementById('confirmBtn');
            button.disabled = true;
            button.textContent = 'Connecting to payment provider...';
        });
    </script>
</body>
</html>