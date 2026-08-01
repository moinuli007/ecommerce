<?php

use App\Core\View;

/** @var array<string,mixed> $product খালি হলে নতুন প্রোডাক্ট */
/** @var array<int,array{id:int,label:string}> $categories */
/** @var array<int,array{id:int,name:string,code:string,group:string}> $units */
/** @var array<int,array<string,mixed>> $attributes */
/** @var string $appUrl */

$isEdit    = ($product['id'] ?? 0) > 0;
$selected  = $product['selected_values'] ?? [];
$variants  = $product['variants'] ?? [];
$v         = static fn (string $key, mixed $default = '') => $product[$key] ?? $default;
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
</style>

<form id="product-form">
    <div class="form-grid">
        <!-- ------------------------------------------------------ বাম কলাম -->
        <div>
            <div class="card">
                <h2 class="card-title">মূল তথ্য</h2>

                <div class="field">
                    <label for="name">নাম</label>
                    <input id="name" name="name" required maxlength="191"
                           value="<?= View::e($v('name')) ?>" placeholder="যেমন Single Pocket Casual Shirt">
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="category_id">ক্যাটাগরি</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">— বাছুন —</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"
                                    <?= (int) $v('category_id', 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                    <?= View::e($category['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="unit_id">ইউনিট</label>
                        <select id="unit_id" name="unit_id" required>
                            <option value="">— বাছুন —</option>
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
                    <label for="short_description">সংক্ষিপ্ত বর্ণনা</label>
                    <input id="short_description" name="short_description" maxlength="500"
                           value="<?= View::e($v('short_description')) ?>">
                </div>

                <div class="field">
                    <label for="description">বিস্তারিত</label>
                    <textarea id="description" name="description"><?= View::e($v('description')) ?></textarea>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">দাম</h2>

                <div class="row3">
                    <div class="field">
                        <label for="purchase_price">ক্রয় মূল্য</label>
                        <input id="purchase_price" name="purchase_price" type="number" step="0.01" min="0"
                               value="<?= (float) $v('purchase_price', 0) ?>">
                    </div>
                    <div class="field">
                        <label for="sale_price">বিক্রয় মূল্য</label>
                        <input id="sale_price" name="sale_price" type="number" step="0.01" min="0"
                               value="<?= (float) $v('sale_price', 0) ?>">
                    </div>
                    <div class="field">
                        <label for="offer_price">অফার মূল্য <span class="muted">(0 = অফার নাই)</span></label>
                        <input id="offer_price" name="offer_price" type="number" step="0.01" min="0"
                               value="<?= (float) $v('offer_price', 0) ?>">
                    </div>
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="offer_start">অফার শুরু</label>
                        <input id="offer_start" name="offer_start" type="date" value="<?= View::e($v('offer_start_date')) ?>">
                    </div>
                    <div class="field">
                        <label for="offer_end">অফার শেষ</label>
                        <input id="offer_end" name="offer_end" type="date" value="<?= View::e($v('offer_end_date')) ?>">
                    </div>
                </div>

                <p class="notice">তারিখ খালি রাখলে অফারের কোনো সীমা থাকবে না।</p>
            </div>

            <?php if ($isEdit): ?>
                <!-- ------------------------------------------- ভ্যারিয়েন্ট -->
                <div class="card">
                    <h2 class="card-title">ভ্যারিয়েন্ট তৈরি</h2>
                    <p class="notice" style="margin-top:0">
                        যে সাইজ/রঙগুলো এই প্রোডাক্টে আছে সেগুলো বেছে দিন — সব কম্বিনেশন নিজে থেকেই তৈরি হবে।
                        কোনো ভ্যারিয়েন্ট বাদ দিলে সেটা মুছে যায় না, শুধু নিষ্ক্রিয় হয়।
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

                    <button type="button" id="gen-variants">ভ্যারিয়েন্ট তৈরি / হালনাগাদ করুন</button>
                    <span class="muted" id="combo-count" style="margin-left:.6rem"></span>
                </div>

                <?php if ($variants !== []): ?>
                    <div class="card">
                        <h2 class="card-title">
                            ভ্যারিয়েন্টের দাম ও স্টক
                            <span class="muted" style="margin-left:auto;font-size:.82rem"><?= count($variants) ?> টি</span>
                        </h2>

                        <div class="scroll">
                            <table class="vgrid">
                                <thead>
                                <tr>
                                    <th>ভ্যারিয়েন্ট</th>
                                    <th>SKU</th>
                                    <th class="num">ক্রয়</th>
                                    <th class="num">বিক্রয়</th>
                                    <th class="num">অফার</th>
                                    <th class="num">স্টক</th>
                                    <th>অবস্থা</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($variants as $variant): ?>
                                    <tr data-variant="<?= (int) $variant['id'] ?>"
                                        style="<?= $variant['isActive'] ? '' : 'opacity:.5' ?>">
                                        <td><?= View::e($variant['name']) ?></td>
                                        <td><code class="muted"><?= View::e($variant['sku']) ?></code></td>
                                        <td><input name="purchase_price" type="number" step="0.01" min="0"
                                                   value="<?= $variant['purchase_price'] ?>"></td>
                                        <td><input name="sale_price" type="number" step="0.01" min="0"
                                                   value="<?= $variant['sale_price'] ?>"></td>
                                        <td><input name="offer_price" type="number" step="0.01" min="0"
                                                   value="<?= $variant['offer_price'] ?>"></td>
                                        <td><input name="stock" type="number" step="0.01" min="0"
                                                   value="<?= $variant['stock'] ?>"></td>
                                        <td><?= $variant['isActive'] ? 'চালু' : '<span class="muted">নিষ্ক্রিয়</span>' ?></td>
                                        <td><button type="button" class="ghost" data-save-variant>সেভ</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ------------------------------------------------------ ডান কলাম -->
        <div>
            <div class="card">
                <h2 class="card-title">প্রকাশ</h2>

                <div class="check">
                    <input id="isActive" type="checkbox" <?= (int) $v('isActive', 1) === 1 ? 'checked' : '' ?>>
                    <label for="isActive">চালু</label>
                </div>
                <div class="check">
                    <input id="isNew" type="checkbox" <?= (int) $v('isNew', 0) === 1 ? 'checked' : '' ?>>
                    <label for="isNew">New Arrivals এ দেখাবে</label>
                </div>
                <div class="check">
                    <input id="isFeatured" type="checkbox" <?= (int) $v('isFeatured', 0) === 1 ? 'checked' : '' ?>>
                    <label for="isFeatured">Bestsellers এ দেখাবে</label>
                </div>

                <div class="field" style="margin-top:.85rem">
                    <label for="sort_order">ক্রম</label>
                    <input id="sort_order" name="sort_order" type="number" value="<?= (int) $v('sort_order', 0) ?>">
                </div>

                <button type="submit" style="width:100%"><?= $isEdit ? 'আপডেট করুন' : 'সেভ করুন' ?></button>

                <?php if ($isEdit): ?>
                    <p class="notice">SKU: <code><?= View::e($v('sku')) ?></code></p>
                <?php else: ?>
                    <p class="notice">সেভ করার পর ভ্যারিয়েন্ট (সাইজ/রঙ) যোগ করতে পারবেন।</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2 class="card-title">স্টক ও শিপিং</h2>

                <?php if (!$isEdit || !$v('has_variant', false)): ?>
                    <div class="field">
                        <label for="stock">স্টক</label>
                        <input id="stock" name="stock" type="number" step="0.01" min="0"
                               value="<?= (float) $v('stock', 0) ?>">
                    </div>
                <?php else: ?>
                    <p class="notice" style="margin-top:0">
                        ভ্যারিয়েন্ট আছে — মোট স্টক ভ্যারিয়েন্টের যোগফল
                        (<strong><?= rtrim(rtrim(number_format((float) $v('stock', 0), 2), '0'), '.') ?></strong>)।
                    </p>
                <?php endif; ?>

                <div class="field">
                    <label for="stock_alert">স্টক অ্যালার্ট</label>
                    <input id="stock_alert" name="stock_alert" type="number" step="0.01" min="0"
                           value="<?= (float) $v('stock_alert', 0) ?>">
                </div>

                <div class="field">
                    <label for="weight">ওজন (গ্রাম)</label>
                    <input id="weight" name="weight" type="number" step="0.001" min="0"
                           value="<?= (float) $v('weight', 0) ?>">
                </div>
            </div>

            <div class="card">
                <h2 class="card-title">SEO</h2>
                <div class="field">
                    <label for="slug">slug</label>
                    <input id="slug" name="slug" maxlength="220" value="<?= View::e($v('slug')) ?>"
                           placeholder="খালি রাখলে নাম থেকে হবে">
                </div>
                <div class="field">
                    <label for="meta_title">মেটা টাইটেল</label>
                    <input id="meta_title" name="meta_title" maxlength="191" value="<?= View::e($v('meta_title')) ?>">
                </div>
                <div class="field">
                    <label for="meta_description">মেটা বর্ণনা</label>
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
            purchase_price:    parseFloat(val('purchase_price')) || 0,
            sale_price:        parseFloat(val('sale_price')) || 0,
            offer_price:       parseFloat(val('offer_price')) || 0,
            offer_start:       val('offer_start'),
            offer_end:         val('offer_end'),
            stock:             parseFloat(val('stock')) || 0,
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
            label.textContent = total > 0 ? (counts.join(' × ') + ' = ' + total + ' টি ভ্যারিয়েন্ট') : '';
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
                && !confirm('কোনো অপশন বাছাই করা হয়নি — সব ভ্যারিয়েন্ট নিষ্ক্রিয় হয়ে যাবে। চালিয়ে যাবেন?')) {
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
})();
</script>
