<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $tree */
/** @var string $appUrl */
?>
<?php if ($tree === []): ?>
    <div class="card">
        <div class="empty">
            চার্ট এখনো খালি। প্রথম ভাউচার পোস্ট হলে সিস্টেম লেজারগুলো নিজে থেকেই তৈরি হয়ে যাবে।
        </div>
    </div>
<?php else: ?>
    <?php foreach ($tree as $master): ?>
        <div class="card">
            <h2 style="font-size:1.05rem;margin:0 0 .5rem">
                <?= View::e($master['name']) ?>
                <span class="muted">(<?= View::e($master['code']) ?> · <?= View::e($master['nature']) ?>)</span>
            </h2>

            <?php foreach ($master['charts'] as $chart): ?>
                <div style="margin:.5rem 0 .75rem 1rem">
                    <strong><?= View::e($chart['name']) ?></strong>
                    <span class="muted"><?= View::e($chart['code']) ?></span>

                    <?php if ($chart['ledgers'] === []): ?>
                        <p class="muted" style="margin:.25rem 0 0 1rem">— কোনো লেজার নাই —</p>
                    <?php else: ?>
                        <ul style="margin:.25rem 0 0 1rem;padding-left:1rem">
                            <?php foreach ($chart['ledgers'] as $ledger): ?>
                                <li>
                                    <a href="<?= View::e($appUrl . '/admin/ledgers/' . $ledger['id'] . '/statement') ?>">
                                        <?= View::e($ledger['name']) ?>
                                    </a>
                                    <span class="muted"><?= View::e($ledger['code']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
