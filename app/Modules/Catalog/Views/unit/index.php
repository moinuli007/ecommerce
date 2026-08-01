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
        একই গ্রুপের ইউনিটগুলো একে অন্যে রূপান্তরযোগ্য। প্রতি গ্রুপে ঠিক একটাই <strong>বেস ইউনিট</strong>
        থাকে, যার রূপান্তরের হার ১। বাকিরা বেসের সাপেক্ষে —
        <em>Dozen এর হার ১২ মানে ১ ডজন = ১২ পিস</em>।
        স্টক সবসময় বেস ইউনিটে জমা হয়।
    </p>
</div>

<?php foreach ($groups as $group): ?>
    <div class="card">
        <h2 class="card-title">
            <?= View::e($group['name']) ?>
            <?php if ((int) $group['isActive'] !== 1): ?><span class="pill">বন্ধ</span><?php endif; ?>
            <span class="muted" style="margin-left:auto;font-size:.82rem"><?= count($group['units']) ?> টি ইউনিট</span>
        </h2>

        <?php if ($group['units'] === []): ?>
            <p class="muted">এই গ্রুপে কোনো ইউনিট নাই।</p>
        <?php else: ?>
            <div class="scroll">
                <table>
                    <thead>
                    <tr>
                        <th>নাম</th><th>কোড</th><th class="num">রূপান্তরের হার</th>
                        <th>বেস</th><th>অবস্থা</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($group['units'] as $unit): ?>
                        <tr>
                            <td><?= View::e($unit['name']) ?></td>
                            <td><code class="muted"><?= View::e($unit['code']) ?></code></td>
                            <td class="num"><?= rtrim(rtrim(number_format($unit['conversion'], 6), '0'), '.') ?></td>
                            <td><?= $unit['is_base'] ? '<span class="pill">বেস</span>' : '' ?></td>
                            <td><?= $unit['isActive'] ? 'চালু' : '<span class="muted">বন্ধ</span>' ?></td>
                            <td><button type="button" class="ghost sm" data-del-unit="<?= (int) $unit['id'] ?>">ডিলিট</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <form class="add-row" data-group="<?= (int) $group['id'] ?>">
            <div>
                <label>নাম</label>
                <input name="name" required maxlength="100" placeholder="যেমন Carton">
            </div>
            <div>
                <label>কোড</label>
                <input name="code" required maxlength="20" placeholder="ctn">
            </div>
            <div>
                <label>রূপান্তরের হার</label>
                <input name="conversion" type="number" step="0.000001" min="0.000001" value="1"
                       title="১ এই ইউনিট = কত বেস ইউনিট">
            </div>
            <button type="submit">যোগ করুন</button>
        </form>
    </div>
<?php endforeach; ?>

<div class="card">
    <h2 class="card-title">নতুন ইউনিট গ্রুপ</h2>
    <form id="group-form" class="add-row" style="margin-top:0">
        <div>
            <label>নাম</label>
            <input name="name" required maxlength="100" placeholder="যেমন Volume">
        </div>
        <div>
            <label>ক্রম</label>
            <input name="sort_order" type="number" value="<?= count($groups) + 1 ?>" style="max-width:80px">
        </div>
        <button type="submit">যোগ করুন</button>
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
            if (!confirm('এই ইউনিটটা ডিলিট করবেন?')) { return; }

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
