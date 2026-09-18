<?php

use App\Core\View;

/**
 * স্টোরফ্রন্ট লেআউট — প্রোমো বার → হেডার (লোগো, নেভ, কার্ট) → কনটেন্ট → ফুটার।
 * doc/10-storefront-order.md §০ এর KINGSMONT/marlandmanor রেফারেন্স অনুযায়ী।
 *
 * কোনো এক্সটার্নাল CSS/JS ফ্রেমওয়ার্ক নাই — বাকি প্রজেক্টের মতোই raw।
 */

/** @var string $content */
/** @var string $title */
/** @var string $appName */
/** @var string $appUrl */
/** @var int $cartCount */
/** @var array<int,array<string,mixed>> $navCategories */
/** @var array<int,array{0:string,1:string}> $messages */

$messages       = $messages ?? [];
$cartCount      = $cartCount ?? 0;
$navCategories  = $navCategories ?? [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($title ?? '') ?><?= ($title ?? '') !== '' ? ' — ' : '' ?><?= View::e($appName ?? '') ?></title>
    <link rel="icon" type="image/jpeg" href="<?= View::e($appUrl) ?>/images/riylon-favicon.jpg">
    <style>
        :root {
            --bg:#fff; --bg-soft:#f7f7f8; --fg:#17181a; --muted:#6b7280; --line:#e7e7ea;
            --brand:#1c1c1e; --accent:#d0341f; --accent-soft:#fdecea;
            --shadow:0 2px 10px rgba(16,24,40,.06);
        }
        * { box-sizing:border-box; }
        html, body { margin:0; padding:0; }
        body {
            background:var(--bg); color:var(--fg);
            font:15px/1.6 system-ui, "Noto Sans Bengali", "Segoe UI", sans-serif;
            -webkit-font-smoothing:antialiased;
        }
        a { color:inherit; text-decoration:none; }
        img { max-width:100%; display:block; }
        button, input, select, textarea { font:inherit; }

        .promo {
            background:var(--brand); color:#fff; text-align:center; font-size:.78rem;
            padding:.5rem .75rem; letter-spacing:.02em;
        }
        .promo span + span::before { content:'  •  '; opacity:.6; }

        header.site {
            display:flex; align-items:center; gap:1.5rem; padding:1rem 1.5rem;
            border-bottom:1px solid var(--line); position:sticky; top:0; background:var(--bg); z-index:20;
        }
        .site-logo { display:flex; align-items:center; gap:.55rem; font-size:1.25rem; font-weight:800; letter-spacing:.02em; white-space:nowrap; }
        .site-logo img { height:34px; width:34px; border-radius:50%; object-fit:cover; }
        nav.site-nav { display:flex; gap:1.25rem; flex:1; overflow-x:auto; }
        nav.site-nav a { font-size:.88rem; font-weight:600; color:var(--fg); white-space:nowrap; padding:.3rem 0; }
        nav.site-nav a:hover { color:var(--accent); }
        .site-actions { display:flex; align-items:center; gap:1rem; margin-left:auto; }
        .cart-link { position:relative; display:flex; align-items:center; gap:.4rem; font-weight:600; font-size:.88rem; }
        .cart-badge {
            position:absolute; top:-8px; right:-10px; background:var(--accent); color:#fff;
            font-size:.65rem; font-weight:700; min-width:17px; height:17px; border-radius:99px;
            display:grid; place-items:center; padding:0 3px;
        }
        .track-link, .account-link { font-size:.82rem; color:var(--muted); }

        main { min-height:60vh; }

        footer.site {
            margin-top:3rem; border-top:1px solid var(--line); background:var(--bg-soft);
            padding:2rem 1.5rem 1.5rem; font-size:.85rem; color:var(--muted);
        }
        footer.site .foot-grid { display:flex; gap:2.5rem; flex-wrap:wrap; max-width:1200px; margin:0 auto; }
        footer.site h4 { color:var(--fg); font-size:.82rem; margin:0 0 .6rem; text-transform:uppercase; letter-spacing:.05em; }
        footer.site ul { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.4rem; }
        footer.site .foot-bottom { text-align:center; margin-top:1.75rem; padding-top:1.25rem; border-top:1px solid var(--line); }

        .msg { max-width:1200px; margin:.75rem auto 0; padding:.65rem .9rem; border-radius:8px; font-size:.88rem; }
        .msg-e { background:#fdecec; color:#8c1c1c; }
        .msg-s { background:#e8f6ec; color:#14532d; }
        .msg-w { background:#fdf3e0; color:#7c4a03; }
        .msg-i { background:#e8f0fd; color:#1e3a8a; }

        /* বটম-রাইট টোস্ট — সাধারণ ইনলাইন .msg এর চেয়ে বেশি ভিজিবল হওয়া দরকার
           (বড় প্যাডিং, বোল্ড টেক্সট, আইকন, কালার-কোডেড বর্ডার, স্লাইড-ইন অ্যানিমেশন) */
        .toast-item {
            display:flex; align-items:flex-start; gap:.6rem; margin:0; padding:.9rem 1.1rem;
            border-radius:10px; font-size:.92rem; font-weight:600; line-height:1.4;
            border-left:4px solid transparent;
            box-shadow:0 10px 28px rgba(16,24,40,.2), 0 2px 8px rgba(16,24,40,.1);
            animation:toast-in .25s ease-out;
        }
        .toast-item.msg-s { border-left-color:#14532d; }
        .toast-item.msg-e { border-left-color:#8c1c1c; }
        .toast-item.msg-w { border-left-color:#7c4a03; }
        .toast-item.msg-i { border-left-color:#1e3a8a; }
        .toast-item .toast-icon { flex-shrink:0; margin-top:.05rem; }
        .toast-item.toast-out { animation:toast-out .2s ease-in forwards; }
        @keyframes toast-in { from { opacity:0; transform:translateY(10px) scale(.97); } to { opacity:1; transform:translateY(0) scale(1); } }
        @keyframes toast-out { to { opacity:0; transform:translateY(6px) scale(.97); } }

        .wrap { max-width:1200px; margin:0 auto; padding:0 1.5rem; }
        .muted { color:var(--muted); }
        .btn {
            display:inline-flex; align-items:center; justify-content:center; gap:.4rem;
            font-weight:700; font-size:.88rem; padding:.7rem 1.3rem; border-radius:6px;
            border:1px solid var(--brand); background:var(--brand); color:#fff; cursor:pointer;
        }
        .btn:hover { opacity:.88; }
        .btn.outline { background:transparent; color:var(--brand); }
        .btn.accent { background:var(--accent); border-color:var(--accent); }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .btn.block { width:100%; }

        @media (max-width:760px) {
            header.site { flex-wrap:wrap; gap:.75rem; }
            nav.site-nav { order:3; flex-basis:100%; gap:1rem; }
        }

        /* প্রোডাক্ট কার্ড — Sale::storefront/_product-card.php ব্যবহার করে, একাধিকবার
           রেন্ডার হয় বলে CSS এখানে একবারই (লেআউটে), partial-এ না */
        .p-card { display:block; }
        .p-card .thumb {
            position:relative; aspect-ratio:3/4; background:var(--bg-soft); border-radius:8px; overflow:hidden;
            margin-bottom:.6rem;
        }
        .p-card .thumb img { width:100%; height:100%; object-fit:cover; }
        .p-card .badge {
            position:absolute; top:.5rem; left:.5rem; background:var(--accent); color:#fff;
            font-size:.68rem; font-weight:800; padding:.2rem .5rem; border-radius:4px; text-transform:uppercase;
        }
        .p-card .badge.new { background:#1c1c1e; }
        .p-card .badge.out { background:#6b7280; }
        .p-card .name { font-size:.88rem; font-weight:600; margin:0 0 .3rem; line-height:1.35;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .p-card .price-block { font-size:.85rem; }
        .p-card .price-sale { color:var(--accent); font-weight:800; }
        .p-card .price-regular { color:var(--muted); text-decoration:line-through; font-size:.8rem; margin-left:.35rem; }
        .p-card .price-now { font-weight:800; }
    </style>
</head>
<body>

<!-- <div class="promo">
    <span>Cash on Delivery All Over Bangladesh</span>
    <span>Free Delivery on Orders Over &#2547;3,000 (Inside Dhaka)</span>
</div> -->

<header class="site">
    <a class="site-logo" href="<?= View::e($appUrl) ?>/">
        <img src="<?= View::e($appUrl) ?>/images/riylon-logo.jpg" alt="">
        <?= View::e($appName ?? 'Store') ?>
    </a>
    <nav class="site-nav">
        <a href="<?= View::e($appUrl) ?>/collections/all">All Products</a>
        <?php foreach (array_slice($navCategories, 0, 6) as $cat): ?>
            <a href="<?= View::e($appUrl . '/collections/' . $cat['slug']) ?>"><?= View::e($cat['name']) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="site-actions">
        <a class="track-link" href="<?= View::e($appUrl) ?>/orders/track">Track Order</a>
        <?php if ($authCustomer !== []): ?>
            <a class="account-link" href="<?= View::e($appUrl) ?>/profile">Hi, <?= View::e($authCustomer['name']) ?></a>
            <a class="account-link" href="<?= View::e($appUrl) ?>/logout">Logout</a>
        <?php else: ?>
            <a class="account-link" href="<?= View::e($appUrl) ?>/login">Login</a>
        <?php endif; ?>
        <a class="cart-link" href="<?= View::e($appUrl) ?>/cart">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            Cart
            <span class="cart-badge" id="cart-badge" <?= $cartCount > 0 ? '' : 'hidden' ?>><?= (int) $cartCount ?></span>
        </a>
    </div>
</header>

<?php foreach ($messages as $message): ?>
    <div class="msg msg-<?= View::e($message[0]) ?>"><?= View::e($message[1]) ?></div>
<?php endforeach; ?>

<main><?= $content ?></main>

<footer class="site">
    <div class="foot-grid">
        <div>
            <h4><?= View::e($appName ?? 'Store') ?></h4>
            <ul>
                <li class="muted">Quality products, delivered fast across Bangladesh.</li>
            </ul>
        </div>
        <div>
            <h4>Shop</h4>
            <ul>
                <li><a href="<?= View::e($appUrl) ?>/collections/all">All Products</a></li>
                <li><a href="<?= View::e($appUrl) ?>/cart">Cart</a></li>
                <li><a href="<?= View::e($appUrl) ?>/orders/track">Track Order</a></li>
            </ul>
        </div>
        <div>
            <h4>Payment &amp; Delivery</h4>
            <ul>
                <li class="muted">Cash on Delivery</li>
                <li class="muted">bKash / Nagad (advance)</li>
                <li class="muted">Delivery inside &amp; outside Dhaka</li>
            </ul>
        </div>
    </div>
    <div class="foot-bottom">&copy; <?= date('Y') ?> <?= View::e($appName ?? '') ?></div>
</footer>

<script>
window.API_ROOT       = '<?= View::e($appUrl) ?>/api/v1';
window.STOREFRONT_API = window.API_ROOT + '/storefront';
window.AUTH_API       = window.API_ROOT + '/auth';

// storefrontApi()/accountApi() দুটোই এর পাতলা wrapper — শুধু base URL আলাদা
// (doc/11-customer-account.md সিদ্ধান্ত C-09)
window.callApi = async function (base, path, body, method) {
    method = method || (body ? 'POST' : 'GET');

    let data;

    try {
        const response = await fetch(base + path, {
            method:      method,
            headers:     { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body:        body ? JSON.stringify(body) : undefined
        });

        data = await response.json();
    } catch (error) {
        window.storeToast('e', 'Could not reach the server: ' + error.message);
        return null;
    }

    (data.m || []).forEach(function (m) { window.storeToast(m[0], m[1]); });

    return data.status === 1 ? data : null;
};

window.storefrontApi = function (path, body, method) {
    return window.callApi(window.STOREFRONT_API, path, body, method);
};

window.accountApi = function (path, body, method) {
    return window.callApi(window.AUTH_API, path, body, method);
};

window.storeToast = function (type, text) {
    let box = document.getElementById('store-toast-box');

    if (!box) {
        box = document.createElement('div');
        box.id = 'store-toast-box';
        box.style.cssText = 'position:fixed;right:1rem;bottom:1rem;z-index:60;display:flex;'
                          + 'flex-direction:column;gap:.6rem;max-width:min(380px,90vw)';
        document.body.appendChild(box);
    }

    const icons = {
        s: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
        e: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/></svg>',
        w: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/></svg>',
        i: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>'
    };

    const item = document.createElement('div');
    item.className = 'msg msg-' + type + ' toast-item';

    const icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.innerHTML = icons[type] || icons.i;

    const label = document.createElement('span');
    label.textContent = text;

    item.appendChild(icon);
    item.appendChild(label);
    box.appendChild(item);

    setTimeout(function () {
        item.classList.add('toast-out');
        setTimeout(function () { item.remove(); }, 200);
    }, 4500);
};

window.setCartBadge = function (count) {
    const badge = document.getElementById('cart-badge');
    if (!badge) { return; }
    badge.textContent = count;
    badge.hidden = count <= 0;
};

window.money = function (n) {
    return '৳' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
};
</script>
</body>
</html>
