<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xendit Payment Checkout</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 30px 15px; font-family: Arial, sans-serif; background: #f3f5f9; color: #202938; }
        .container { max-width: 520px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 14px; box-shadow: 0 5px 24px rgba(0,0,0,.08); }
        h1 { margin: 0 0 8px; font-size: 25px; }
        .subtitle { color: #667085; font-size: 14px; margin-bottom: 25px; }
        .section-title { display: block; font-weight: bold; margin: 20px 0 10px; }
        .field-label { display: block; font-size: 14px; font-weight: bold; margin: 15px 0 7px; }
        input[type="number"], input[type="tel"] { width: 100%; padding: 12px; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 15px; }
        .quick-amounts { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .quick-amounts button { flex: 1; min-width: 65px; padding: 9px; background: #fff; border: 1px solid #d0d5dd; border-radius: 8px; cursor: pointer; }
        .quick-amounts button:hover { background: #eef4ff; border-color: #4778e5; }
        .payment-option { display: flex; align-items: center; gap: 10px; padding: 14px; margin-bottom: 9px; border: 1px solid #d0d5dd; border-radius: 9px; cursor: pointer; }
        .payment-option:has(input:checked) { border-color: #3867d6; background: #f4f7ff; }
        .payment-option input { accent-color: #3867d6; }
        .payment-name { font-weight: bold; }
        .payment-description { display: block; color: #667085; font-size: 12px; margin-top: 4px; }
        .details-panel { margin-top: 15px; }
        .flow-panel { margin-top: 20px; padding: 15px; border: 1px solid #d0d5dd; border-radius: 10px; background: #fafbfc; }
        .flow-choice { display: block; margin: 12px 0; font-size: 14px; cursor: pointer; }
        .flow-choice input { margin-right: 8px; accent-color: #3867d6; }
        .demo-notice { padding: 12px; border-radius: 8px; background: #fff4db; color: #825500; font-size: 13px; line-height: 1.5; margin-top: 12px; }
        .real-notice { color: #667085; font-size: 13px; line-height: 1.5; margin-top: 12px; }
        .submit-btn { width: 100%; padding: 14px; margin-top: 23px; border: 0; border-radius: 9px; background: #3867d6; color: white; font-size: 16px; font-weight: bold; cursor: pointer; }
        .submit-btn:hover { background: #2854bd; }
        .footer { text-align: center; color: #667085; font-size: 12px; margin-top: 18px; }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Payment Checkout</h1>
        <p class="subtitle">Choose a payment method to continue.</p>

        <form action="create_payment.php" method="POST" id="checkoutForm">
            <label class="field-label" for="amount">Payment Amount (PHP)</label>
            <input type="number" id="amount" name="amount" min="1" max="50000" step="0.01" value="100.00" required>

            <div class="quick-amounts">
                <button type="button" data-amount="50">₱50</button>
                <button type="button" data-amount="100">₱100</button>
                <button type="button" data-amount="200">₱200</button>
                <button type="button" data-amount="500">₱500</button>
            </div>

            <span class="section-title">Select Payment Method</span>

            <label class="payment-option">
                <input type="radio" name="channel_code" value="GCASH" checked>
                <span><span class="payment-name">GCash</span><span class="payment-description">Pay using GCash</span></span>
            </label>

            <label class="payment-option">
                <input type="radio" name="channel_code" value="PAYMAYA">
                <span><span class="payment-name">Maya</span><span class="payment-description">Pay using Maya</span></span>
            </label>

            <label class="payment-option">
                <input type="radio" name="channel_code" value="CARDS">
                <span><span class="payment-name">Credit / Debit Card</span><span class="payment-description">Continue to Xendit's hosted checkout</span></span>
            </label>

            <div id="gcashDetails" class="details-panel">
                <label class="field-label" for="gcash_number">GCash Mobile Number</label>
                <input type="tel" id="gcash_number" name="gcash_number" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX">
            </div>

            <div id="mayaDetails" class="details-panel hidden">
                <label class="field-label" for="maya_number">Maya Mobile Number</label>
                <input type="tel" id="maya_number" name="maya_number" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" disabled>
            </div>

            <div id="cardNotice" class="real-notice hidden">Card payments always use Xendit's hosted checkout. Enter card details only on Xendit's secure checkout page.</div>

            <button type="submit" class="submit-btn" id="submitBtn">Continue to Payment</button>
        </form>

        <div class="footer">Payment options are processed according to the selected checkout flow.</div>
    </div>

    <script>
        const form = document.getElementById('checkoutForm');
        const amountInput = document.getElementById('amount');
        const gcashDetails = document.getElementById('gcashDetails');
        const mayaDetails = document.getElementById('mayaDetails');
        const gcashNumber = document.getElementById('gcash_number');
        const mayaNumber = document.getElementById('maya_number');
        const cardNotice = document.getElementById('cardNotice');
        const submitBtn = document.getElementById('submitBtn');

        function updatePaymentMethod() {
            const method = document.querySelector('input[name="channel_code"]:checked').value;
            const isGcash = method === 'GCASH';
            const isMaya = method === 'PAYMAYA';
            const isCard = method === 'CARDS';

            gcashDetails.classList.toggle('hidden', !isGcash);
            mayaDetails.classList.toggle('hidden', !isMaya);
            gcashNumber.disabled = !isGcash;
            gcashNumber.required = isGcash;
            mayaNumber.disabled = !isMaya;
            mayaNumber.required = isMaya;

            cardNotice.classList.toggle('hidden', !isCard);
            submitBtn.textContent = isCard ? 'Continue to Xendit Checkout' : 'Continue to Wallet Confirmation';
        }

        document.querySelectorAll('input[name="channel_code"]').forEach(function(input) {
            input.addEventListener('change', updatePaymentMethod);
        });

        document.querySelectorAll('[data-amount]').forEach(function(button) {
            button.addEventListener('click', function() {
                amountInput.value = this.dataset.amount;
            });
        });

        [gcashNumber, mayaNumber].forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 11);
            });
        });

        form.addEventListener('submit', function(event) {
            const amount = Number(amountInput.value);
            const method = document.querySelector('input[name="channel_code"]:checked').value;

            if (!Number.isFinite(amount) || amount < 1 || amount > 50000) {
                event.preventDefault();
                alert('Enter an amount from ₱1 to ₱50,000.');
                return;
            }

            if ((method === 'GCASH' || method === 'PAYMAYA') && !/^09[0-9]{9}$/.test(method === 'GCASH' ? gcashNumber.value : mayaNumber.value)) {
                event.preventDefault();
                alert('Enter a valid 11-digit mobile number beginning with 09.');
                return;
            }

            form.action = method === 'CARDS' ? 'create_payment.php' : 'wallet_demo.php';
        });

        updatePaymentMethod();
    </script>
</body>
</html>