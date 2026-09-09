<?php

use App\Core\View;

/** @var array<string,mixed> $product empty for a new product */
/** @var array<int,array{id:int,label:string}> $categories */
/** @var array<int,array{id:int,name:string,code:string,group:string}> $units */
/** @var array<int,array<string,mixed>> $attributes */
/** @var string $appUrl */

$isEdit    = ($product['id'] ?? 0) > 0;
$selected  = $product['selected_values'] ?? [];
$variants  = $product['variants'] ?? [];
$images    = $product['images'] ?? [];
$v         = static fn (string $key, mixed $default = '') => $product[$key] ?? $default;

$colorAttribute = null;
foreach ($attributes as $attribute) {
    if ($attribute['code'] === 'color') {
        $colorAttribute = $attribute;
        break;
    }
}

// ছবিগুলো রঙ অনুযায়ী গ্রুপ করা — General আলাদা সেকশনে
$imagesByColor = [];
foreach ($images as $image) {
    $label = $image['color_name'] !== '' ? $image['color_name'] : 'General';
    $imagesByColor[$label][] = $image;
}
?>
<style>
    .form-grid { display:grid; gap:1rem; grid-template-columns:2fr 1fr; align-items:start; }
    @media (max-width:1000px) { .form-grid { grid-template-columns:1fr; } }
    .row2 { display:grid; gap:.75rem; grid-template-columns:1fr 1fr; }
    .row3 { display:grid; gap:.75rem; grid-template-columns:1fr 1fr 1fr; }
    @media (max-width:600px) { .row2, .row3 { grid-template-columns:1fr; } }
    .field { margin-bottom:.85rem; }
    .field input, .field select, .field textarea { width:100%; }
    .field textarea { min-height:90px; resize:vertical; }
    .check { display:flex; align-items:center; gap:.45rem; font-size:.9rem; margin-bottom:.6rem; }
    .check input { width:auto; }
    .check label { margin:0; color:var(--fg); }

    .attr-block { margin-bottom:1rem; }
    .attr-block h3 { margin:0 0 .5rem; font-size:.88rem; }
    .opts { display:flex; flex-wrap:wrap; gap:.4rem; }
    .opt {
        display:inline-flex; align-items:center; gap:.4rem; padding:.35rem .65rem;
        border:1px solid var(--line); border-radius:99px; cursor:pointer; font-size:.86rem; user-select:none;
    }
    .opt input { display:none; }
    .opt .swatch { width:14px; height:14px; border-radius:50%; border:1px solid var(--line); }
    .opt.on { background:var(--brand); color:#fff; border-color:transparent; }
    .opt.on .swatch { border-color:rgba(255,255,255,.6); }

    .vgrid input { width:100%; min-width:84px; }
    .notice { font-size:.85rem; color:var(--muted); margin:.5rem 0 0; }

    .img-upload-row { display:flex; align-items:flex-end; gap:.75rem; flex-wrap:wrap; }
    .img-upload-row .field { margin-bottom:0; flex:1 1 160px; }
    .img-preview-box {
        width:56px; height:56px; border-radius:8px; border:1px solid var(--line);
        overflow:hidden; flex:0 0 auto; display:none; background:var(--card);
    }
    .img-preview-box img { width:100%; height:100%; object-fit:cover; display:block; }
    .img-color-group { margin-top:1rem; }
    .img-color-group h3 { margin:0 0 .5rem; font-size:.85rem; color:var(--muted); }
    .img-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:.75rem; }
    .img-item { border:1px solid var(--line); border-radius:8px; overflow:hidden; font-size:.78rem; }
    .img-item img { width:100%; height:120px; object-fit:cover; display:block; background:var(--card); }
    .img-meta { display:flex; justify-content:space-between; align-items:center; gap:.3rem; padding:.35rem .5rem; }
    .img-actions { display:flex; gap:.3rem; padding:0 .5rem .5rem; }
    .img-actions button { flex:1; padding:.25rem .3rem; font-size:.72rem; }
</style>

<form id="product-form">
    <div class="form-grid">
        <!-- ------------------------------------------------------ বাম কলাম -->
        <div>
            <div class="card">
                <h2 class="card-title">Basic Info</h2>

                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" name="name" required maxlength="191"
                           value="<?= View::e($v('name')) ?>" placeholder="e.g. Single Pocket Casual Shirt">
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">— Select —</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"
                                    <?= (int) $v('category_id', 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= View::e($category['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="unit_id">Unit</label>
                        <select id="unit_id" name="unit_id" required>
                            <option value="">— Select —</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) $unit['id'] ?>"
                                    <?= (int) $v('unit_id', 0) === (int) $unit['id'] ? 'selected' : '' ?>>
                                    <?= View::e($unit['group'] . ' › ' . $unit['name'] . ' (' . $unit['code'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label for="short_description">Short Description</label>
                    <input id="short_description" name="short_description" maxlength="500"
                           value="<?= View::e($v('short_description')) ?>">
                </div>

                <div class="field">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= View::e($v('description')) ?></textarea>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">Price</h2>

                <div class="row3">
                    <div class="field">
                        <label>Purchase Price <span class="muted">(from Purchase entries)</span></label>
                        <p class="notice" style="margin-top:0">
                            <strong><?= number_format((float) $v('purchase_price', 0), 2) ?></strong>
                            <?php if (!$isEdit): ?>
                                — set once you record a Purchase entry after saving.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="field">
                        <label for="sale_price">Sale Price</label>
                        <input id="sale_price" name="sale_price" type="number" step="0.01" min="0"
                               value="<?= (float) $v('sale_price', 0) ?>">
                    </div>
                    <div class="field">
                        <label for="offer_price">Offer Price <span class="muted">(0 = no offer)</span></label>
                        <input id="offer_price" name="offer_price" type="number" step="0.01" min="0"
                               value="<?= (float) $v('offer_price', 0) ?>">
                    </div>
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="offer_start">Offer Start</label>
                        <input id="offer_start" name="offer_start" type="date" value="<?= View::e($v('offer_start_date')) ?>">
                    </div>
                    <div class="field">
                        <label for="offer_end">Offer End</label>
                        <input id="offer_end" name="offer_end" type="date" value="<?= View::e($v('offer_end_date')) ?>">
                    </div>
                </div>

                <p class="notice">Leave the dates empty for an offer with no time limit.</p>
            </div>

            <?php if ($isEdit): ?>
                <!-- ------------------------------------------- ভ্যারিয়েন্ট -->
                <div class="card">
                    <h2 class="card-title">Build Variants</h2>
                    <p class="notice" style="margin-top:0">
                        Pick the sizes/colors this product comes in — every combination is generated automatically.
                        Removing a variant here doesn't delete it, it just deactivates it.
                    </p>

                    <?php foreach ($attributes as $attribute): ?>
                        <?php if ($attribute['values'] === []) { continue; } ?>
                        <div class="attr-block">
                            <h3><?= View::e($attribute['name']) ?></h3>
                            <div class="opts">
                                <?php foreach ($attribute['values'] as $value): ?>
                                    <?php $on = in_array((int) $value['id'], $selected[(int) $attribute['id']] ?? [], true); ?>
                                    <label class="opt <?= $on ? 'on' : '' ?>">
                                        <input type="checkbox" class="vopt"
                                               data-attribute="<?= (int) $attribute['id'] ?>"
                                               value="<?= (int) $value['id'] ?>" <?= $on ? 'checked' : '' ?>>
                                        <?php if ($value['color_hex'] !== ''): ?>
                                            <span class="swatch" style="background:<?= View::e($value['color_hex']) ?>"></span>
                                        <?php endif; ?>
                                        <?= View::e($value['value']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="button" id="gen-variants">Build / Update Variants</button>
                    <span class="muted" id="combo-count" style="margin-left:.6rem"></span>
                </div>

                <?php if ($variants !== []): ?>
                    <div class="card">
                        <h2 class="card-title">
                            Variant Price &amp; Stock
                            <span class="muted" style="margin-left:auto;font-size:.82rem"><?= count($variants) ?></span>
                        </h2>
                        <p class="notice" style="margin-top:0">
                            Sale price is set once for the whole product (see the Price card above) — every color/size sells at the same price. Only purchase price, offer, and stock vary by variant.
                        </p>

                        <div class="scroll">
                            <table class="vgrid">
                                <thead>
                                <tr>
                                    <th>Variant</th>
                                    <th>SKU</th>
                                    <th class="num">Purchase</th>
                                    <th class="num">Offer</th>
                                    <th class="num">Stock</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($variants as $variant): ?>
                                    <tr data-variant="<?= (int) $variant['id'] ?>"
                                        style="<?= $variant['isActive'] ? '' : 'opacity:.5' ?>">
                                        <td><?= View::e($variant['name']) ?></td>
                                        <td><code class="muted"><?= View::e($variant['sku']) ?></code></td>
                                        <td class="num" title="Set via Purchase entries">
                                            <?= number_format((float) $variant['purchase_price'], 2) ?>
                                        </td>
                                        <td><input name="offer_price" type="number" step="0.01" min="0"
                                                   value="<?= $variant['offer_price'] ?>"></td>
                                        <td class="num" title="Set via Purchase / Stock Adjustment"><?=
                                            rtrim(rtrim(number_format((float) $variant['stock'], 4), '0'), '.') ?: '0'
                                        ?></td>
                                        <td><?= $variant['isActive'] ? 'Active' : '<span class="muted">Inactive</span>' ?></td>
                                        <td style="white-space:nowrap">
                                            <button type="button" class="ghost" data-save-variant>Save</button>
                                            <?php if (!$variant['isActive']): ?>
                                                <button type="button" class="ghost" data-activate-variant title="This combination isn't part of the current size/color selection above, but you can still turn it back on">Activate</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ------------------------------------------------- ছবি -->
                <div class="card">
                    <h2 class="card-title">
                        Images
                        <span class="muted" style="margin-left:auto;font-size:.82rem"><?= count($images) ?></span>
                    </h2>
                    <p class="notice" style="margin-top:0">
                        Pick a color first if this photo is for one color, then choose the file — it uploads right away, no separate save step. Leave the color as General to show the photo on all variants.
                    </p>

                    <div class="img-upload-row">
                        <div class="field">
                            <label for="img-color">Color (optional)</label>
                            <select id="img-color">
                                <option value="0">— General —</option>
                                <?php if ($colorAttribute !== null): ?>
                                    <?php foreach ($colorAttribute['values'] as $value): ?>
                                        <option value="<?= (int) $value['id'] ?>"><?= View::e($value['value']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="img-file">Image (JPEG/PNG/WebP, max 2MB)</label>
                            <input id="img-file" type="file" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <div class="img-preview-box" id="img-preview-box">
                            <img id="img-preview" src="" alt="">
                        </div>
                        <span class="muted" id="img-uploading" style="display:none">Uploading…</span>
                    </div>

                    <?php if ($images === []): ?>
                        <p class="notice">No images yet.</p>
                    <?php else: ?>
                        <?php foreach ($imagesByColor as $colorLabel => $group): ?>
                            <div class="img-color-group">
                                <h3><?= View::e($colorLabel) ?></h3>
                                <div class="img-grid">
                                    <?php foreach ($group as $image): ?>
                                        <div class="img-item" data-image="<?= (int) $image['id'] ?>">
                                            <img src="<?= View::e($appUrl . $image['path']) ?>" alt="<?= View::e($image['alt']) ?>">
                                            <div class="img-meta">
                                                <span><?= $image['is_primary'] ? 'Primary' : '&nbsp;' ?></span>
                                            </div>
                                            <div class="img-actions">
                                                <?php if (!$image['is_primary']): ?>
                                                    <button type="button" class="ghost" data-make-primary>Make primary</button>
                                                <?php endif; ?>
                                                <button type="button" class="ghost danger" data-delete-image>Delete</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ------------------------------------------------------ ডান কলাম -->
        <div>
            <div class="card">
                <h2 class="card-title">Publish</h2>

                <div class="check">
                    <input id="isActive" type="checkbox" <?= (int) $v('isActive', 1) === 1 ? 'checked' : '' ?>>
                    <label for="isActive">Active</label>
                </div>
                <div class="check">
                    <input id="isNew" type="checkbox" <?= (int) $v('isNew', 0) === 1 ? 'checked' : '' ?>>
                    <label for="isNew">Show in New Arrivals</label>
                </div>
                <div class="check">
                    <input id="isFeatured" type="checkbox" <?= (int) $v('isFeatured', 0) === 1 ? 'checked' : '' ?>>
                    <label for="isFeatured">Show in Bestsellers</label>
                </div>

                <div class="field" style="margin-top:.85rem">
                    <label for="sort_order">Sort Order</label>
                    <input id="sort_order" name="sort_order" type="number" value="<?= (int) $v('sort_order', 0) ?>">
                </div>

                <button type="submit" style="width:100%"><?= $isEdit ? 'Update' : 'Save' ?></button>

                <?php if ($isEdit): ?>
                    <p class="notice">SKU: <code><?= View::e($v('sku')) ?></code></p>
                <?php else: ?>
                    <p class="notice">You'll be able to add variants (size/color) after saving.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">Stock &amp; Shipping</h2>

                <div class="field">
                    <label>Stock <span class="muted">(from Purchase / Stock Adjustment)</span></label>
                    <p class="notice" style="margin-top:0">
                        <strong><?= rtrim(rtrim(number_format((float) $v('stock', 0), 4), '0'), '.') ?: '0' ?></strong>
                        <?php if (!$isEdit): ?>
                            — stock will increase once you record a Purchase entry after saving.
                        <?php elseif ($v('has_variant', false)): ?>
                            — sum of the variants.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="field">
                    <label for="stock_alert">Stock Alert</label>
                    <input id="stock_alert" name="stock_alert" type="number" step="0.01" min="0"
                           value="<?= (float) $v('stock_alert', 0) ?>">
                </div>

                <div class="field">
                    <label for="weight">Weight (grams)</label>
                    <input id="weight" name="weight" type="number" step="0.001" min="0"
                           value="<?= (float) $v('weight', 0) ?>">
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">SEO</h2>
                <div class="field">
                    <label for="slug">Slug</label>
                    <input id="slug" name="slug" maxlength="220" value="<?= View::e($v('slug')) ?>"
                           placeholder="Leave empty to derive from the name">
                </div>
                <div class="field">
                    <label for="meta_title">Meta Title</label>
                    <input id="meta_title" name="meta_title" maxlength="191" value="<?= View::e($v('meta_title')) ?>">
                </div>
                <div class="field">
                    <label for="meta_description">Meta Description</label>
                    <textarea id="meta_description" name="meta_description" maxlength="300"
                              style="min-height:60px"><?= View::e($v('meta_description')) ?></textarea>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const PRODUCT_ID = <?= (int) $v('id', 0) ?>;

    // ---- প্রোডাক্ট সেভ
    document.getElementById('product-form').addEventListener('submit', async function (event) {
        event.preventDefault();

        const val = id => document.getElementById(id) ? document.getElementById(id).value : '';
        const chk = id => document.getElementById(id) && document.getElementById(id).checked ? 1 : 0;

        const body = {
            name:              val('name'),
            category_id:       parseInt(val('category_id'), 10) || 0,
            unit_id:           parseInt(val('unit_id'), 10) || 0,
            short_description: val('short_description'),
            description:       val('description'),
            sale_price:        parseFloat(val('sale_price')) || 0,
            offer_price:       parseFloat(val('offer_price')) || 0,
            offer_start:       val('offer_start'),
            offer_end:         val('offer_end'),
            stock_alert:       parseFloat(val('stock_alert')) || 0,
            weight:            parseFloat(val('weight')) || 0,
            sort_order:        parseInt(val('sort_order'), 10) || 0,
            slug:              val('slug'),
            meta_title:        val('meta_title'),
            meta_description:  val('meta_description'),
            isActive:          chk('isActive'),
            isNew:             chk('isNew'),
            isFeatured:        chk('isFeatured')
        };

        const result = PRODUCT_ID
            ? await api('/products/' + PRODUCT_ID, body, 'PUT')
            : await api('/products', body, 'POST');

        if (result && !PRODUCT_ID && result.product) {
            // নতুন প্রোডাক্ট সেভ হলে এডিট পেজে — সেখানেই ভ্যারিয়েন্ট যোগ করা যায়
            location.href = '<?= View::e($appUrl) ?>/admin/products/' + result.product.id + '/edit';
        }
    });

    // ---- ভ্যারিয়েন্ট অপশন টগল (চিপের চেহারা)
    function collectSelection() {
        const selection = {};

        document.querySelectorAll('.vopt').forEach(function (input) {
            input.closest('.opt').classList.toggle('on', input.checked);

            if (input.checked) {
                (selection[input.dataset.attribute] ||= []).push(parseInt(input.value, 10));
            }
        });

        return selection;
    }

    function updateCount() {
        const selection = collectSelection();
        const counts    = Object.values(selection).map(a => a.length);
        const total     = counts.length ? counts.reduce((a, b) => a * b, 1) : 0;
        const label     = document.getElementById('combo-count');

        if (label) {
            label.textContent = total > 0 ? (counts.join(' × ') + ' = ' + total + ' variants') : '';
        }
    }

    document.querySelectorAll('.vopt').forEach(function (input) {
        input.addEventListener('change', updateCount);
    });

    updateCount();

    // ---- ভ্যারিয়েন্ট জেনারেট
    const genBtn = document.getElementById('gen-variants');

    if (genBtn) {
        genBtn.addEventListener('click', async function () {
            const selection = collectSelection();

            if (Object.keys(selection).length === 0
                && !confirm('No option is selected — every variant will be deactivated. Continue?')) {
                return;
            }

            if (await api('/products/' + PRODUCT_ID + '/variants', { selection: selection }, 'POST')) {
                setTimeout(() => location.reload(), 600);
            }
        });
    }

    // ---- ভ্যারিয়েন্টের দাম/স্টক সেভ
    document.querySelectorAll('[data-save-variant]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const row  = this.closest('tr');
            const body = {};

            row.querySelectorAll('input[name]').forEach(function (input) {
                body[input.name] = parseFloat(input.value) || 0;
            });

            await api('/variants/' + row.dataset.variant, body, 'PUT');
        });
    });

    // ---- নিষ্ক্রিয় ভ্যারিয়েন্ট আবার সক্রিয় করা
    document.querySelectorAll('[data-activate-variant]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const row = this.closest('tr');

            if (await api('/variants/' + row.dataset.variant, { isActive: 1 }, 'PUT')) {
                location.reload();
            }
        });
    });

    // ---- ছবি — ফাইল বাছলেই সাথে সাথে প্রিভিউ + আপলোড (আলাদা বাটন লাগবে না)
    const imgFileInput  = document.getElementById('img-file');
    const imgPreviewBox = document.getElementById('img-preview-box');
    const imgPreview    = document.getElementById('img-preview');
    const imgUploading  = document.getElementById('img-uploading');

    if (imgFileInput) {
        imgFileInput.addEventListener('change', async function () {
            const file = imgFileInput.files[0];
            if (!file) { imgPreviewBox.style.display = 'none'; return; }

            imgPreview.src = URL.createObjectURL(file);
            imgPreviewBox.style.display = '';
            imgUploading.style.display = '';

            const formData = new FormData();
            formData.append('image', file);
            formData.append('attribute_value_id', document.getElementById('img-color').value);

            const result = await window.apiUpload('/products/' + PRODUCT_ID + '/images', formData, 'POST');

            imgUploading.style.display = 'none';

            if (result) {
                location.reload();
            }
        });
    }

    document.querySelectorAll('[data-make-primary]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id = this.closest('[data-image]').dataset.image;

            if (await api('/images/' + id, { is_primary: 1 }, 'PUT')) {
                location.reload();
            }
        });
    });

    document.querySelectorAll('[data-delete-image]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this image?')) { return; }

            const id = this.closest('[data-image]').dataset.image;

            if (await api('/images/' + id, null, 'DELETE')) {
                location.reload();
            }
        });
    });
})();
</script>
