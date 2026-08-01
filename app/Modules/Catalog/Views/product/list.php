<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $products */
/** @var array<string,mixed> $pagination */
/** @var array<int,array{id:int,label:string}> $categories */
/** @var array<string,string> $filters */
/** @var string $appUrl */
?>
<style>
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
    .pill.warn { background:rgba(217,119,6,.14); color:var(--warning); }
    .thumb {
        width:38px; height:38px; border-radius:7px; object-fit:cover; background:var(--bg);
        border:1px solid var(--line); display:grid; place-items:center; color:var(--muted); font-size:.65rem;
    }
    .strike { text-decoration:line-through; color:var(--muted); font-size:.8rem; }
    button.sm { padding:.25rem .55rem; font-size:.78rem; }
</style>

<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/products') ?>">
        <div>
            <label for="q">খুঁজুন</label>
            <input id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="নাম বা SKU">
        </div>
        <div>
            <label for="category_id">ক্যাটাগরি</label>
            <select id="category_id" name="category_id">
                <option value="">সব</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                        <?= (string) $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= View::e($category['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="isActive">অবস্থা</label>
            <select id="isActive" name="isActive">
                <option value="">সব</option>
                <option value="1" <?= $filters['isActive'] === '1' ? 'selected' : '' ?>>চালু</option>
                <option value="0" <?= $filters['isActive'] === '0' ? 'selected' : '' ?>>বন্ধ</option>
            </select>
        </div>
        <button type="submit">খুঁজুন</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/products/create') ?>">+ নতুন প্রোডাক্ট</a>
    </form>
</div>

<div class="card">
    <?php if ($products === []): ?>
        <div class="empty">
            কোনো প্রোডাক্ট পাওয়া যায়নি।
            <a href="<?= View::e($appUrl . '/admin/products/create') ?>" style="color:var(--brand)">প্রথমটা যোগ করুন</a>।
        </div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th></th>
                    <th>নাম</th>
                    <th>SKU</th>
                    <th>ক্যাটাগরি</th>
                    <th class="num">দাম</th>
                    <th class="num">স্টক</th>
                    <th>ভ্যারিয়েন্ট</th>
                    <th>অবস্থা</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <?php if ($product['image'] !== ''): ?>
                                <img class="thumb" src="<?= View::e($appUrl . '/' . ltrim($product['image'], '/')) ?>" alt="">
                            <?php else: ?>
                                <div class="thumb">—</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/products/' . $product['id'] . '/edit') ?>">
                                <?= View::e($product['name']) ?>
                            </a>
                            <?php if ($product['isNew']): ?><span class="pill">New</span><?php endif; ?>
                            <?php if ($product['isFeatured']): ?><span class="pill">Bestseller</span><?php endif; ?>
                        </td>
                        <td><code class="muted"><?= View::e($product['sku']) ?></code></td>
                        <td><?= View::e($product['category_name']) ?></td>
                        <td class="num">
                            <?php if ($product['on_offer']): ?>
                                <span class="strike"><?= number_format($product['regular_price'], 2) ?></span><br>
                                <strong><?= number_format($product['price'], 2) ?></strong>
                                <span class="pill">-<?= $product['discount_percent'] ?>%</span>
                            <?php else: ?>
                                <?= number_format($product['price'], 2) ?>
                            <?php endif; ?>
                        </td>
                        <td class="num">
                            <?= rtrim(rtrim(number_format($product['stock'], 2), '0'), '.') ?>
                            <span class="muted"><?= View::e($product['unit_code']) ?></span>
                            <?php if ($product['low_stock']): ?><span class="pill warn">কম</span><?php endif; ?>
                        </td>
                        <td><?= $product['has_variant'] ? (int) $product['variant_count'] . ' টি' : '<span class="muted">—</span>' ?></td>
                        <td><?= $product['isActive'] ? 'চালু' : '<span class="pill off">বন্ধ</span>' ?></td>
                        <td><button type="button" class="ghost sm" data-del="<?= (int) $product['id'] ?>">ডিলিট</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($pagination['pages'] ?? 1) > 1): ?>
            <p class="muted">
                মোট <?= (int) $pagination['total'] ?> টি —
                পৃষ্ঠা <?= (int) $pagination['page'] ?> / <?= (int) $pagination['pages'] ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('[data-del]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        if (!confirm('এই প্রোডাক্টটা ডিলিট করবেন?')) { return; }

        if (await api('/products/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
    });
});
</script>
