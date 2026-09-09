<?php

use App\Core\View;

/** @var array<string,mixed> $supplier — এডিটে ভরা, নতুনে খালি */
/** @var string $appUrl */

$isEdit = ($supplier['id'] ?? 0) > 0;
$v = static fn (string $key, mixed $default = '') => $supplier[$key] ?? $default;
?>
<div class="card" style="max-width:560px">
    <form id="supplier-form">
        <input type="hidden" id="id" value="<?= (int) ($supplier['id'] ?? 0) ?>">

        <div style="margin-bottom:.85rem">
            <label for="name">Name *</label>
            <input id="name" name="name" required maxlength="150" style="width:100%"
                   value="<?= View::e($v('name')) ?>">
        </div>

        <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:.85rem">
            <div style="flex:1; min-width:180px">
                <label for="phone">Phone</label>
                <input id="phone" name="phone" maxlength="30" style="width:100%"
                       value="<?= View::e($v('phone')) ?>">
            </div>
            <div style="flex:1; min-width:180px">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="150" style="width:100%"
                       value="<?= View::e($v('email')) ?>">
            </div>
        </div>

        <div style="margin-bottom:.85rem">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="2" maxlength="255" style="width:100%"><?= View::e($v('address')) ?></textarea>
        </div>

        <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem">
            <div style="flex:1; min-width:180px">
                <label for="opening_balance">Opening Balance (payable)</label>
                <input id="opening_balance" name="opening_balance" type="number" step="0.01" min="0" style="width:100%"
                       value="<?= (float) $v('opening_balance', 0) ?>">
                <small class="muted">Amount owed to this supplier at start. Posts an Opening voucher.</small>
            </div>
            <div style="flex:0 0 auto">
                <label for="isActive">Status</label>
                <select id="isActive" name="isActive">
                    <option value="1" <?= (int) $v('isActive', 1) === 1 ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= (int) $v('isActive', 1) === 0 ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <button type="submit"><?= $isEdit ? 'Update' : 'Save' ?></button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/suppliers') ?>">Cancel</a>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('supplier-form');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        var id = parseInt(document.getElementById('id').value, 10) || 0;

        var body = {
            name:            document.getElementById('name').value,
            phone:           document.getElementById('phone').value,
            email:           document.getElementById('email').value,
            address:         document.getElementById('address').value,
            opening_balance: parseFloat(document.getElementById('opening_balance').value) || 0,
            isActive:        parseInt(document.getElementById('isActive').value, 10)
        };

        var result = id > 0
            ? await api('/suppliers/' + id, body, 'PUT')
            : await api('/suppliers', body, 'POST');

        if (result) {
            location.href = '<?= View::e($appUrl) ?>/admin/suppliers';
        }
    });
})();
</script>
