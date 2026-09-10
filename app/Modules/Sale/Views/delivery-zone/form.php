<?php

use App\Core\View;

/** @var array<string,mixed> $zone — এডিটে ভরা, নতুনে খালি */
/** @var string $appUrl */

$isEdit = ($zone['id'] ?? 0) > 0;
$v = static fn (string $key, mixed $default = '') => $zone[$key] ?? $default;
?>
<div class="card" style="max-width:560px">
    <form id="zone-form">
        <input type="hidden" id="id" value="<?= (int) ($zone['id'] ?? 0) ?>">

        <div style="margin-bottom:.85rem">
            <label for="name">Name *</label>
            <input id="name" name="name" required maxlength="100" style="width:100%"
                   value="<?= View::e($v('name')) ?>" placeholder="e.g. Inside Dhaka">
        </div>

        <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:.85rem">
            <div style="flex:1; min-width:180px">
                <label for="fee">Delivery Fee (৳) *</label>
                <input id="fee" name="fee" type="number" step="0.01" min="0" required style="width:100%"
                       value="<?= (float) $v('fee', 0) ?>">
            </div>
            <div style="flex:1; min-width:180px">
                <label for="free_delivery_threshold">Free Delivery Over (৳)</label>
                <input id="free_delivery_threshold" name="free_delivery_threshold" type="number" step="0.01" min="0" style="width:100%"
                       value="<?= (float) $v('free_delivery_threshold', 0) ?>">
                <small class="muted">0 = no free delivery in this zone.</small>
            </div>
        </div>

        <div style="display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem">
            <div style="flex:1; min-width:140px">
                <label for="sort_order">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" step="1" style="width:100%"
                       value="<?= (int) $v('sort_order', 0) ?>">
            </div>
            <div style="flex:0 0 auto">
                <label for="is_default">Default at checkout</label>
                <select id="is_default" name="is_default">
                    <option value="0" <?= (int) $v('is_default', 0) === 0 ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= (int) $v('is_default', 0) === 1 ? 'selected' : '' ?>>Yes</option>
                </select>
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
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/delivery-zones') ?>">Cancel</a>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('zone-form');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        var id = parseInt(document.getElementById('id').value, 10) || 0;

        var body = {
            name:                     document.getElementById('name').value,
            fee:                      parseFloat(document.getElementById('fee').value) || 0,
            free_delivery_threshold: parseFloat(document.getElementById('free_delivery_threshold').value) || 0,
            sort_order:               parseInt(document.getElementById('sort_order').value, 10) || 0,
            is_default:               parseInt(document.getElementById('is_default').value, 10),
            isActive:                 parseInt(document.getElementById('isActive').value, 10)
        };

        var result = id > 0
            ? await api('/delivery-zones/' + id, body, 'PUT')
            : await api('/delivery-zones', body, 'POST');

        if (result) {
            location.href = '<?= View::e($appUrl) ?>/admin/delivery-zones';
        }
    });
})();
</script>
