<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $zones */
/** @var array<int,array<string,mixed>> $items */
/** @var float $subTotal */
/** @var string $appUrl */
?>
<style>
    .checkout-wrap { display:grid; grid-template-columns:1fr 380px; gap:2.5rem; padding:2rem 0 3rem; align-items:start; }
    @media (max-width:840px) { .checkout-wrap { grid-template-columns:1fr; } }

    .checkout-wrap h1 { font-size:1.5rem; margin:0 0 1.5rem; font-weight:800; grid-column:1/-1; }
    .field { margin-bottom:1rem; }
    .field label { display:block; font-size:.82rem; font-weight:600; margin-bottom:.3rem; }
    .field input, .field textarea {
        width:100%; padding:.65rem .75rem; border:1px solid var(--line); border-radius:6px; font-size:.9rem;
    }
    .field-row { display:flex; gap:.75rem; }
    .field-row .field { flex:1; }

    .option-card {
        display:flex; align-items:center; gap:.65rem; padding:.8rem .9rem; border:1px solid var(--line);
        border-radius:8px; margin-bottom:.6rem; cursor:pointer;
    }
    .option-card.selected { border-color:var(--brand); background:var(--bg-soft); }
    .option-card .meta { flex:1; }
    .option-card .name { font-weight:600; font-size:.88rem; }
    .option-card .sub { font-size:.78rem; color:var(--muted); }
    .option-card .fee { font-weight:700; font-size:.88rem; }

    .pay-extra { display:none; margin-top:.6rem; padding:.8rem; background:var(--bg-soft); border-radius:8px; }
    .pay-extra.show { display:block; }
    .pay-extra .field { margin-bottom:.6rem; }

    .summary-card {
        border:1px solid var(--line); border-radius:10px; padding:1.25rem; position:sticky; top:5rem;
    }
    .summary-card h3 { margin:0 0 1rem; font-size:1rem; }
    .summary-line { display:flex; justify-content:space-between; font-size:.85rem; padding:.4rem 0; }
    .summary-item { display:flex; gap:.6rem; font-size:.85rem; padding:.4rem 0; }
    .summary-item .qty { color:var(--muted); }
    .summary-total { border-top:1px solid var(--line); margin-top:.6rem; padding-top:.7rem; font-weight:800; font-size:1.05rem; }
</style>

<div class="wrap checkout-wrap">
    <h1>Checkout</h1>

    <?php if ($items === []): ?>
        <div class="empty" style="padding:2rem 1rem; text-align:center; color:var(--muted)">
            Your cart is empty. <a href="<?= View::e($appUrl) ?>/collections/all">Continue shopping</a>.
        </div>
    <?php else: ?>
    <div>
        <form id="checkout-form">
            <div class="field">
                <label for="recipient_name">Full Name *</label>
                <input id="recipient_name" name="recipient_name" required maxlength="150">
            </div>
            <div class="field">
                <label for="recipient_phone">Phone Number *</label>
                <input id="recipient_phone" name="recipient_phone" required maxlength="20" placeholder="01XXXXXXXXX">
            </div>
            <div class="field">
                <label for="shipping_address">Full Address *</label>
                <textarea id="shipping_address" name="shipping_address" rows="3" required></textarea>
            </div>

            <div class="field">
                <label>Delivery Area *</label>
                <?php foreach ($zones as $i => $zone): ?>
                    <label class="option-card <?= (int) $zone['is_default'] === 1 ? 'selected' : '' ?>" data-zone-option>
                        <input type="radio" name="delivery_zone_id" value="<?= (int) $zone['id'] ?>"
                               data-fee="<?= (float) $zone['fee'] ?>" data-threshold="<?= (float) $zone['free_delivery_threshold'] ?>"
                               <?= (int) $zone['is_default'] === 1 ? 'checked' : '' ?> style="width:auto">
                        <span class="meta">
                            <span class="name"><?= View::e($zone['name']) ?></span>
                        </span>
                        <span class="fee">৳<?= number_format((float) $zone['fee'], 0) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="field">
                <label>Payment Method *</label>
                <label class="option-card selected" data-pay-option>
                    <input type="radio" name="payment_method" value="1" checked style="width:auto">
                    <span class="meta"><span class="name">Cash on Delivery</span><span class="sub">Pay when your order arrives</span></span>
                </label>
                <label class="option-card" data-pay-option>
                    <input type="radio" name="payment_method" value="2" style="width:auto">
                    <span class="meta"><span class="name">bKash (Advance Payment)</span><span class="sub">Pay in advance, we verify and ship</span></span>
                </label>
                <label class="option-card" data-pay-option>
                    <input type="radio" name="payment_method" value="3" style="width:auto">
                    <span class="meta"><span class="name">Nagad (Advance Payment)</span><span class="sub">Pay in advance, we verify and ship</span></span>
                </label>

                <div class="pay-extra" id="pay-extra">
                    <p class="muted" style="margin:0 0 .6rem;font-size:.82rem">
                        Send the total amount to our <strong id="pay-method-name">bKash</strong> number
                        <strong>01700-000000</strong> (Send Money), then enter your number and the Transaction ID below.
                    </p>
                    <div class="field-row">
                        <div class="field">
                            <label for="sender_number">Your bKash/Nagad Number *</label>
                            <input id="sender_number" name="sender_number" maxlength="20">
                        </div>
                        <div class="field">
                            <label for="transaction_id">Transaction ID *</label>
                            <input id="transaction_id" name="transaction_id" maxlength="50">
                        </div>
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="note">Order Note (optional)</label>
                <textarea id="note" name="note" rows="2"></textarea>
            </div>

            <button type="submit" class="btn accent block" id="place-order-btn">Place Order</button>
        </form>
    </div>

    <div class="summary-card">
        <h3>Order Summary</h3>
        <?php foreach ($items as $item): ?>
            <div class="summary-item">
                <span class="qty"><?= (float) $item['qty'] ?>&times;</span>
                <span style="flex:1"><?= View::e($item['name']) ?></span>
                <span>৳<?= number_format((float) $item['line_total'], 0) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="summary-line"><span>Subtotal</span><span id="sum-subtotal">৳<?= number_format((float) $subTotal, 0) ?></span></div>
        <div class="summary-line"><span>Delivery Fee</span><span id="sum-delivery">—</span></div>
        <div class="summary-line summary-total"><span>Total</span><span id="sum-total">—</span></div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    if (!document.getElementById('checkout-form')) { return; }

    var subTotal = <?= (float) $subTotal ?>;

    function recomputeSummary() {
        var checked = document.querySelector('input[name="delivery_zone_id"]:checked');
        var fee = checked ? parseFloat(checked.dataset.fee) : 0;
        var threshold = checked ? parseFloat(checked.dataset.threshold) : 0;

        if (threshold > 0 && subTotal >= threshold) { fee = 0; }

        document.getElementById('sum-delivery').textContent = window.money(fee);
        document.getElementById('sum-total').textContent = window.money(subTotal + fee);
    }

    document.querySelectorAll('input[name="delivery_zone_id"]').forEach(function (input) {
        input.addEventListener('change', function () {
            document.querySelectorAll('[data-zone-option]').forEach(function (el) { el.classList.remove('selected'); });
            input.closest('[data-zone-option]').classList.add('selected');
            recomputeSummary();
        });
    });

    document.querySelectorAll('input[name="payment_method"]').forEach(function (input) {
        input.addEventListener('change', function () {
            document.querySelectorAll('[data-pay-option]').forEach(function (el) { el.classList.remove('selected'); });
            input.closest('[data-pay-option]').classList.add('selected');

            var extra = document.getElementById('pay-extra');
            var needsRef = input.value === '2' || input.value === '3';
            extra.classList.toggle('show', needsRef);
            document.getElementById('sender_number').required = needsRef;
            document.getElementById('transaction_id').required = needsRef;
            document.getElementById('pay-method-name').textContent = input.value === '2' ? 'bKash' : 'Nagad';
        });
    });

    recomputeSummary();

    document.getElementById('checkout-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        var btn = document.getElementById('place-order-btn');
        btn.disabled = true;
        btn.textContent = 'Placing order…';

        var zoneEl = document.querySelector('input[name="delivery_zone_id"]:checked');
        var payEl  = document.querySelector('input[name="payment_method"]:checked');

        var body = {
            recipient_name:    document.getElementById('recipient_name').value,
            recipient_phone:   document.getElementById('recipient_phone').value,
            shipping_address:  document.getElementById('shipping_address').value,
            delivery_zone_id:  zoneEl ? parseInt(zoneEl.value, 10) : 0,
            payment_method:    payEl ? parseInt(payEl.value, 10) : 1,
            sender_number:     document.getElementById('sender_number').value,
            transaction_id:    document.getElementById('transaction_id').value,
            note:              document.getElementById('note').value
        };

        var result = await window.storefrontApi('/checkout', body, 'POST');

        btn.disabled = false;
        btn.textContent = 'Place Order';

        if (result) {
            var code = result.order.code;
            var phone = result.order.recipient_phone;
            location.href = '<?= View::e($appUrl) ?>/orders/track?code=' + encodeURIComponent(code) + '&phone=' + encodeURIComponent(phone);
        }
    });
})();
</script>
