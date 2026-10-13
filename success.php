<?php
include_once 'app/classes/models/XenditModel.php';
$XenditModel = new XenditModel();

$referenceId = trim($_GET['reference_id'] ?? '');
$status = '';
$orderFound = false;

if ($referenceId !== '' && strlen($referenceId) <= 100) {
    $order = $XenditModel->getOrderByReference($referenceId);
    if (is_array($order) && !empty($order)) {
        $orderFound = true;
        $status = strtoupper(trim($order['status'] ?? ''));
    }
}

$wasPaid = $orderFound && $status === 'PAID';

if ($wasPaid) {
    $title = 'Payment successful';
    $message = 'Your payment has been confirmed. Thank you for your transaction.';
    $icon = '✓';
    $iconBackground = '#dcfce7';
    $iconColor = '#15803d';
} elseif ($orderFound && $status === 'FAILED') {
    $title = 'Payment not completed';
    $message = 'Your payment was marked as failed. You can return to checkout to try again.';
    $icon = '!';
    $iconBackground = '#fff1f2';
    $iconColor = '#be123c';
} elseif ($orderFound) {
    $title = 'Payment processing';
    $message = 'Your payment is still being verified. Please wait for confirmation before attempting another payment.';
    $icon = '…';
    $iconBackground = '#fef3c7';
    $iconColor = '#b45309';
} else {
    $title = 'Order not found';
    $message = 'We could not find your order. Please check your order reference or contact support if you completed a payment.';
    $icon = '!';
    $iconBackground = '#fff1f2';
    $iconColor = '#be123c';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Xendit</title>
    <?php if ($orderFound && $status !== 'PAID' && $status !== 'FAILED'): ?>
        <meta http-equiv="refresh" content="3">
    <?php endif; ?>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            color: #172033;
            background: #f3f6fc;
        }

        .container {
            max-width: 510px;
            margin: 45px auto;
            padding: 32px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 15px 45px rgba(31,54,92,.09);
            text-align: center;
        }

        .brand {
            margin-bottom: 35px;
            font-size: 19px;
            font-weight: 800;
        }

        .status-icon {
            display: grid;
            place-items: center;
            width: 76px;
            height: 76px;
            margin: 0 auto 22px;
            border-radius: 50%;
            background: <?= json_encode($iconBackground) ?>;
            color: <?= json_encode($iconColor) ?>;
            font-size: 38px;
            font-weight: 700;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 27px;
            letter-spacing: -.6px;
        }

        .message {
            color: #64748b;
            font-size: 14px;
            line-height: 1.7;
        }

        .reference {
            margin-top: 25px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            text-align: left;
        }

        .reference small {
            display: block;
            margin-bottom: 7px;
            color: #64748b;
        }

        .reference strong {
            overflow-wrap: anywhere;
            font-size: 13px;
        }

        .btn {
            display: block;
            margin-top: 24px;
            padding: 14px;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }

        .btn:hover { background: #1d4ed8; }

        .secondary {
            display: block;
            margin-top: 16px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
        }

        .footer {
            margin-top: 23px;
            color: #64748b;
            font-size: 11px;
        }

        @media (max-width: 480px) {
            .container {
                margin: 5px auto;
                padding: 25px 18px;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="brand">Xendit Checkout</div>

        <div class="status-icon">
            <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>

        <p class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if ($referenceId !== ''): ?>
            <div class="reference">
                <small>Order reference</small>
                <strong>
                    <?= htmlspecialchars($referenceId, ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </div>
        <?php endif; ?>

        <?php if ($wasPaid): ?>
            <a class="btn" href="index.php">Return to Checkout</a>
        <?php elseif ($orderFound && $status !== 'FAILED'): ?>
            <a class="btn" href="index.php">Return to Checkout</a>
        <?php else: ?>
            <a class="btn" href="index.php">Try Again</a>
        <?php endif; ?>

        <a class="secondary" href="index.php">
            Return to payment options
        </a>

        <div class="footer">
            If your account was charged but the payment is not confirmed, please wait for the payment status to update before trying again.
        </div>
    </main>
</body>
</html>