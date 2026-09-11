<?php

use App\Core\View;

/** @var array<string,mixed> $product — publicProductDetail() শেপ */
/** @var string $appUrl */

$p = $product;
$hasVariant = !empty($p['has_variant']);

// অ্যাট্রিবিউট গ্রুপ (Size/Color চিপ) — সব ভ্যারিয়েন্টের values থেকে ইউনিক করে
$attrGroups = [];
foreach ($p['variants'] as $v) {
    foreach ($v['values'] as $val) {
        $aid = (int) $val['attribute_id'];
        if (!isset($attrGroups[$aid])) {
            $attrGroups[$aid] = ['name' => $val['attribute_name'], 'type' => $val['attribute_type'], 'values' => []];
        }
        $vid = (int) $val['attribute_value_id'];
        $attrGroups[$aid]['values'][$vid] = ['value' => $val['value'], 'color_hex' => $val['color_hex']];
    }
}

$generalImages = array_values(array_filter($p['images'], static fn (array $i) => (int) $i['attribute_value_id'] === 0));
$mainImages    = $generalImages !== [] ? $generalImages : $p['images'];
?>
<style>
    .pdp { display:grid; grid-template-columns:1fr 1fr; gap:2.5rem; padding:2rem 0 3rem; }
    @media (max-width:800px) { .pdp { grid-template-columns:1fr; } }

    .gallery-main {
        position:relative; aspect-ratio:1/1; background:var(--bg-soft); border-radius:10px;
        overflow:hidden; margin-bottom:.6rem; cursor:zoom-in;
    }
    .gallery-main img { width:100%; height:100%; object-fit:cover; }
    .gallery-main .noimg { width:100%; height:100%; display:grid; place-items:center; color:var(--muted); }
    .zoom-lens {
        position:absolute; width:180px; height:180px; border-radius:50%; pointer-events:none;
        border:3px solid #fff; box-shadow:0 4px 18px rgba(0,0,0,.35), inset 0 0 0 1px rgba(0,0,0,.08);
        background-repeat:no-repeat; background-color:var(--bg-soft); display:none; z-index:5;
    }
    @media (max-width:800px) { .zoom-lens { display:none !important; } }
    .gallery-thumbs { display:flex; gap:.5rem; flex-wrap:wrap; }
    .gallery-thumbs img {
        width:60px; height:60px; object-fit:cover; border-radius:6px; cursor:pointer;
        border:2px solid transparent; opacity:.75;
    }
    .gallery-thumbs img.active { border-color:var(--brand); opacity:1; }

    .pdp-info h1 { font-size:1.5rem; margin:0 0 .5rem; font-weight:800; }
    .pdp-price { margin:.5rem 0 1.25rem; }
    .pdp-price .sale { font-size:1.5rem; font-weight:800; color:var(--accent); }
    .pdp-price .regular { font-size:1rem; color:var(--muted); text-decoration:line-through; margin-left:.5rem; }
    .pdp-price .now { font-size:1.5rem; font-weight:800; }
    .pdp-price .label { font-size:.75rem; color:var(--muted); }
    .pdp-price .off-pill { display:inline-block; background:var(--accent-soft); color:var(--accent); font-size:.72rem;
        font-weight:700; padding:.15rem .5rem; border-radius:4px; margin-left:.5rem; }

    .attr-group { margin-bottom:1.1rem; }
    .attr-group .attr-label { font-size:.82rem; font-weight:700; margin-bottom:.45rem; }
    .chip-row { display:flex; gap:.5rem; flex-wrap:wrap; }
    .chip {
        min-width:2.4rem; height:2.4rem; padding:0 .8rem; border:1px solid var(--line); border-radius:6px;
        display:grid; place-items:center; font-size:.82rem; cursor:pointer; background:#fff;
    }
    .chip:hover { border-color:var(--brand); }
    .chip.selected { border-color:var(--brand); background:var(--brand); color:#fff; font-weight:700; }
    .chip.swatch { width:2.4rem; height:2.4rem; padding:0; border-radius:50%; border:2px solid var(--line); }
    .chip.swatch.selected { border-color:var(--brand); box-shadow:0 0 0 2px var(--brand); }
    .chip.disabled { opacity:.35; cursor:not-allowed; text-decoration:line-through; }

    .qty-row { display:flex; align-items:center; gap:1rem; margin:1.25rem 0; }
    .stepper { display:flex; align-items:center; border:1px solid var(--line); border-radius:6px; }
    .stepper button { width:2.4rem; height:2.4rem; border:0; background:none; font-size:1.1rem; cursor:pointer; }
    .stepper input { width:3rem; text-align:center; border:0; -moz-appearance:textfield; }
    .stock-note { font-size:.82rem; }
    .stock-note.in { color:#16a34a; }
    .stock-note.out { color:var(--accent); }

    .pdp-actions { display:flex; gap:.75rem; margin-bottom:1.5rem; }
    .pdp-actions .btn { flex:1; padding:.85rem; }

    .pdp-desc { border-top:1px solid var(--line); padding-top:1.25rem; font-size:.9rem; line-height:1.7; }
    .pdp-meta { margin-top:1rem; font-size:.82rem; color:var(--muted); }
</style>

<div class="wrap">
    <div class="pdp">
        <div>
            <div class="gallery-main" id="gallery-main">
                <?php if ($mainImages !== []): ?>
                    <img id="gallery-main-img" src="<?= View::e($appUrl . $mainImages[0]['path']) ?>" alt="<?= View::e($p['name']) ?>">
                    <div class="zoom-lens" id="zoom-lens"></div>
                <?php else: ?>
                    <div class="noimg">No image</div>
                <?php endif; ?>
            </div>
            <div class="gallery-thumbs" id="gallery-thumbs">
                <?php foreach ($p['images'] as $i => $img): ?>
                    <img src="<?= View::e($appUrl . $img['path']) ?>" alt=""
                         class="<?= $i === 0 ? 'active' : '' ?>"
                         data-color="<?= (int) $img['attribute_value_id'] ?>"
                         onclick="selectImage(this)">
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pdp-info">
            <h1><?= View::e($p['name']) ?></h1>
            <?php if ($p['short_description'] !== ''): ?>
                <p class="muted"><?= View::e($p['short_description']) ?></p>
            <?php endif; ?>

            <div class="pdp-price" id="pdp-price">
                <?php if ($p['on_offer']): ?>
                    <div class="label">Sale price</div>
                    <span class="sale">৳<?= number_format((float) $p['price'], 0) ?></span>
                    <span class="regular">৳<?= number_format((float) $p['regular_price'], 0) ?></span>
                    <span class="off-pill"><?= (float) $p['discount_percent'] ?>% OFF</span>
                <?php else: ?>
                    <span class="now">৳<?= number_format((float) $p['price'], 0) ?></span>
                <?php endif; ?>
            </div>

            <form id="pdp-form">
                <?php foreach ($attrGroups as $aid => $group): ?>
                    <div class="attr-group">
                        <div class="attr-label"><?= View::e($group['name']) ?></div>
                        <div class="chip-row" data-attr-group="<?= $aid ?>">
                            <?php foreach ($group['values'] as $vid => $val): ?>
                                <?php if ($group['type'] === 'color'): ?>
                                    <div class="chip swatch" title="<?= View::e($val['value']) ?>"
                                         style="background:<?= View::e($val['color_hex'] ?: '#ccc') ?>"
                                         data-attr="<?= $aid ?>" data-value="<?= $vid ?>" onclick="selectAttr(this)"></div>
                                <?php else: ?>
                                    <div class="chip" data-attr="<?= $aid ?>" data-value="<?= $vid ?>" onclick="selectAttr(this)">
                                        <?= View::e($val['value']) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="qty-row">
                    <div class="stepper">
                        <button type="button" onclick="stepQty(-1)">&minus;</button>
                        <input type="number" id="qty" value="1" min="1" max="10" readonly>
                        <button type="button" onclick="stepQty(1)">&plus;</button>
                    </div>
                    <span class="stock-note" id="stock-note"></span>
                </div>

                <div class="pdp-actions">
                    <button type="button" class="btn outline" id="btn-add-cart">Add to Cart</button>
                    <button type="button" class="btn accent" id="btn-buy-now">Buy It Now</button>
                </div>
            </form>

            <?php if ($p['description'] !== ''): ?>
                <div class="pdp-desc"><?= nl2br(View::e($p['description'])) ?></div>
            <?php endif; ?>

            <div class="pdp-meta">
                SKU: <?= View::e($p['sku']) ?>
                <?php if (!empty($p['category_path'])): ?>
                    &nbsp;&middot;&nbsp;
                    <?php foreach ($p['category_path'] as $crumb): ?>
                        <a href="<?= View::e($appUrl . '/collections/' . $crumb['slug']) ?>"><?= View::e($crumb['name']) ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var product     = <?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var hasVariant   = <?= $hasVariant ? 'true' : 'false' ?>;
    var selected     = {}; // attribute_id -> value_id
    var currentVariant = null;

    window.selectAttr = function (el) {
        var attr = el.dataset.attr, value = el.dataset.value;
        selected[attr] = value;

        document.querySelectorAll('[data-attr-group="' + attr + '"] .chip').forEach(function (c) {
            c.classList.toggle('selected', c.dataset.value === value);
        });

        recompute();
    };

    window.selectImage = function (el) {
        document.getElementById('gallery-main-img').src = el.src;
        document.querySelectorAll('#gallery-thumbs img').forEach(function (t) { t.classList.remove('active'); });
        el.classList.add('active');
    };

    // হোভার ম্যাগনিফায়ার — গোল লেন্স কার্সর অনুসরণ করে জুম করা অংশ দেখায় (doc/10-storefront-order.md §১১ O-18)
    (function initZoomLens() {
        var stage = document.getElementById('gallery-main');
        var lens  = document.getElementById('zoom-lens');
        var ZOOM  = 2.2;

        if (!stage || !lens) { return; }

        stage.addEventListener('mousemove', function (e) {
            var img = document.getElementById('gallery-main-img');
            if (!img) { lens.style.display = 'none'; return; }

            var rect = stage.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            var size = lens.offsetWidth;

            lens.style.display = 'block';
            lens.style.left = (x - size / 2) + 'px';
            lens.style.top = (y - size / 2) + 'px';
            lens.style.backgroundImage = 'url("' + img.src + '")';
            lens.style.backgroundSize = (rect.width * ZOOM) + 'px ' + (rect.height * ZOOM) + 'px';
            lens.style.backgroundPosition = (-(x * ZOOM - size / 2)) + 'px ' + (-(y * ZOOM - size / 2)) + 'px';
        });

        stage.addEventListener('mouseleave', function () { lens.style.display = 'none'; });
    })();

    window.stepQty = function (delta) {
        var input = document.getElementById('qty');
        var next = Math.max(1, Math.min(10, parseInt(input.value, 10) + delta));
        input.value = next;
    };

    function recompute() {
        if (!hasVariant) { return; }

        var neededCount = Object.keys(groupBy(product.variants)).length;
        var chosen = Object.keys(selected);

        if (chosen.length < neededCount) { return; } // এখনো সব অপশন বাছা হয়নি

        var ids = chosen.map(function (k) { return parseInt(selected[k], 10); }).sort(function (a, b) { return a - b; });
        var signature = ids.join('-');

        currentVariant = product.variants.find(function (v) { return v.signature === signature; }) || null;

        if (!currentVariant) { return; }

        // দাম আপডেট
        var priceBox = document.getElementById('pdp-price');
        if (currentVariant.on_offer) {
            priceBox.innerHTML = '<div class="label">Sale price</div>'
                + '<span class="sale">' + window.money(currentVariant.price) + '</span>'
                + '<span class="regular">' + window.money(currentVariant.regular_price) + '</span>';
        } else {
            priceBox.innerHTML = '<span class="now">' + window.money(currentVariant.price) + '</span>';
        }

        // স্টক নোট (exact সংখ্যা না, শুধু আছে/নাই — publicProductDetail() ইচ্ছাকৃতভাবে
        // exact stock লিক করে না, doc/10-storefront-order.md §৬)
        var note = document.getElementById('stock-note');
        note.textContent = currentVariant.in_stock ? 'In stock' : 'Out of stock';
        note.className = 'stock-note ' + (currentVariant.in_stock ? 'in' : 'out');

        // রঙ-ভিত্তিক ছবি বদল, যদি এই ভ্যারিয়েন্টের রঙের ট্যাগড ছবি থাকে
        var colorValueId = null;
        (currentVariant.values || []).forEach(function (v) {
            if (v.attribute_type === 'color') { colorValueId = v.attribute_value_id; }
        });
        if (colorValueId) {
            var match = product.images.find(function (i) { return i.attribute_value_id === colorValueId; });
            if (match) {
                document.getElementById('gallery-main-img').src = '<?= View::e($appUrl) ?>' + match.path;
            }
        }
    }

    function groupBy(variants) {
        var groups = {};
        variants.forEach(function (v) {
            (v.values || []).forEach(function (val) { groups[val.attribute_id] = true; });
        });
        return groups;
    }

    function pickVariantId() {
        if (!hasVariant) { return 0; }
        if (!currentVariant) {
            window.storeToast('e', 'Please select all options.');
            return null;
        }
        if (!currentVariant.in_stock) {
            window.storeToast('e', 'This option is out of stock.');
            return null;
        }
        return currentVariant.id;
    }

    async function addToCart() {
        var variantId = pickVariantId();
        if (variantId === null) { return null; }

        var qty = parseInt(document.getElementById('qty').value, 10) || 1;

        return await window.storefrontApi('/cart/items', { product_id: product.id, variant_id: variantId, qty: qty }, 'POST');
    }

    document.getElementById('btn-add-cart').addEventListener('click', async function () {
        // সার্ভার রেসপন্সের 'm' মেসেজ storefrontApi() নিজেই toast করে দেয় (layout দেখুন) —
        // এখানে আবার storeToast() কল করলে "Added to cart." দুইবার দেখাতো (doc/10-storefront-order.md §১১ O-17)
        var result = await addToCart();
        if (result) { window.setCartBadge(result.count); }
    });

    document.getElementById('btn-buy-now').addEventListener('click', async function () {
        var result = await addToCart();
        if (result) { location.href = '<?= View::e($appUrl) ?>/checkout'; }
    });

    // সিম্পল প্রোডাক্টে (ভ্যারিয়েন্ট নাই) স্টক নোট সরাসরি
    if (!hasVariant) {
        var note = document.getElementById('stock-note');
        note.textContent = product.in_stock ? 'In stock' : 'Out of stock';
        note.className = 'stock-note ' + (product.in_stock ? 'in' : 'out');

        if (!product.in_stock) {
            document.getElementById('btn-add-cart').disabled = true;
            document.getElementById('btn-buy-now').disabled = true;
        }
    }
})();
</script>
