<?php

use App\Core\View;

/** @var array<string,mixed> $category — খালি হলে "all" */
/** @var array<int,array<string,mixed>> $products */
/** @var array<string,mixed> $pagination — total, page, per_page, pages */
/** @var string $sort */
/** @var string $slug */
/** @var string $appUrl */

$base = $appUrl . '/collections/' . $slug;
$sortOptions = ['' => 'Featured', 'newest' => 'Newest', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low'];
?>
<style>
    .coll-head { padding:2rem 0 1rem; }
    .coll-head h1 { margin:0 0 .3rem; font-size:1.6rem; font-weight:800; }
    .coll-toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:1.5rem 1rem; }
    .pager { display:flex; gap:.4rem; justify-content:center; margin:2.5rem 0; }
    .pager a, .pager span {
        min-width:2.2rem; height:2.2rem; border-radius:6px; display:grid; place-items:center;
        font-size:.85rem; border:1px solid var(--line);
    }
    .pager a:hover { border-color:var(--accent); color:var(--accent); }
    .pager .current { background:var(--brand); color:#fff; border-color:var(--brand); font-weight:700; }

    .sort-control { display:flex; align-items:center; gap:.3rem; }
    .sort-control select {
        appearance:none; -webkit-appearance:none; -moz-appearance:none;
        border:none; background-color:transparent; margin:0;
        padding:.3rem 1.2rem .3rem 0;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2317181a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat:no-repeat; background-position:right center; background-size:12px;
        font-size:.85rem; font-weight:500; color:var(--fg); cursor:pointer;
    }
    .sort-control select:hover { color:var(--accent); }
    .sort-control select:focus { outline:none; }
</style>

<div class="wrap">
    <div class="coll-head">
        <h1><?= View::e($category['name'] ?? 'All Products') ?></h1>
        <?php if (($category['description'] ?? '') !== ''): ?>
            <p class="muted"><?= View::e($category['description']) ?></p>
        <?php endif; ?>
    </div>

    <div class="coll-toolbar">
        <span class="muted"><?= (int) $pagination['total'] ?> products</span>
        <form method="get" action="<?= View::e($base) ?>" class="sort-control">
            <label for="sort" style="font-size:.85rem">Sort:</label>
            <select id="sort" name="sort" onchange="this.form.submit()">
                <?php foreach ($sortOptions as $value => $label): ?>
                    <option value="<?= View::e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($products === []): ?>
        <div class="empty" style="padding:4rem 1rem; text-align:center; color:var(--muted)">No products found in this collection.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($products as $p): ?>
                <?= View::render('Sale::storefront/_product-card', ['product' => $p]) ?>
            <?php endforeach; ?>
        </div>

        <?php if ((int) $pagination['pages'] > 1): ?>
            <div class="pager">
                <?php for ($i = 1; $i <= (int) $pagination['pages']; $i++): ?>
                    <?php if ($i === (int) $pagination['page']): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= View::e($base . '?' . http_build_query(array_filter(['sort' => $sort, 'page' => $i]))) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
