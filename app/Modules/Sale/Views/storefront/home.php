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
        background:linear-gradient(135deg,#1c1c1e,#3a3a3d); color:#fff; padding:3.5rem 1.5rem;
        display:flex; align-items:center; justify-content:center; gap:3rem; overflow:hidden;
    }
    .hero-text { max-width:440px; }
    .hero h1 { font-size:2rem; margin:0 0 .6rem; font-weight:800; }
    .hero p { margin:0 0 1.5rem; opacity:.85; font-size:.95rem; }
    .hero-art { width:280px; flex-shrink:0; }
    @media (max-width:760px) {
        .hero { flex-direction:column; text-align:center; padding-top:3rem; }
        .hero-art { width:180px; order:-1; }
    }

    .perks { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; padding:1.75rem 0; border-bottom:1px solid var(--line); }
    .perk { display:flex; align-items:center; gap:.7rem; }
    .perk svg { flex-shrink:0; color:var(--accent); }
    .perk strong { display:block; font-size:.85rem; }
    .perk span { display:block; font-size:.78rem; color:var(--muted); }

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
</style>

<section class="hero">
    <div class="hero-text">
        <h1><?= View::e($appName ?? 'Welcome') ?></h1>
        <p>Quality fashion, delivered fast across Bangladesh — Cash on Delivery available.</p>
        <a class="btn accent" href="<?= View::e($appUrl) ?>/collections/all">Shop All Products</a>
    </div>
    <img class="hero-art" src="<?= View::e($appUrl) ?>/images/hero-art.svg" alt="" aria-hidden="true">
</section>

<div class="wrap">
    <div class="perks">
        <div class="perk">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <div><strong>Fast Delivery</strong><span>Across Bangladesh</span></div>
        </div>
        <div class="perk">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg>
            <div><strong>Cash on Delivery</strong><span>Pay when it arrives</span></div>
        </div>
        <div class="perk">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            <div><strong>Easy Returns</strong><span>Hassle-free exchange</span></div>
        </div>
        <div class="perk">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <div><strong>Secure Checkout</strong><span>Your info stays safe</span></div>
        </div>
    </div>
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
                        <img src="<?= View::e($appUrl . (($cat['image'] ?? '') !== '' ? $cat['image'] : '/images/placeholder.svg')) ?>"
                             alt="<?= View::e($cat['name']) ?>" loading="lazy">
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
