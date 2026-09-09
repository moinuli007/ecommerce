<?php

use App\Core\View;

/** @var array<string,mixed> $adjustment — এডিটে ভরা */
/** @var array<int,array<string,mixed>> $products */
/** @var string $appUrl */

$isEdit = ($adjustment['id'] ?? 0) > 0;
$v = static fn (string $k, mixed $d = '') => $adjustment[$k] ?? $d;
?>
<style>
    .builder { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; margin-bottom:.85rem; }
    .builder > div { flex:1 1 130px; }
    .builder input, .builder select { width:100%; }
</style>

<div class="card" style="max-width:820px">
    <form id="adj-form">
        <input type="hidden" id="id" value="<?= (int) ($adjustment['id'] ?? 0) ?>">

        <div class="builder">
            <div>
                <label for="reason">Reason</label>
                <select id="reason">
                    <option value="1" <?= (int) $v('reason', 1) === 1 ? 'selected' : '' ?>>Opening Stock</option>
                    <option value="2" <?= (int) $v('reason', 1) === 2 ? 'selected' : '' ?>>Damage / Loss</option>
                    <option value="3" <?= (int) $v('reason', 1) === 3 ? 'selected' : '' ?>>Count Correction</option>
                </select>
            </div>
            <div>
                <label for="adjust_date">Date</label>
                <input id="adjust_date" type="date" value="<?= View::e($v('adjust_date_str') ?: date('Y-m-d')) ?>">
            </div>
            <div style="flex:2 1 200px">
                <label for="note">Note</label>
                <input id="note" maxlength="255" value="<?= View::e($v('note')) ?>">
            </div>
        </div>

        <div class="builder">
            <div style="flex:2 1 220px">
                <label for="b_product">Product</label>
                <select id="b_product"><option value="">— select —</option></select>
            </div>
            <div id="b_variant_wrap" style="display:none">
                <label for="b_variant">Variant</label>
                <select id="b_variant"></select>
            </div>
            <div style="flex:0 1 100px">
                <label for="b_qty">Qty (± )</label>
                <input id="b_qty" type="number" step="0.01">
            </div>
            <div style="flex:0 1 110px">
                <label for="b_rate">Unit Cost</label>
                <input id="b_rate" type="number" step="0.01" min="0">
            </div>
            <div><button type="button" id="b_add">Add</button></div>
        </div>

        <div class="scroll">
            <table id="cart">
                <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Unit Cost</th><th class="num">Value</th><th></th></tr></thead>
                <tbody id="cart-body"><tr><td colspan="5" class="muted" style="text-align:center">No lines</td></tr></tbody>
                <tfoot><tr><td colspan="3" class="num"><strong>Net Value</strong></td><td class="num"><strong id="f-net">0.00</strong></td><td></td></tr></tfoot>
            </table>
        </div>

        <div style="margin-top:1rem">
            <button type="submit"><?= $isEdit ? 'Update' : 'Save' ?></button>
            <a class="btn ghost" href="<?= View::e($appUrl . '/admin/stock/adjustments') ?>">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var PRELOAD  = <?= json_encode($adjustment['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var byId = {}; PRODUCTS.forEach(function (p) { byId[p.id] = p; });
    var cart = [];
    var $ = function (id) { return document.getElementById(id); };

    PRODUCTS.forEach(function (p) {
        var o = document.createElement('option');
        o.value = p.id; o.textContent = p.name + ' (' + p.sku + ')';
        $('b_product').appendChild(o);
    });

    $('b_product').addEventListener('change', function () {
        var p = byId[parseInt(this.value, 10)];
        var sel = $('b_variant'); sel.innerHTML = '';
        if (p && p.has_variant) {
            p.variants.forEach(function (vr) {
                var o = document.createElement('option'); o.value = vr.id; o.textContent = vr.name;
                o.dataset.price = vr.purchase_price; sel.appendChild(o);
            });
            $('b_variant_wrap').style.display = '';
            $('b_rate').value = p.variants.length ? p.variants[0].purchase_price : '';
        } else {
            $('b_variant_wrap').style.display = 'none';
            $('b_rate').value = p ? p.purchase_price : '';
        }
    });

    $('b_add').addEventListener('click', function () {
        var pid = parseInt($('b_product').value, 10);
        var qty = parseFloat($('b_qty').value) || 0;
        var rate = parseFloat($('b_rate').value) || 0;
        var vid = $('b_variant_wrap').style.display === 'none' ? 0 : parseInt($('b_variant').value, 10) || 0;
        var p = byId[pid];
        if (!p || qty === 0) { window.toast('e', 'Pick a product and a non-zero qty.'); return; }

        var label = p.name;
        if (p.has_variant) {
            var vr = p.variants.find(function (x) { return x.id === vid; });
            label += ' — ' + (vr ? vr.name : '?');
        }
        cart.push({ product_id: pid, variant_id: vid, label: label, qty: qty, rate: rate });
        $('b_qty').value = '';
        render();
    });

    function render() {
        var body = $('cart-body'); body.innerHTML = '';
        if (cart.length === 0) { body.innerHTML = '<tr><td colspan="5" class="muted" style="text-align:center">No lines</td></tr>'; }
        var net = 0;
        cart.forEach(function (l, i) {
            net += l.qty * l.rate;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + l.label + '</td><td class="num">' + l.qty + '</td><td class="num">' +
                l.rate.toFixed(2) + '</td><td class="num">' + (l.qty * l.rate).toFixed(2) +
                '</td><td><button type="button" class="ghost" data-rm="' + i + '">x</button></td>';
            body.appendChild(tr);
        });
        $('f-net').textContent = net.toFixed(2);
        body.querySelectorAll('[data-rm]').forEach(function (b) {
            b.addEventListener('click', function () { cart.splice(parseInt(this.dataset.rm, 10), 1); render(); });
        });
    }

    PRELOAD.forEach(function (it) {
        cart.push({ product_id: it.product_id, variant_id: it.variant_id, label: it.product_name, qty: it.qty, rate: it.rate });
    });
    render();

    $('adj-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (cart.length === 0) { window.toast('e', 'Add at least one line.'); return; }
        var body = {
            reason: parseInt($('reason').value, 10),
            adjust_date: $('adjust_date').value,
            note: $('note').value,
            items: cart.map(function (l) {
                return { product_id: l.product_id, variant_id: l.variant_id, qty: l.qty, rate: l.rate };
            })
        };
        var id = parseInt($('id').value, 10) || 0;
        var res = id > 0 ? await api('/stock/adjustments/' + id, body, 'PUT') : await api('/stock/adjustments', body, 'POST');
        if (res) { location.href = '<?= View::e($appUrl) ?>/admin/stock/adjustments'; }
    });
})();
</script>
