<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Xendit Secure Checkout</title>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<style>
* { box-sizing: border-box; }
body { margin: 0; padding: 30px 16px; background: #f3f6fb; color: #172033; font-family: Arial, sans-serif; }
.container { max-width: 480px; margin: 25px auto; padding: 30px; background: #fff; border-radius: 18px; box-shadow: 0 12px 35px rgba(0,0,0,.08); }
.header { text-align: center; margin-bottom: 28px; }
.header h1 { margin: 0 0 8px; font-size: 27px; }
.header p { color: #687386; margin: 0; }
.badge { display: inline-block; margin-top: 12px; padding: 6px 10px; border-radius: 20px; background: #fff2d6; color: #855600; font-size: 11px; font-weight: bold; }
label.field-label { display: block; margin: 22px 0 9px; font-size: 14px; font-weight: bold; }
.amount-wrap { display: flex; align-items: center; border: 1px solid #d7deea; border-radius: 10px; padding: 0 14px; }
.amount-wrap span { font-size: 20px; font-weight: bold; color: #657085; }
.amount-wrap input { width: 100%; min-width: 0; border: 0; outline: none; padding: 15px 10px; font-size: 23px; font-weight: bold; }
.quick-amounts { display: flex; gap: 8px; margin-top: 10px; }
.quick-amounts button { flex: 1; border: 1px solid #d7deea; background: #fff; border-radius: 8px; padding: 9px 4px; cursor: pointer; }
.quick-amounts button:hover { background: #edf4ff; border-color: #3778e5; }
.payment-option { display: flex; align-items: center; gap: 12px; padding: 14px; margin-top: 10px; border: 1px solid #d7deea; border-radius: 12px; cursor: pointer; }
.payment-option:has(input:checked) { border: 2px solid #2767d8; background: #f3f7ff; padding: 13px; }
.payment-option input { accent-color: #2767d8; width: 17px; height: 17px; }
.method-icon { width: 42px; height: 42px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 20px; font-weight: bold; }
.gcash-icon { color: #0875df; background: #e7f2ff; }
.maya-icon { color: #008f46; background: #e7f9ed; }
.card-icon { color: #493bb8; background: #eeecff; }
.method-info { flex: 1; }
.method-name { display: block; font-weight: bold; }
.method-description { display: block; margin-top: 4px; color: #687386; font-size: 12px; }
.checkmark { color: #2767d8; font-size: 20px; }
.details-panel { display: none; margin-top: 18px; padding: 18px; background: #f8faff; border: 1px solid #e1e7f1; border-radius: 12px; }
.details-panel h3 { margin: 0 0 14px; font-size: 16px; }
.details-panel label { display: block; margin: 12px 0 6px; font-size: 13px; font-weight: bold; }
.details-panel input { width: 100%; padding: 12px; border: 1px solid #d7deea; border-radius: 8px; font-size: 15px; }
.details-panel .hint { margin: 12px 0 0; color: #687386; font-size: 12px; line-height: 1.5; }
.two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.submit-btn { width: 100%; margin-top: 25px; border: 0; border-radius: 10px; padding: 15px; background: #2767d8; color: white; font-size: 16px; font-weight: bold; cursor: pointer; }
.submit-btn:hover { background: #1e53b5; }
.note { margin-top: 18px; color: #7a8495; text-align: center; font-size: 12px; line-height: 1.6; }
@media(max-width: 420px) { .container { padding: 22px 18px; } }
</style>
</head>
<body>
<div class="container">
    <div class="header"><h1>Secure Checkout</h1><p>Choose your payment method</p><span class="badge">TEST MODE · SIMULATED PAYMENT</span></div>
    <form action="create_payment.php" method="POST" id="checkoutForm">
        <label class="field-label" for="amount">Payment Amount</label>
        <div class="amount-wrap">
            <span>₱</span>
            <input type="number" id="amount" name="amount" min="1" max="50000" step="0.01" value="100.00" inputmode="decimal" required>
        </div>
        <div class="quick-amounts">
            <button type="button" data-amount="50">₱50</button>
            <button type="button" data-amount="100">₱100</button>
            <button type="button" data-amount="200">₱200</button>
            <button type="button" data-amount="500">₱500</button>
        </div>

        <label class="field-label">Payment Method</label>
        <label class="payment-option" for="gcash">
            <input type="radio" id="gcash" name="channel_code" value="GCASH" checked>
            <span class="method-icon gcash-icon">G</span>
            <span class="method-info">
                <span class="method-name">GCash</span>
                <span class="method-description">Pay using GCash</span>
            </span><span class="checkmark">✓</span>
        </label>

        <label class="payment-option" for="maya">
            <input type="radio" id="maya" name="channel_code" value="PAYMAYA">
            <span class="method-icon maya-icon">M</span>
            <span class="method-info">
                <span class="method-name">Maya</span>
                <span class="method-description">Pay using Maya</span>
            </span>
            <span class="checkmark">✓</span>
        </label>

        <label class="payment-option" for="cards">
            <input type="radio" id="cards" name="channel_code" value="CARDS">
            <span class="method-icon card-icon">▤</span>
            <span class="method-info">
                <span class="method-name">Credit / Debit Card</span>
                <span class="method-description">Pay using a credit or debit card</span>
            </span>
            <span class="checkmark">✓</span>
        </label>

        <div class="details-panel" id="gcashDetails">
            <h3>GCash Details</h3>
            <label for="gcash_number">GCash Mobile Number</label>
            <input type="tel" id="gcash_number" name="gcash_number" inputmode="tel" placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" maxlength="11" autocomplete="tel" disabled required>
        </div>

        <div class="details-panel" id="mayaDetails">
            <h3>Maya Details</h3>
            <label for="maya_number">Maya Mobile Number</label>
            <input type="tel" id="maya_number" name="maya_number" inputmode="tel" placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" maxlength="11" autocomplete="tel" disabled required>
        </div>

        <button type="submit" class="submit-btn">Continue to Payment</button>
        <p class="note">Test mode only. Complete payment using Xendit's supported checkout or authentication flow.</p>
    </form>
</div>
<script>
$(function() {
    function updatePaymentMethod() {
        const method = $('input[name="channel_code"]:checked').val();
        $('.details-panel').hide().find(':input').prop('disabled', true);
        if (method === 'GCASH') $('#gcashDetails').show().find(':input').prop('disabled', false);
        if (method === 'PAYMAYA') $('#mayaDetails').show().find(':input').prop('disabled', false);
    }
    $('input[name="channel_code"]').on('change', updatePaymentMethod);

    $('[data-amount]').on('click', function() { 
        $('#amount').val($(this).data('amount')); 
    });

    $('#expiry').on('input', function() { 
        let value = this.value.replace(/\D/g, '').slice(0, 4);
        if (value.length > 2) value = value.slice(0, 2) + '/' + value.slice(2);
        this.value = value; 
    });

    $('#card_number').on('input', function() { 
        this.value = this.value.replace(/\D/g, '').slice(0, 19);
    });

    $('#cvv').on('input', function() { 
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
    });

    updatePaymentMethod();
});
</script>
</body>
</html>