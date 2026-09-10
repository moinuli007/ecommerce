<?php

use App\Core\View;

/** @var array<string,mixed> $product — publicProductRow() শেপ */
/** @var string $appUrl */
/**
 * ⚠ `.p-card` CSS এখানে না — লেআউটে (resources/views/layouts/storefront.php)।
 * এই partial একটা লিস্টিং পেজে বহুবার (প্রতি প্রোডাক্টে একবার) রেন্ডার হয়,
 * তাই `<style>` এখানে রাখলে পেজে N বার ডুপ্লিকেট হয়ে যেত।
 */
?>
<a class="p-card" href="<?= View::e($appUrl . '/products/' . $product['slug']) ?>">
    <div class="thumb">
        <?php if (!empty($product['on_offer'])): ?>
            <span class="badge">Sale</span>
        <?php elseif (!empty($product['isNew'])): ?>
            <span class="badge new">New</span>
        <?php endif; ?>
        <?php if (empty($product['in_stock'])): ?>
            <span class="badge out" style="left:auto;right:.5rem">Sold Out</span>
        <?php endif; ?>
        <?php if (($product['image'] ?? '') !== ''): ?>
            <img src="<?= View::e($appUrl . $product['image']) ?>" alt="<?= View::e($product['name']) ?>" loading="lazy">
        <?php else: ?>
            <div class="noimg">No image</div>
        <?php endif; ?>
    </div>
    <h3 class="name"><?= View::e($product['name']) ?></h3>
    <div class="price-block">
        <?php if (!empty($product['on_offer'])): ?>
            <div class="muted" style="font-size:.72rem">Sale price</div>
            <span class="price-sale"><?= View::e('৳' . number_format((float) $product['price'], 0)) ?></span>
            <span class="price-regular"><?= View::e('৳' . number_format((float) $product['regular_price'], 0)) ?></span>
        <?php else: ?>
            <span class="price-now"><?= View::e('৳' . number_format((float) $product['price'], 0)) ?></span>
        <?php endif; ?>
    </div>
</a>
