<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $orders */
/** @var array<string,string> $filters — status, code */
/** @var string $appUrl */

$statusLabels = [1 => 'Pending', 2 => 'Confirmed', 3 => 'Processing', 4 => 'Shipped', 5 => 'Delivered', 6 => 'Cancelled', 7 => 'Returned'];
$statusPill = static function (int $status): string {
    $cls = match ($status) {
        6       => 'off',
        default => '',
    };

    return $cls;
};
?>
<style>
    .pill { font-size:.68rem; padding:.1rem .45rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
</style>

<div class="card">
    <form class="filters" method="get" action="<?= View::e($appUrl . '/admin/orders') ?>">
        <div>
            <label for="code">Order code</label>
            <input id="code" name="code" value="<?= View::e($filters['code']) ?>" placeholder="ORD-000123">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $filters['status'] === (string) $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Search</button>
        <a class="btn ghost" href="<?= View::e($appUrl . '/admin/orders/pending') ?>">Pending queue</a>
    </form>
</div>

<div class="card">
    <?php if ($orders === []): ?>
        <div class="empty">No orders found.</div>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Placed</th>
                    <th>Recipient</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th class="num">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a href="<?= View::e($appUrl . '/admin/orders/' . $order['id']) ?>"><?= View::e($order['code']) ?></a>
                        </td>
                        <td class="muted"><?= View::e(date('Y-m-d H:i', (int) $order['placed_at'])) ?></td>
                        <td><?= View::e($order['recipient_name']) ?> <span class="muted"><?= View::e($order['recipient_phone']) ?></span></td>
                        <td><?= (int) $order['payment_method'] === 1 ? 'COD' : 'Advance' ?></td>
                        <td>
                            <span class="pill <?= $statusPill((int) $order['status']) ?>">
                                <?= $statusLabels[(int) $order['status']] ?? $order['status'] ?>
                            </span>
                        </td>
                        <td class="num"><?= number_format((float) $order['grand_total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
