<?php

use App\Core\View;

/** @var array<string,mixed> $purchase — এডিটে ভরা, নতুনে খালি */
/** @var array<int,array<string,mixed>> $suppliers */
/** @var array<int,array<string,mixed>> $products */
/** @var string $appUrl */

$isEdit = ($purchase['id'] ?? 0) > 0;
$v = static fn (string $key, mixed $default = '') => $purchase[$key] ?? $default;
?>
<style>
    .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
    .grid2 > div { min-width:0; }
    .builder { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; }
    .builder > div { flex:1 1 140px; }
    .builder input, .builder select { width:100%; }
    @media (max-width:640px){ .grid2 { grid-template-columns:1fr; } }
</style>

<div class="card" style="max-width:900px">
    <form id="purchase-form">
        <input type="hidden" id="id" value="<?= (int) ($purchase['id'] ?? 0) ?>">

        <div class="grid2" style="margin-bottom:.85rem">
            <div>
                <label for="supplier_id">Supplier *</label>
                <select id="supplier_id" style="width:100%" required>
                    <option value="">— select —</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"
                            <?= (int) $v('supplier_id') === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= View::e($s['name']) ?> (<?= View::e($s['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="invoice_date">Invoice Date</label>
                <input id="invoice_date" type="date" style="width:100%"
                       value="<?= View::e($v('invoice_date_str') ?: date('Y-m-d')) ?>">
            </div>
            <div>
                <label for="supplier_invoice_no">Bill No</label>
                <input id="supplier_invoice_no" maxlength="60" style="width:100%" value="<?= View::e($v('supplier_invoice_no')) ?>">
            </div>
            <div>
                <label for="challan_no">Challan No</label>
                <input id="challan_no" maxlength="60" style="width:100%" value="<?= View::e($v('challan_no')) ?>">
            </div>
            <div>
                <label for="po_no">PO No</label>
                <input id="po_no" maxlength="60" style="width:100%" value="<?= View::e($v('po_no')) ?>">
            </div>
            <div>
                <label for="note">Note</label>
                <input id="note" maxlength="255" style="width:100%" value="<?= View::e($v('note')) ?>">
            </div>
        </div>

        <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0">

        <div class="builder" style="margin-bottom:.85rem">
            <div style="flex:2 1 220px">
                <label for="b_product">Product</label>
                <select id="b_product"><option value="">— select —</option></select>
            </div>
            <div id="b_variant_wrap" style="display:none">
                <label for="b_variant">Variant</label>
                <select id="b_variant"></select>
            </div>
            <div style="flex:0 1 90px">
                <label for="b_qty">Qty</label>
                <input id="b_qty" type="number" step="0.01" min="0">
            </div>
            <div style="flex:0 1 110px">
                <label for="b_price">Unit Price</label>
                <input id="b_price" type="number" step="0.01" min="0">
            </div>
            <div style="flex:0 1 110px">
                <label for="b_sale_price" title="Applies to the whole product, not just this color/size">Sale Price</label>
                <input id="b_sale_price" type="number" step="0.01" min="0">
            </div>
            <div style="flex:0 0 auto">
                <button type="button" id="b_add">Add</button>
            </div>
        </div>

        <div class="scroll">
            <table id="cart">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Sale Price</th>
                    <th class="num">Line Total</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="cart-body">
                    <tr id="cart-empty"><td colspan="7" class="muted" style="text-align:center">No items yet</td></tr>
                </tbody>
                <tfoot>
                    <tr><td colspan="5" class="num">Sub Total</td><td class="num" id="f-sub">0.00</td><td></td></tr>
                    <tr>
                        <td colspan="5" class="num">Discount</td>
                        <td class="num"><input id="discount" type="number" step="0.01" min="0" style="width:100px;text-align:right"
                               value="<?= (float) $v('discount', 0) ?>"></td>
                        <td></td>
                    </tr>
                    <tr><td colspan="5" class="num"><strong>Total</strong></td><td class="num"><strong id="f-total">0.00</strong></td><td></td></tr>
                </tfoot>
            </table>
        </div>

        <div style="margin-top:1rem">
            <button type="submit"><?= $isEdit ? 'Update Purchase' : 'Save Purchase' ?></button>
            <a class="btn ghost" href="<?= View::e($appUrl . '/admin/purchases') ?>">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var PRELOAD  = <?= json_encode($purchase['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    var byId = {};
    PRODUCTS.forEach(function (p) { byId[p.id] = p; });

    var cart = []; // {product_id, variant_id, unit_id, unit_name, label, qty, unit_price, sale_price}

    var $ = function (id) { return document.getElementById(id); };

    // ---- প্রোডাক্ট ড্রপডাউন ভরা
    PRODUCTS.forEach(function (p) {
        var o = document.createElement('option');
        o.value = p.id;
        o.textContent = p.name + ' (' + p.sku + ')';
        $('b_product').appendChild(o);
    });

    // 0 মানে "এখনো কোনো real দাম নাই" — ইনপুটে খালি দেখাই, "0" না (যাতে ভুল করে
    // শূন্য দামে সাবমিট না হয়ে যায়; ইউজারকে আসল দাম টাইপ করতে হবে)
    function priceOrEmpty(value) {
        var n = parseFloat(value);
        return n > 0 ? n : '';
    }

    // ---- প্রোডাক্ট বদলালে ভ্যারিয়েন্ট ও দাম
    $('b_product').addEventListener('change', function () {
        var p = byId[parseInt(this.value, 10)];
        var wrap = $('b_variant_wrap');
        var sel = $('b_variant');
        sel.innerHTML = '';

        // sale price is product-wide (not per-variant) — prefill from the product either way
        $('b_sale_price').value = p ? priceOrEmpty(p.sale_price) : '';

        if (p && p.has_variant) {
            p.variants.forEach(function (vr) {
                var o = document.createElement('option');
                o.value = vr.id;
                o.textContent = vr.name;
                o.dataset.price = vr.purchase_price;
                sel.appendChild(o);
            });
            wrap.style.display = '';
            // প্রতিটা ভ্যারিয়েন্টের নিজের দাম — কোনো প্যারেন্ট/প্রোডাক্ট-লেভেল দামে fallback হবে না
            $('b_price').value = p.variants.length ? priceOrEmpty(p.variants[0].purchase_price) : '';
        } else {
            wrap.style.display = 'none';
            $('b_price').value = p ? priceOrEmpty(p.purchase_price) : '';
        }
    });

    $('b_variant').addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        $('b_price').value = opt ? priceOrEmpty(opt.dataset.price) : '';
    });

    // ---- কার্টে যোগ
    function addLine(productId, variantId, qty, price, salePrice) {
        var p = byId[productId];
        if (!p) { return; }

        var label = p.name, vId = 0;

        if (p.has_variant) {
            vId = variantId || (p.variants[0] && p.variants[0].id) || 0;
            var vr = p.variants.find(function (x) { return x.id === vId; });
            label += ' — ' + (vr ? vr.name : '?');
        }

        cart.push({
            product_id: productId,
            variant_id: vId,
            unit_id:    p.unit_id,
            unit_name:  p.unit_code,
            label:      label,
            qty:        qty,
            unit_price: price,
            sale_price: salePrice || 0
        });
        render();
    }

    $('b_add').addEventListener('click', function () {
        var pid = parseInt($('b_product').value, 10);
        var qty = parseFloat($('b_qty').value) || 0;
        var price = parseFloat($('b_price').value) || 0;
        var salePrice = parseFloat($('b_sale_price').value) || 0;
        var vid = $('b_variant_wrap').style.display === 'none' ? 0 : parseInt($('b_variant').value, 10) || 0;

        if (!pid || qty <= 0) { window.toast('e', 'Pick a product and quantity.'); return; }

        addLine(pid, vid, qty, price, salePrice);
        $('b_qty').value = '';
        $('b_sale_price').value = '';
    });

    function render() {
        var body = $('cart-body');
        body.innerHTML = '';

        if (cart.length === 0) {
            body.innerHTML = '<tr id="cart-empty"><td colspan="7" class="muted" style="text-align:center">No items yet</td></tr>';
        }

        var sub = 0;

        cart.forEach(function (line, i) {
            var lt = line.qty * line.unit_price;
            sub += lt;

            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + line.label + '</td>' +
                '<td>' + line.unit_name + '</td>' +
                '<td class="num">' + line.qty + '</td>' +
                '<td class="num">' + line.unit_price.toFixed(2) + '</td>' +
                '<td class="num">' + (line.sale_price ? line.sale_price.toFixed(2) : '—') + '</td>' +
                '<td class="num">' + lt.toFixed(2) + '</td>' +
                '<td><button type="button" class="ghost" data-rm="' + i + '">x</button></td>';
            body.appendChild(tr);
        });

        var disc = parseFloat($('discount').value) || 0;
        $('f-sub').textContent = sub.toFixed(2);
        $('f-total').textContent = (sub - disc).toFixed(2);

        body.querySelectorAll('[data-rm]').forEach(function (b) {
            b.addEventListener('click', function () {
                cart.splice(parseInt(this.dataset.rm, 10), 1);
                render();
            });
        });
    }

    $('discount').addEventListener('input', render);

    // ---- এডিটে পুরনো লাইন লোড
    PRELOAD.forEach(function (it) {
        cart.push({
            product_id: it.product_id,
            variant_id: it.variant_id,
            unit_id:    it.unit_id,
            unit_name:  it.unit_code,
            label:      it.product_name,
            qty:        it.qty,
            unit_price: it.unit_price,
            sale_price: it.sale_price
        });
    });
    render();

    // ---- সাবমিট
    $('purchase-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        if (cart.length === 0) { window.toast('e', 'Add at least one item.'); return; }

        var body = {
            supplier_id:         parseInt($('supplier_id').value, 10) || 0,
            invoice_date:        $('invoice_date').value,
            supplier_invoice_no: $('supplier_invoice_no').value,
            challan_no:          $('challan_no').value,
            po_no:               $('po_no').value,
            note:                $('note').value,
            discount:            parseFloat($('discount').value) || 0,
            items: cart.map(function (l) {
                return {
                    product_id: l.product_id,
                    variant_id: l.variant_id,
                    unit_id:    l.unit_id,
                    qty:        l.qty,
                    unit_price: l.unit_price,
                    sale_price: l.sale_price || 0
                };
            })
        };

        var id = parseInt($('id').value, 10) || 0;
        var result = id > 0
            ? await api('/purchases/' + id, body, 'PUT')
            : await api('/purchases', body, 'POST');

        if (result) { location.href = '<?= View::e($appUrl) ?>/admin/purchases'; }
    });
})();
</script>
