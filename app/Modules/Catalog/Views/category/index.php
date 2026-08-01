<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $tree */
/** @var array<int,array{id:int,name:string,depth:int,label:string}> $options */
/** @var string $appUrl */

/** গাছটা রিকার্সিভভাবে আঁকে */
$renderNodes = static function (array $nodes, callable $self): string {
    $html = '<ul class="cat-list">';

    foreach ($nodes as $node) {
        $html .= '<li data-id="' . (int) $node['id'] . '">'
            . '<div class="cat-row">'
            . '<span class="cat-name">' . View::e($node['name']) . '</span>'
            . '<code class="muted">' . View::e($node['slug']) . '</code>'
            . ((int) $node['isFeatured'] === 1 ? '<span class="pill">Featured</span>' : '')
            . ((int) $node['isActive'] === 1 ? '' : '<span class="pill off">বন্ধ</span>')
            . '<span class="cat-actions">'
            . '<button type="button" class="ghost sm" data-edit=\'' . htmlspecialchars(json_encode([
                'id'         => (int) $node['id'],
                'parent_id'  => (int) $node['parent_id'],
                'name'       => $node['name'],
                'slug'       => $node['slug'],
                'sort_order' => (int) $node['sort_order'],
                'isFeatured' => (int) $node['isFeatured'],
                'isActive'   => (int) $node['isActive'],
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '\'>এডিট</button>'
            . '<button type="button" class="ghost sm danger" data-del="' . (int) $node['id'] . '">ডিলিট</button>'
            . '</span>'
            . '</div>';

        if (!empty($node['children'])) {
            $html .= $self($node['children'], $self);
        }

        $html .= '</li>';
    }

    return $html . '</ul>';
};
?>
<style>
    .cat-wrap { display:grid; gap:1rem; grid-template-columns:1fr 340px; align-items:start; }
    @media (max-width:900px) { .cat-wrap { grid-template-columns:1fr; } }
    .cat-list { list-style:none; margin:0; padding-left:0; }
    .cat-list .cat-list { padding-left:1.4rem; border-left:1px dashed var(--line); margin-left:.5rem; }
    .cat-row { display:flex; align-items:center; gap:.6rem; padding:.5rem .1rem; border-bottom:1px solid var(--line); }
    .cat-name { font-weight:600; }
    .cat-actions { margin-left:auto; display:flex; gap:.35rem; }
    button.sm { padding:.25rem .55rem; font-size:.78rem; }
    button.sm.danger { color:var(--danger); border-color:var(--line); }
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
    .field { margin-bottom:.75rem; }
    .field input, .field select, .field textarea { width:100%; }
    .check { display:flex; align-items:center; gap:.45rem; font-size:.88rem; }
    .check input { width:auto; }
</style>

<div class="cat-wrap">
    <div class="card">
        <h2 class="card-title">ক্যাটাগরি গাছ</h2>
        <?php if ($tree === []): ?>
            <div class="empty">এখনো কোনো ক্যাটাগরি নাই। ডান পাশের ফর্ম থেকে প্রথমটা যোগ করুন।</div>
        <?php else: ?>
            <?= $renderNodes($tree, $renderNodes) ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="card-title" id="form-title">নতুন ক্যাটাগরি</h2>

        <form id="cat-form">
            <input type="hidden" name="id" id="cat-id" value="">

            <div class="field">
                <label for="cat-name">নাম</label>
                <input id="cat-name" name="name" required maxlength="150" placeholder="যেমন Casual Shirts">
            </div>

            <div class="field">
                <label for="cat-parent">প্যারেন্ট</label>
                <select id="cat-parent" name="parent_id">
                    <option value="0">— টপ লেভেল —</option>
                    <?php foreach ($options as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= View::e($option['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="cat-slug">slug <span class="muted">(খালি রাখলে নাম থেকে হবে)</span></label>
                <input id="cat-slug" name="slug" maxlength="180" placeholder="casual-shirts">
            </div>

            <div class="field">
                <label for="cat-sort">ক্রম</label>
                <input id="cat-sort" name="sort_order" type="number" value="0">
            </div>

            <div class="field check">
                <input id="cat-featured" name="isFeatured" type="checkbox" value="1">
                <label for="cat-featured" style="margin:0">হোমপেজের collection কার্ডে দেখাবে</label>
            </div>

            <div class="field check">
                <input id="cat-active" name="isActive" type="checkbox" value="1" checked>
                <label for="cat-active" style="margin:0">চালু</label>
            </div>

            <button type="submit">সেভ করুন</button>
            <button type="button" class="ghost" id="cat-reset">রিসেট</button>
        </form>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('cat-form');

    function reset() {
        form.reset();
        document.getElementById('cat-id').value = '';
        document.getElementById('cat-active').checked = true;
        document.getElementById('form-title').textContent = 'নতুন ক্যাটাগরি';
    }

    document.getElementById('cat-reset').addEventListener('click', reset);

    // এডিট — সারির ডেটা ফর্মে বসায়
    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const c = JSON.parse(this.dataset.edit);

            document.getElementById('cat-id').value       = c.id;
            document.getElementById('cat-name').value      = c.name;
            document.getElementById('cat-slug').value      = c.slug;
            document.getElementById('cat-sort').value       = c.sort_order;
            document.getElementById('cat-parent').value     = c.parent_id;
            document.getElementById('cat-featured').checked = c.isFeatured === 1;
            document.getElementById('cat-active').checked   = c.isActive === 1;
            document.getElementById('form-title').textContent = 'ক্যাটাগরি এডিট — ' + c.name;

            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    document.querySelectorAll('[data-del]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('এই ক্যাটাগরিটা ডিলিট করবেন?')) { return; }

            if (await api('/categories/' + this.dataset.del, null, 'DELETE')) {
                location.reload();
            }
        });
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const id   = document.getElementById('cat-id').value;
        const body = {
            name:       document.getElementById('cat-name').value,
            parent_id:  parseInt(document.getElementById('cat-parent').value, 10) || 0,
            slug:       document.getElementById('cat-slug').value,
            sort_order: parseInt(document.getElementById('cat-sort').value, 10) || 0,
            isFeatured: document.getElementById('cat-featured').checked ? 1 : 0,
            isActive:   document.getElementById('cat-active').checked ? 1 : 0
        };

        const result = id
            ? await api('/categories/' + id, body, 'PUT')
            : await api('/categories', body, 'POST');

        if (result) { location.reload(); }
    });
})();
</script>
