<?php

use App\Core\View;

/** @var string $appUrl */
?>
<style>
    .track-wrap { max-width:640px; margin:0 auto; padding:2.5rem 1.5rem 3rem; }
    .track-wrap h1 { font-size:1.5rem; margin:0 0 .4rem; font-weight:800; }
    .track-form { display:flex; gap:.6rem; margin:1.25rem 0; flex-wrap:wrap; }
    .track-form input { flex:1; min-width:160px; padding:.65rem .75rem; border:1px solid var(--line); border-radius:6px; }

    .track-result { border:1px solid var(--line); border-radius:10px; padding:1.25rem; margin-top:1.5rem; display:none; }
    .track-result.show { display:block; }
    .track-status { display:inline-block; background:var(--bg-soft); padding:.3rem .7rem; border-radius:99px; font-size:.8rem; font-weight:700; }
    .track-items { margin:1rem 0; font-size:.88rem; }
    .track-items .row { display:flex; justify-content:space-between; padding:.3rem 0; border-bottom:1px dashed var(--line); }
    .track-timeline { list-style:none; padding:0; margin:1rem 0 0; font-size:.82rem; }
    .track-timeline li { padding:.3rem 0; border-bottom:1px dashed var(--line); }
</style>

<div class="wrap track-wrap">
    <h1>Track Your Order</h1>
    <p class="muted">Enter your order code and phone number to see the current status.</p>

    <form class="track-form" id="track-form">
        <input type="text" id="track-code" placeholder="Order code (ORD-000123)" required>
        <input type="text" id="track-phone" placeholder="Phone number" required>
        <button type="submit" class="btn">Track</button>
    </form>

    <div class="track-result" id="track-result"></div>
</div>

<script>
(function () {
    var statusLabels = { 1: 'Pending', 2: 'Confirmed', 3: 'Processing', 4: 'Shipped', 5: 'Delivered', 6: 'Cancelled', 7: 'Returned' };

    function render(order) {
        var box = document.getElementById('track-result');
        var itemsHtml = order.items.map(function (i) {
            return '<div class="row"><span>' + i.qty + '&times; ' + i.product_name
                + (i.variant_name ? ' — ' + i.variant_name : '') + '</span><span>' + window.money(i.line_total) + '</span></div>';
        }).join('');

        var logHtml = order.status_log.map(function (l) {
            var d = new Date(l.created_at * 1000);
            return '<li>' + d.toLocaleString() + ' — ' + (l.note || statusLabels[l.to_status] || '') + '</li>';
        }).join('');

        box.innerHTML = ''
            + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">'
            + '<strong>' + order.code + '</strong>'
            + '<span class="track-status">' + order.status_label + '</span>'
            + '</div>'
            + '<div class="muted" style="font-size:.85rem">' + order.recipient_name + ' — ' + order.shipping_address + '</div>'
            + '<div class="track-items">' + itemsHtml + '</div>'
            + '<div class="row" style="font-weight:700"><span>Total</span><span>' + window.money(order.grand_total) + '</span></div>'
            + '<ul class="track-timeline">' + logHtml + '</ul>';

        box.classList.add('show');
    }

    async function track(code, phone) {
        var result = await window.storefrontApi('/orders/' + encodeURIComponent(code) + '?phone=' + encodeURIComponent(phone));
        if (result) { render(result.order); }
    }

    document.getElementById('track-form').addEventListener('submit', function (event) {
        event.preventDefault();
        track(document.getElementById('track-code').value.trim(), document.getElementById('track-phone').value.trim());
    });

    // চেকআউটের পর রিডাইরেক্টে ?code=&phone= থাকলে সাথে সাথেই দেখাও
    var params = new URLSearchParams(location.search);
    if (params.get('code') && params.get('phone')) {
        document.getElementById('track-code').value = params.get('code');
        document.getElementById('track-phone').value = params.get('phone');
        track(params.get('code'), params.get('phone'));
    }
})();
</script>
