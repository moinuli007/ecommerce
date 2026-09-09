<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $groups */
?>
<style>
    .add-row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end; margin-top:.85rem; }
    .add-row input, .add-row select { max-width:170px; }
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    button.sm { padding:.25rem .55rem; font-size:.78rem; }
</style>

<div class="card">
    <p class="muted" style="margin:0">
        Units in the same group convert to each other. Each group has exactly one <strong>base unit</strong>
        with a conversion rate of 1. The rest are relative to the base —
        <em>Dozen at rate 12 means 1 dozen = 12 pieces</em>.
        Stock is always stored in the base unit.
    </p>
</div>

<?php foreach ($groups as $group): ?>
    <div class="card">
        <h2 class="card-title">
            <?= View::e($group['name']) ?>
            <?php if ((int) $group['isActive'] !== 1): ?><span class="pill">Off</span><?php endif; ?>
            <span class="muted" style="margin-left:auto;font-size:.82rem"><?= count($group['units']) ?> units</span>
        </h2>

        <?php if ($group['units'] === []): ?>
            <p class="muted">No units in this group yet.</p>
        <?php else: ?>
            <div class="scroll">
                <table>
                    <thead>
                    <tr>
                        <th>Name</th><th>Code</th><th class="num">Conversion Rate</th>
                        <th>Base</th><th>Status</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['units'] as $unit): ?>
                        <tr>
                            <td><?= View::e($unit['name']) ?></td>
                            <td><code class="muted"><?= View::e($unit['code']) ?></code></td>
                            <td class="num"><?= rtrim(rtrim(number_format($unit['conversion'], 6), '0'), '.') ?></td>
                            <td><?= $unit['is_base'] ? '<span class="pill">Base</span>' : '' ?></td>
                            <td><?= $unit['isActive'] ? 'Active' : '<span class="muted">Off</span>' ?></td>
                            <td><button type="button" class="ghost sm" data-del-unit="<?= (int) $unit['id'] ?>">Delete</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form class="add-row" data-group="<?= (int) $group['id'] ?>">
            <div>
                <label>Name</label>
                <input name="name" required maxlength="100" placeholder="e.g. Carton">
            </div>
            <div>
                <label>Code</label>
                <input name="code" required maxlength="20" placeholder="ctn">
            </div>
            <div>
                <label>Conversion Rate</label>
                <input name="conversion" type="number" step="0.000001" min="0.000001" value="1"
                       title="1 of this unit = how many base units">
            </div>
            <button type="submit">Add</button>
        </form>
    </div>
<?php endforeach; ?>

<div class="card">
    <h2 class="card-title">New Unit Group</h2>
    <form id="group-form" class="add-row" style="margin-top:0">
        <div>
            <label>Name</label>
            <input name="name" required maxlength="100" placeholder="e.g. Volume">
        </div>
        <div>
            <label>Order</label>
            <input name="sort_order" type="number" value="<?= count($groups) + 1 ?>" style="max-width:80px">
        </div>
        <button type="submit">Add</button>
    </form>
</div>

<script>
(function () {
    document.querySelectorAll('form[data-group]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const body = Object.fromEntries(new FormData(form).entries());
            body.unit_group_id = parseInt(form.dataset.group, 10);

            if (await api('/units', body, 'POST')) { location.reload(); }
        });
    });

    document.querySelectorAll('[data-del-unit]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this unit?')) { return; }

            if (await api('/units/' + this.dataset.delUnit, null, 'DELETE')) { location.reload(); }
        });
    });

    document.getElementById('group-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        const body = Object.fromEntries(new FormData(this).entries());

        if (await api('/unit-groups', body, 'POST')) { location.reload(); }
    });
})();
</script>
