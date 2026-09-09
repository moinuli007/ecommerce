<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $purchases — id, code, supplier_name */
/** @var string $appUrl */
?>
<div class="card" style="max-width:820px">
    <form id="ret-form">
        <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:flex-end; margin-bottom:.85rem">
            <div style="flex:2 1 240px">
                <label for="purchase_id">From Purchase *</label>
                <select id="purchase_id" style="width:100%" required>
                    <option value="">— select —</option>
                    <?php foreach ($purchases as $p): ?>
                        <option value="<?= (int) $p['id'] ?>">
                            <?= View::e($p['code']) ?> — <?= View::e($p['supplier_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="return_date">Return Date</label>
                <input id="return_date" type="date" value="<?= date('Y-m-d') ?>">
            </div>
            <div style="flex:2 1 200px">
                <label for="note">Note</label>
                <input id="note" maxlength="255" style="width:100%">
            </div>
        </div>

        <div class="scroll">
            <table>
                <thead><tr><th>Product</th><th class="num">Purchased</th><th class="num">Unit Price</th><th class="num">Return Qty</th></tr></thead>
                <tbody id="lines"><tr><td colspan="4" class="muted" style="text-align:center">Pick a purchase</td></tr></tbody>
            </table>
        </div>

        <div style="margin-top:1rem">
            <button type="submit">Save Return</button>
            <a class="btn ghost" href="<?= View::e($appUrl . '/admin/stock/returns') ?>">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var current = [];

    $('purchase_id').addEventListener('change', async function () {
        var id = parseInt(this.value, 10) || 0;
        var body = $('lines');
        current = [];

        if (!id) { body.innerHTML = '<tr><td colspan="4" class="muted" style="text-align:center">Pick a purchase</td></tr>'; return; }

        var data = await api('/purchases/' + id);
        if (!data || !data.purchase) { return; }

        current = data.purchase.items;
        body.innerHTML = '';

        current.forEach(function (it, i) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + it.product_name + '</td>' +
                '<td class="num">' + it.qty + ' ' + it.unit_code + '</td>' +
                '<td class="num">' + it.unit_price.toFixed(2) + '</td>' +
                '<td class="num"><input type="number" step="0.01" min="0" max="' + it.qty +
                '" data-i="' + i + '" style="width:90px;text-align:right"></td>';
            body.appendChild(tr);
        });
    });

    $('ret-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        var items = [];
        document.querySelectorAll('#lines input[data-i]').forEach(function (inp) {
            var qty = parseFloat(inp.value) || 0;
            if (qty > 0) {
                var it = current[parseInt(inp.dataset.i, 10)];
                items.push({
                    product_id: it.product_id,
                    variant_id: it.variant_id,
                    qty: qty,
                    unit_price: it.unit_price
                });
            }
        });

        if (items.length === 0) { window.toast('e', 'Enter a return quantity on at least one line.'); return; }

        var res = await api('/stock/returns', {
            purchase_id: parseInt($('purchase_id').value, 10),
            return_date: $('return_date').value,
            note: $('note').value,
            items: items
        }, 'POST');

        if (res) { location.href = '<?= View::e($appUrl) ?>/admin/stock/returns'; }
    });
})();
</script>
