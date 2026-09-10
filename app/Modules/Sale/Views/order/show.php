<?php

use App\Core\View;

/** @var array<string,mixed> $order */
/** @var string $appUrl */

$o = $order;
?>
<style>
    .pill { font-size:.7rem; padding:.15rem .55rem; border-radius:99px; background:var(--brand-soft); color:var(--brand); }
    .pill.off { background:rgba(220,38,38,.12); color:var(--danger); }
    .pill.warn { background:rgba(217,119,6,.12); color:#b45309; }
    button.sm { padding:.25rem .6rem; font-size:.78rem; }
    button.approve { background:#16a34a; border-color:#16a34a; color:#fff; }
    button.reject { background:transparent; border:1px solid var(--danger); color:var(--danger); }
    .timeline { list-style:none; padding:0; margin:0; font-size:.85rem; }
    .timeline li { padding:.35rem 0; border-bottom:1px dashed var(--border); }
    .timeline li:last-child { border-bottom:none; }
</style>

<?php if ($o === []): ?>
    <div class="card"><div class="empty">Order not found.</div></div>
<?php else: ?>
    <div class="card" style="max-width:900px">
        <h2 class="card-title">
            <?= View::e($o['code']) ?>
            <span class="pill <?= (int) $o['status'] === 6 ? 'off' : '' ?>" style="margin-left:.5rem">
                <?= View::e($o['status_label']) ?>
            </span>
            <span class="muted" style="margin-left:auto;font-size:.82rem">
                <?= View::e(date('Y-m-d H:i', (int) $o['placed_at'])) ?>
            </span>
        </h2>

        <div style="display:flex; gap:2rem; flex-wrap:wrap; margin-bottom:1rem; font-size:.9rem">
            <div><span class="muted">Recipient:</span> <?= View::e($o['recipient_name']) ?></div>
            <div><span class="muted">Phone:</span> <?= View::e($o['recipient_phone']) ?></div>
            <div><span class="muted">Zone:</span> <?= View::e($o['delivery_zone_name']) ?></div>
            <div><span class="muted">Payment:</span> <?= View::e($o['payment_method_label']) ?></div>
        </div>
        <div style="margin-bottom:1rem; font-size:.9rem">
            <span class="muted">Address:</span> <?= View::e($o['shipping_address']) ?>
        </div>
        <?php if ($o['note'] !== ''): ?>
            <div style="margin-bottom:1rem; font-size:.9rem"><span class="muted">Note:</span> <?= View::e($o['note']) ?></div>
        <?php endif; ?>

        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Line Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($o['items'] as $item): ?>
                    <tr>
                        <td><?= View::e($item['product_name']) ?><?= $item['variant_name'] !== '' ? ' — ' . View::e($item['variant_name']) : '' ?></td>
                        <td><code class="muted"><?= View::e($item['sku']) ?></code></td>
                        <td class="num"><?= rtrim(rtrim(number_format((float) $item['qty'], 4), '0'), '.') ?></td>
                        <td class="num"><?= number_format((float) $item['unit_price'], 2) ?></td>
                        <td class="num"><?= number_format((float) $item['line_total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="4" class="num">Sub Total</td><td class="num"><?= number_format((float) $o['sub_total'], 2) ?></td></tr>
                    <tr><td colspan="4" class="num">Discount</td><td class="num"><?= number_format((float) $o['discount_total'], 2) ?></td></tr>
                    <tr><td colspan="4" class="num">Delivery Fee</td><td class="num"><?= number_format((float) $o['delivery_fee'], 2) ?></td></tr>
                    <tr><td colspan="4" class="num"><strong>Grand Total</strong></td><td class="num"><strong><?= number_format((float) $o['grand_total'], 2) ?></strong></td></tr>
                    <?php if ((float) $o['advance_paid'] > 0): ?>
                        <tr><td colspan="4" class="num">Advance Paid</td><td class="num"><?= number_format((float) $o['advance_paid'], 2) ?></td></tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>

        <div id="order-actions" style="margin-top:1.25rem; display:flex; gap:.5rem; flex-wrap:wrap">
            <?php if ((int) $o['status'] === 1): ?>
                <button type="button" class="approve" data-approve="<?= (int) $o['id'] ?>">Approve</button>
                <button type="button" class="reject" data-reject="<?= (int) $o['id'] ?>">Reject</button>
            <?php else: ?>
                <?php foreach ($o['next_states'] as $next): ?>
                    <button type="button" class="sm" data-status="<?= (int) $o['id'] ?>" data-to="<?= (int) $next['value'] ?>">
                        Mark as <?= View::e($next['label']) ?>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a class="btn ghost" style="margin-top:1rem;display:inline-block" href="<?= View::e($appUrl . '/admin/orders') ?>">Back</a>
    </div>

    <?php if ($o['payments'] !== []): ?>
        <div class="card" style="max-width:900px">
            <h3 class="card-title">Payments</h3>
            <div class="scroll">
                <table>
                    <thead>
                    <tr>
                        <th>Method</th>
                        <th>Sender</th>
                        <th>TrxID</th>
                        <th class="num">Amount</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($o['payments'] as $payment): ?>
                        <tr>
                            <td><?= (int) $payment['method'] === 2 ? 'bKash' : 'Nagad' ?></td>
                            <td><?= View::e($payment['sender_number']) ?></td>
                            <td><code><?= View::e($payment['transaction_id']) ?></code></td>
                            <td class="num"><?= number_format((float) $payment['amount'], 2) ?></td>
                            <td>
                                <?php if ((int) $payment['status'] === 1): ?>
                                    <span class="pill warn">Pending</span>
                                <?php elseif ((int) $payment['status'] === 2): ?>
                                    <span class="pill">Verified</span>
                                <?php else: ?>
                                    <span class="pill off">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap">
                                <?php if ((int) $payment['status'] === 1): ?>
                                    <button type="button" class="sm approve" data-verify="<?= (int) $payment['id'] ?>">Verify</button>
                                    <button type="button" class="sm reject" data-reject-payment="<?= (int) $payment['id'] ?>">Reject</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width:900px">
        <h3 class="card-title">Status History</h3>
        <ul class="timeline">
            <?php foreach ($o['status_log'] as $log): ?>
                <li>
                    <span class="muted"><?= View::e(date('Y-m-d H:i', (int) $log['created_at'])) ?></span>
                    — <?= View::e($log['note'] ?: '—') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script>
    (function () {
        var el;

        if ((el = document.querySelector('[data-approve]'))) {
            el.addEventListener('click', async function () {
                if (!confirm('Approve this order?')) { return; }
                if (await api('/orders/' + this.dataset.approve + '/approve', {}, 'POST')) { location.reload(); }
            });
        }

        if ((el = document.querySelector('[data-reject]'))) {
            el.addEventListener('click', async function () {
                var note = prompt('Reason for rejecting this order (required):');
                if (!note) { return; }
                if (await api('/orders/' + this.dataset.reject + '/reject', { note: note }, 'POST')) { location.reload(); }
            });
        }

        document.querySelectorAll('[data-status]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                if (!confirm('Move this order to the next status?')) { return; }
                if (await api('/orders/' + this.dataset.status + '/status', { status: parseInt(this.dataset.to, 10) }, 'PUT')) { location.reload(); }
            });
        });

        document.querySelectorAll('[data-verify]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                if (!confirm('Mark this payment as verified? This posts a Customer Receive voucher.')) { return; }
                if (await api('/order-payments/' + this.dataset.verify + '/verify', {}, 'PUT')) { location.reload(); }
            });
        });

        document.querySelectorAll('[data-reject-payment]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                var note = prompt('Reason for rejecting this payment:') || '';
                if (await api('/order-payments/' + this.dataset.rejectPayment + '/reject', { note: note }, 'PUT')) { location.reload(); }
            });
        });
    })();
    </script>
<?php endif; ?>
