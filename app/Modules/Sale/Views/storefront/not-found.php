<?php

use App\Core\View;

/** @var string $message */
/** @var string $appUrl */
?>
<div class="wrap" style="padding:5rem 1rem; text-align:center">
    <h1 style="font-size:1.6rem; margin-bottom:.5rem">Page Not Found</h1>
    <p class="muted"><?= View::e($message ?? 'This page is not available.') ?></p>
    <a class="btn" style="margin-top:1.25rem" href="<?= View::e($appUrl) ?>/collections/all">Continue Shopping</a>
</div>
