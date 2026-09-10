<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $collections — টপ-লেভেল ক্যাটাগরি গাছ */
/** @var array<int,array<string,mixed>> $featured — হোমপেজ কালেকশন কার্ড */
/** @var array<int,array<string,mixed>> $newArrivals */
/** @var array<int,array<string,mixed>> $bestsellers */
/** @var string $appUrl */
?>
<style>
    .hero {
        background:linear-gradient(135deg,#1c1c1e,#3a3a3d); color:#fff; padding:4rem 1.5rem;
        text-align:center;
    }
    .hero h1 { font-size:2rem; margin:0 0 .6rem; font-weight:800; }
    .hero p { margin:0 0 1.5rem; opacity:.85; font-size:.95rem; }

    .section { padding:2.5rem 0; }
    .section-head { display:flex; align-items:baseline; justify-content:space-between; margin-bottom:1.25rem; }
    .section-head h2 { font-size:1.3rem; margin:0; font-weight:800; }
    .section-head a { font-size:.85rem; font-weight:600; color:var(--accent); }

    .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:1.25rem 1rem; }

    .collection-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1rem; }
    .collection-card {
        position:relative; aspect-ratio:16/10; border-radius:10px; overflow:hidden;
        background:var(--bg-soft); display:flex; align-items:flex-end;
    }
    .collection-card img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .collection-card .label {
        position:relative; z-index:1; width:100%; padding:.85rem 1rem; font-weight:700; color:#fff;
        background:linear-gradient(0deg,rgba(0,0,0,.55),transparent);
    }
    .collection-card:not(:has(img)) .label { color:var(--fg); background:none; }
</style>

<section class="hero">
    <h1><?= View::e($appName ?? 'Welcome') ?></h1>
    <p>Quality fashion, delivered fast across Bangladesh — Cash on Delivery available.</p>
    <a class="btn accent" href="<?= View::e($appUrl) ?>/collections/all">Shop All Products</a>
</section>

<div class="wrap">
    <?php if ($newArrivals !== []): ?>
        <section class="section">
            <div class="section-head">
                <h2>New Arrivals</h2>
                <a href="<?= View::e($appUrl) ?>/collections/all">View all &rarr;</a>
            </div>
            <div class="grid">
                <?php foreach ($newArrivals as $p): ?>
                    <?= View::render('Sale::storefront/_product-card', ['product' => $p]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($featured !== []): ?>
        <section class="section">
            <div class="section-head"><h2>Shop by Collection</h2></div>
            <div class="collection-grid">
                <?php foreach ($featured as $cat): ?>
                    <a class="collection-card" href="<?= View::e($appUrl . '/collections/' . $cat['slug']) ?>">
                        <?php if (($cat['image'] ?? '') !== ''): ?>
                            <img src="<?= View::e($appUrl . $cat['image']) ?>" alt="<?= View::e($cat['name']) ?>" loading="lazy">
                        <?php endif; ?>
                        <span class="label"><?= View::e($cat['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($bestsellers !== []): ?>
        <section class="section">
            <div class="section-head">
                <h2>Bestsellers</h2>
                <a href="<?= View::e($appUrl) ?>/collections/all">View all &rarr;</a>
            </div>
            <div class="grid">
                <?php foreach ($bestsellers as $p): ?>
                    <?= View::render('Sale::storefront/_product-card', ['product' => $p]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($newArrivals === [] && $bestsellers === [] && $collections === []): ?>
        <div class="empty" style="padding:4rem 1rem; text-align:center; color:var(--muted)">
            No products yet — check back soon.
        </div>
    <?php endif; ?>
</div>
