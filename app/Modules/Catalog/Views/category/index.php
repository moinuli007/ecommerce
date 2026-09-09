<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $tree */
/** @var array<int,array{id:int,name:string,depth:int,label:string}> $options */
/** @var string $appUrl */

/** গাছটা রিকার্সিভভাবে আঁকে */
$renderNodes = static function (array $nodes, callable $self) use ($appUrl): string {
    $html = '<ul class="cat-list">';

    foreach ($nodes as $node) {
        $thumb = $node['image'] !== ''
            ? '<img class="cat-thumb" src="' . View::e($appUrl . $node['image']) . '" alt="">'
            : '';

        $html .= '<li data-id="' . (int) $node['id'] . '">'
            . '<div class="cat-row">'
            . $thumb
            . '<span class="cat-name">' . View::e($node['name']) . '</span>'
            . '<code class="muted">' . View::e($node['slug']) . '</code>'
            . ((int) $node['isFeatured'] === 1 ? '<span class="pill">Featured</span>' : '')
            . ((int) $node['isActive'] === 1 ? '' : '<span class="pill off">Off</span>')
            . '<span class="cat-actions">'
            . '<button type="button" class="ghost sm" data-edit=\'' . htmlspecialchars(json_encode([
                'id'         => (int) $node['id'],
                'parent_id'  => (int) $node['parent_id'],
                'name'       => $node['name'],
                'slug'       => $node['slug'],
                'sort_order' => (int) $node['sort_order'],
                'isFeatured' => (int) $node['isFeatured'],
                'isActive'   => (int) $node['isActive'],
                'image'      => $node['image'],
            ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '\'>Edit</button>'
            . '<button type="button" class="ghost sm danger" data-del="' . (int) $node['id'] . '">Delete</button>'
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
    .cat-thumb { width:24px; height:24px; object-fit:cover; border-radius:4px; border:1px solid var(--line); }
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
        <h2 class="card-title">Category Tree</h2>
        <?php if ($tree === []): ?>
            <div class="empty">No categories yet. Add the first one from the form on the right.</div>
        <?php else: ?>
            <?= $renderNodes($tree, $renderNodes) ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="card-title" id="form-title">New Category</h2>

        <form id="cat-form">
            <input type="hidden" name="id" id="cat-id" value="">

            <div class="field">
                <label for="cat-name">Name</label>
                <input id="cat-name" name="name" required maxlength="150" placeholder="e.g. Casual Shirts">
            </div>

            <div class="field">
                <label for="cat-parent">Parent</label>
                <select id="cat-parent" name="parent_id">
                    <option value="0">— Top level —</option>
                    <?php foreach ($options as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= View::e($option['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="cat-slug">Slug <span class="muted">(leave empty to derive from the name)</span></label>
                <input id="cat-slug" name="slug" maxlength="180" placeholder="casual-shirts">
            </div>

            <div class="field">
                <label for="cat-sort">Sort Order</label>
                <input id="cat-sort" name="sort_order" type="number" value="0">
            </div>

            <div class="field" id="cat-image-section" style="display:none">
                <label>Image</label>
                <div style="display:flex; align-items:center; gap:.6rem; margin-bottom:.4rem">
                    <img id="cat-image-preview" src="" alt=""
                         style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid var(--line);display:none">
                    <span id="cat-image-empty" class="muted" style="font-size:.82rem">No image</span>
                    <span id="cat-image-uploading" class="muted" style="font-size:.82rem;display:none">Uploading…</span>
                </div>
                <input type="file" id="cat-image-file" accept="image/jpeg,image/png,image/webp">
                <p class="notice" style="margin:.3rem 0 0">Picking a file uploads it right away — no separate save step.</p>
                <div style="margin-top:.4rem;display:flex;gap:.4rem">
                    <button type="button" class="ghost sm danger" id="cat-image-remove" style="display:none">Remove image</button>
                </div>
            </div>

            <div class="field check">
                <input id="cat-featured" name="isFeatured" type="checkbox" value="1">
                <label for="cat-featured" style="margin:0">Show in the homepage collection cards</label>
            </div>

            <div class="field check">
                <input id="cat-active" name="isActive" type="checkbox" value="1" checked>
                <label for="cat-active" style="margin:0">Active</label>
            </div>

            <button type="submit">Save</button>
            <button type="button" class="ghost" id="cat-reset">Reset</button>
        </form>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('cat-form');

    function setImagePreview(path) {
        const img       = document.getElementById('cat-image-preview');
        const empty     = document.getElementById('cat-image-empty');
        const removeBtn = document.getElementById('cat-image-remove');

        if (path) {
            img.src = '<?= View::e($appUrl) ?>' + path;
            img.style.display = '';
            empty.style.display = 'none';
            removeBtn.style.display = '';
        } else {
            img.src = '';
            img.style.display = 'none';
            empty.style.display = '';
            removeBtn.style.display = 'none';
        }
    }

    function reset() {
        form.reset();
        document.getElementById('cat-id').value = '';
        document.getElementById('cat-active').checked = true;
        document.getElementById('cat-image-section').style.display = 'none';
        document.getElementById('cat-image-file').value = '';
        setImagePreview('');
        document.getElementById('form-title').textContent = 'New Category';
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
            document.getElementById('form-title').textContent = 'Edit Category — ' + c.name;

            document.getElementById('cat-image-section').style.display = '';
            document.getElementById('cat-image-file').value = '';
            setImagePreview(c.image || '');

            form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    // ---- ক্যাটাগরি ছবি — ফাইল বাছলেই সাথে সাথে প্রিভিউ + আপলোড (আলাদা বাটন লাগবে না)
    document.getElementById('cat-image-file').addEventListener('change', async function () {
        const file = this.files[0];
        if (!file) { return; }

        const id = document.getElementById('cat-id').value;

        if (!id) {
            window.toast('e', 'Please save the category first.');
            this.value = '';
            return;
        }

        const img      = document.getElementById('cat-image-preview');
        const empty    = document.getElementById('cat-image-empty');
        const uploading = document.getElementById('cat-image-uploading');

        img.src = URL.createObjectURL(file);
        img.style.display = '';
        empty.style.display = 'none';
        uploading.style.display = '';

        const formData = new FormData();
        formData.append('image', file);

        const result = await window.apiUpload('/categories/' + id + '/image', formData, 'POST');

        uploading.style.display = 'none';

        if (result) {
            setImagePreview(result.image);
            this.value = '';
            location.reload();
        }
    });

    document.getElementById('cat-image-remove').addEventListener('click', async function () {
        const id = document.getElementById('cat-id').value;

        if (!id || !confirm('Remove the image?')) { return; }

        if (await api('/categories/' + id + '/image', null, 'DELETE')) {
            setImagePreview('');
            location.reload();
        }
    });

    document.querySelectorAll('[data-del]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this category?')) { return; }

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
