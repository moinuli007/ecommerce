<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $orders — শুধু Pending, পুরোনোটা আগে */
/** @var string $appUrl */
?>
<style>
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
    button.sm { padding:.25rem .6rem; font-size:.78rem; }
    button.approve { background:#16a34a; border-color:#16a34a; color:#fff; }
    button.reject { background:transparent; border:1px solid var(--danger); color:var(--danger); }
</style>

<div class="card">
    <?php if ($orders === []): ?>
        <div class="empty">No pending orders — everything is caught up.</div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Placed</th>
                    <th>Recipient</th>
                    <th>Phone</th>
                    <th>Zone</th>
                    <th>Payment</th>
                    <th class="num">Total</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/orders/' . $order['id']) ?>"><?= View::e($order['code']) ?></a>
                        </td>
                        <td class="muted"><?= View::e(date('Y-m-d H:i', (int) $order['placed_at'])) ?></td>
                        <td><?= View::e($order['recipient_name']) ?></td>
                        <td><?= View::e($order['recipient_phone']) ?></td>
                        <td><?= View::e($order['delivery_zone_name']) ?></td>
                        <td><?= (int) $order['payment_method'] === 1 ? 'COD' : '<span class="pill">Advance</span>' ?></td>
                        <td class="num"><?= number_format((float) $order['grand_total'], 2) ?></td>
                        <td style="white-space:nowrap">
                            <button type="button" class="sm approve" data-approve="<?= (int) $order['id'] ?>">Approve</button>
                            <button type="button" class="sm reject" data-reject="<?= (int) $order['id'] ?>">Reject</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    document.querySelectorAll('[data-approve]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Approve this order? — address/items have been phone-confirmed.')) { return; }

            if (await api('/orders/' + this.dataset.approve + '/approve', {}, 'POST')) { location.reload(); }
        });
    });

    document.querySelectorAll('[data-reject]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var note = prompt('Reason for rejecting this order (required):');
            if (!note) { return; }

            if (await api('/orders/' + this.dataset.reject + '/reject', { note: note }, 'POST')) { location.reload(); }
        });
    });
})();
</script>
