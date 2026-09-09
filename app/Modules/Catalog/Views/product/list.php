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
            <label for="q">Search</label>
            <input id="q" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Name or SKU">
        </div>
        <div>
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
                <option value="">All</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                        <?= (string) $filters['category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= View::e($category['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="isActive">Status</label>
            <select id="isActive" name="isActive">
                <option value="">All</option>
                <option value="1" <?= $filters['isActive'] === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $filters['isActive'] === '0' ? 'selected' : '' ?>>Off</option>
            </select>
        </div>
        <button type="submit">Search</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/products/create') ?>">+ New Product</a>
    </form>
</div>

<div class="card">
    <?php if ($products === []): ?>
        <div class="empty">
            No products found.
            <a href="<?= View::e($appUrl . '/admin/products/create') ?>" style="color:var(--brand)">Add the first one</a>.
        </div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="num">Price</th>
                    <th class="num">Stock</th>
                    <th>Variants</th>
                    <th>Status</th>
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
                            <?php if ($product['low_stock']): ?><span class="pill warn">Low</span><?php endif; ?>
                        </td>
                        <td><?= $product['has_variant'] ? (int) $product['variant_count'] : '<span class="muted">—</span>' ?></td>
                        <td><?= $product['isActive'] ? 'Active' : '<span class="pill off">Off</span>' ?></td>
                        <td style="white-space:nowrap">
                            <a class="btn ghost sm" href="<?= View::e($appUrl . '/admin/products/' . $product['id'] . '/edit') ?>">Edit</a>
                            <button type="button" class="ghost sm danger" data-del="<?= (int) $product['id'] ?>">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($pagination['pages'] ?? 1) > 1): ?>
            <p class="muted">
                Total <?= (int) $pagination['total'] ?> —
                page <?= (int) $pagination['page'] ?> / <?= (int) $pagination['pages'] ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('[data-del]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        if (!confirm('Delete this product?')) { return; }

        if (await api('/products/' + this.dataset.del, null, 'DELETE')) { location.reload(); }
    });
});
</script>
