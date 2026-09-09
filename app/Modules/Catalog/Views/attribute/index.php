<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $attributes */
?>
<style>
    .attr-card { margin-bottom:1rem; }
    .attr-head { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin-bottom:.85rem; }
    .attr-head h2 { margin:0; font-size:1rem; }
    .chips { display:flex; flex-wrap:wrap; gap:.45rem; margin-bottom:.9rem; }
    .chip {
        display:inline-flex; align-items:center; gap:.4rem; padding:.32rem .6rem;
        border:1px solid var(--line); border-radius:99px; font-size:.85rem; background:var(--card);
    }
    .chip.off { opacity:.45; }
    .chip .swatch { width:14px; height:14px; border-radius:50%; border:1px solid var(--line); flex:0 0 auto; }
    .chip .x { cursor:pointer; color:var(--muted); font-weight:700; line-height:1; }
    .chip .x:hover { color:var(--danger); }
    .add-row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; }
    .add-row input { max-width:150px; }
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
</style>

<div class="card">
    <p class="muted" style="margin:0">
        Attributes are what generate product variants — like Size (S/M/L/XL/XXL) and Color.
        Pick values on the product form and every combination is generated automatically.
        <br>
        <strong>Note:</strong> once a value is used by a variant it can't be deleted
        (old orders would lose which size they had) — you can only deactivate it.
    </p>
</div>

<?php foreach ($attributes as $attribute): ?>
    <div class="card attr-card">
        <div class="attr-head">
            <h2><?= View::e($attribute['name']) ?></h2>
            <code class="muted"><?= View::e($attribute['code']) ?></code>
            <span class="pill"><?= View::e($attribute['type']) ?></span>
            <span class="muted" style="margin-left:auto"><?= count($attribute['values']) ?> values</span>
        </div>

        <?php if ($attribute['values'] === []): ?>
            <p class="muted">No values yet.</p>
        <?php else: ?>
            <div class="chips">
                <?php foreach ($attribute['values'] as $value): ?>
                    <span class="chip <?= $value['isActive'] ? '' : 'off' ?>">
                        <?php if ($value['color_hex'] !== ''): ?>
                            <span class="swatch" style="background:<?= View::e($value['color_hex']) ?>"></span>
                        <?php endif; ?>
                        <?= View::e($value['value']) ?>
                        <code class="muted" style="font-size:.72rem"><?= View::e($value['code']) ?></code>
                        <span class="x" data-del-value="<?= (int) $value['id'] ?>" title="Delete">×</span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="add-row" data-attribute="<?= (int) $attribute['id'] ?>">
            <div>
                <label>New Value</label>
                <input name="value" required maxlength="100"
                       placeholder="<?= $attribute['code'] === 'size' ? 'e.g. XXL' : 'e.g. Navy' ?>">
            </div>
            <div>
                <label>Code <span class="muted">(used in SKU)</span></label>
                <input name="code" maxlength="50" placeholder="<?= $attribute['code'] === 'size' ? 'XXL' : 'NVY' ?>">
            </div>
            <?php if ($attribute['type'] === 'color'): ?>
                <div>
                    <label>Color</label>
                    <input name="color_hex" type="color" value="#1f2a44" style="padding:.2rem;height:36px;width:56px">
                </div>
            <?php endif; ?>
            <div>
                <label>Order</label>
                <input name="sort_order" type="number" value="<?= count($attribute['values']) + 1 ?>" style="max-width:80px">
            </div>
            <button type="submit">Add</button>
        </form>
    </div>
<?php endforeach; ?>

<div class="card">
    <h2 class="card-title">New Attribute</h2>
    <form id="attr-form" class="add-row">
        <div>
            <label>Name</label>
            <input name="name" required maxlength="100" placeholder="e.g. Fit">
        </div>
        <div>
            <label>Code</label>
            <input name="code" maxlength="30" placeholder="fit">
        </div>
        <div>
            <label>Type</label>
            <select name="type">
                <option value="select">Standard (select)</option>
                <option value="color">Color (swatch)</option>
            </select>
        </div>
        <button type="submit">Add</button>
    </form>
</div>

<script>
(function () {
    // ভ্যালু যোগ
    document.querySelectorAll('form[data-attribute]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const body = Object.fromEntries(new FormData(form).entries());
            body.attribute_id = parseInt(form.dataset.attribute, 10);

            if (await api('/attribute-values', body, 'POST')) { location.reload(); }
        });
    });

    // ভ্যালু ডিলিট
    document.querySelectorAll('[data-del-value]').forEach(function (x) {
        x.addEventListener('click', async function () {
            if (!confirm('Delete this value?')) { return; }

            if (await api('/attribute-values/' + this.dataset.delValue, null, 'DELETE')) {
                location.reload();
            }
        });
    });

    // নতুন অ্যাট্রিবিউট
    document.getElementById('attr-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        const body = Object.fromEntries(new FormData(this).entries());

        if (await api('/attributes', body, 'POST')) { location.reload(); }
    });
})();
</script>
