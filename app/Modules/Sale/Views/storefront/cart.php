<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $items */
/** @var float $subTotal */
/** @var string $appUrl */
?>
<style>
    .cart-wrap { padding:2rem 0 3rem; }
    .cart-wrap h1 { font-size:1.5rem; margin:0 0 1.5rem; font-weight:800; }
    .cart-row {
        display:flex; align-items:center; gap:1rem; padding:1rem 0; border-bottom:1px solid var(--line);
    }
    .cart-row img { width:72px; height:72px; object-fit:cover; border-radius:6px; background:var(--bg-soft); }
    .cart-row .noimg { width:72px; height:72px; border-radius:6px; background:var(--bg-soft); display:grid; place-items:center; color:var(--muted); font-size:.68rem; }
    .cart-row .info { flex:1; min-width:0; }
    .cart-row .info a { font-weight:600; font-size:.92rem; }
    .cart-row .price { font-size:.85rem; color:var(--muted); margin-top:.2rem; }
    .cart-stepper { display:flex; align-items:center; border:1px solid var(--line); border-radius:6px; }
    .cart-stepper button { width:2rem; height:2rem; border:0; background:none; cursor:pointer; font-size:1rem; }
    .cart-stepper input { width:2.4rem; text-align:center; border:0; }
    .cart-row .line-total { width:90px; text-align:right; font-weight:700; }
    .cart-row .remove { color:var(--accent); font-size:.8rem; cursor:pointer; background:none; border:0; }

    .cart-summary { max-width:420px; margin-left:auto; margin-top:1.5rem; text-align:right; }
    .cart-summary .row { display:flex; justify-content:space-between; padding:.35rem 0; font-size:.9rem; }
    .cart-summary .total { font-size:1.15rem; font-weight:800; border-top:1px solid var(--line); margin-top:.5rem; padding-top:.75rem; }
</style>

<div class="wrap cart-wrap">
    <h1>Your Cart</h1>

    <?php if ($items === []): ?>
        <div class="empty" style="padding:3rem 1rem; text-align:center; color:var(--muted)">
            Your cart is empty.
            <div style="margin-top:1rem"><a class="btn" href="<?= View::e($appUrl) ?>/collections/all">Continue Shopping</a></div>
        </div>
    <?php else: ?>
        <div id="cart-lines">
            <?php foreach ($items as $item): ?>
                <div class="cart-row" data-item="<?= (int) $item['id'] ?>">
                    <?php if (($item['image'] ?? '') !== ''): ?>
                        <img src="<?= View::e($appUrl . $item['image']) ?>" alt="">
                    <?php else: ?>
                        <div class="noimg">No image</div>
                    <?php endif; ?>
                    <div class="info">
                        <a href="<?= View::e($appUrl . '/products/' . $item['product_slug']) ?>"><?= View::e($item['name']) ?></a>
                        <div class="price">৳<?= number_format((float) $item['unit_price'], 0) ?> each</div>
                        <button type="button" class="remove" data-remove="<?= (int) $item['id'] ?>">Remove</button>
                    </div>
                    <div class="cart-stepper">
                        <button type="button" data-step="-1" data-item-step="<?= (int) $item['id'] ?>">&minus;</button>
                        <input type="number" value="<?= (float) $item['qty'] ?>" min="1" readonly id="qty-<?= (int) $item['id'] ?>">
                        <button type="button" data-step="1" data-item-step="<?= (int) $item['id'] ?>">&plus;</button>
                    </div>
                    <div class="line-total">৳<?= number_format((float) $item['line_total'], 0) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-summary">
            <div class="row"><span>Subtotal</span><span>৳<?= number_format((float) $subTotal, 0) ?></span></div>
            <div class="muted" style="font-size:.8rem">Delivery fee calculated at checkout.</div>
            <div class="row total"><span>Estimated Total</span><span>৳<?= number_format((float) $subTotal, 0) ?></span></div>
            <a class="btn accent block" style="margin-top:1rem" href="<?= View::e($appUrl) ?>/checkout">Proceed to Checkout</a>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    document.querySelectorAll('[data-item-step]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var id = this.dataset.itemStep;
            var input = document.getElementById('qty-' + id);
            var next = Math.max(0, parseInt(input.value, 10) + parseInt(this.dataset.step, 10));

            var result = await window.storefrontApi('/cart/items/' + id, { qty: next }, 'PUT');
            if (result) { location.reload(); }
        });
    });

    document.querySelectorAll('[data-remove]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var result = await window.storefrontApi('/cart/items/' + this.dataset.remove, null, 'DELETE');
            if (result) { location.reload(); }
        });
    });
})();
</script>
