<?php

use App\Core\View;

/** @var array<string,mixed> $purchase */
/** @var string $appUrl */

$p = $purchase;
?>
<div class="card" style="max-width:820px">
    <?php if ($p === []): ?>
        <div class="empty">Purchase not found.</div>
    <?php else: ?>
        <h2 class="card-title">
            <?= View::e($p['code']) ?>
            <span class="muted" style="margin-left:auto;font-size:.82rem">
                <?= View::e($p['invoice_date_str'] ?: '—') ?>
            </span>
        </h2>

        <div style="display:flex; gap:2rem; flex-wrap:wrap; margin-bottom:1rem; font-size:.9rem">
            <div><span class="muted">Supplier:</span> <?= View::e($p['supplier_name']) ?></div>
            <div><span class="muted">Bill No:</span> <?= View::e($p['supplier_invoice_no'] ?: '—') ?></div>
            <div><span class="muted">Challan:</span> <?= View::e($p['challan_no'] ?: '—') ?></div>
            <div><span class="muted">PO:</span> <?= View::e($p['po_no'] ?: '—') ?></div>
        </div>

        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="num">Qty</th>
                    <th>Unit</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Line Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($p['items'] as $item): ?>
                    <tr>
                        <td><?= View::e($item['product_name']) ?></td>
                        <td><code class="muted"><?= View::e($item['product_sku']) ?></code></td>
                        <td class="num"><?= rtrim(rtrim(number_format($item['qty'], 4), '0'), '.') ?></td>
                        <td><?= View::e($item['unit_code']) ?></td>
                        <td class="num"><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="num"><?= number_format($item['line_total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="5" class="num">Sub Total</td><td class="num"><?= number_format($p['sub_total'], 2) ?></td></tr>
                    <tr><td colspan="5" class="num">Discount</td><td class="num"><?= number_format($p['discount'], 2) ?></td></tr>
                    <tr><td colspan="5" class="num"><strong>Total</strong></td><td class="num"><strong><?= number_format($p['total'], 2) ?></strong></td></tr>
                </tfoot>
            </table>
        </div>

        <?php if ($p['note'] !== ''): ?>
            <p class="muted" style="margin-top:.75rem"><?= View::e($p['note']) ?></p>
        <?php endif; ?>

        <div style="margin-top:1rem">
            <a class="btn ghost" href="<?= View::e($appUrl . '/admin/purchases') ?>">Back</a>
            <a class="btn" href="<?= View::e($appUrl . '/admin/purchases/' . $p['id'] . '/edit') ?>">Edit</a>
        </div>
    <?php endif; ?>
</div>
