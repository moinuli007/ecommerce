<?php

use App\Core\Menu;
use App\Core\View;

/** @var array<string,mixed> $data */
/** @var string $appUrl */

$stats   = $data['stats'] ?? [];
$pending = $data['pending'] ?? [];
$sales   = $data['daily_sales'] ?? [];
$masters = $data['master_summary'] ?? [];
$top     = $data['top_ledgers'] ?? [];
$recent  = $data['recent_vouchers'] ?? [];
$trial   = $data['trial_balance'] ?? ['is_balanced' => true, 'debit' => 0, 'credit' => 0, 'ledgers' => 0];

$money = static fn (float $n): string => '৳ ' . number_format($n, 2);

$maxSale = 0.0;
foreach ($sales as $point) {
    $maxSale = max($maxSale, (float) $point['amount']);
}
?>
<style>
    .stat-grid { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(215px, 1fr)); margin-bottom:1rem; }
    .stat {
        background:var(--card); border:1px solid var(--line); border-radius:12px; padding:1rem;
        box-shadow:var(--shadow); display:flex; align-items:center; gap:.9rem;
    }
    .stat-icon { width:44px; height:44px; border-radius:11px; display:grid; place-items:center; flex:0 0 auto; }
    .stat-icon svg { width:21px; height:21px; }
    .stat-brand   { background:var(--brand-soft); color:var(--brand); }
    .stat-success { background:rgba(22,163,74,.12);  color:var(--success); }
    .stat-danger  { background:rgba(220,38,38,.12);  color:var(--danger); }
    .stat-warning { background:rgba(217,119,6,.12);  color:var(--warning); }
    .stat-info    { background:rgba(37,99,235,.12);  color:var(--info); }
    .stat-label { font-size:.79rem; color:var(--muted); margin-bottom:.15rem; }
    .stat-value { font-size:1.22rem; font-weight:700; font-variant-numeric:tabular-nums; }

    .two-col { display:grid; gap:1rem; grid-template-columns:2fr 1fr; align-items:start; }
    @media (max-width:1000px) { .two-col { grid-template-columns:1fr; } }

    .kv { display:flex; justify-content:space-between; gap:1rem; padding:.55rem 0; border-bottom:1px solid var(--line); }
    .kv:last-child { border-bottom:0; }
    .kv strong { font-variant-numeric:tabular-nums; }

    .chart { display:flex; align-items:flex-end; gap:.35rem; height:150px; padding-top:.5rem; }
    .bar-wrap { flex:1 1 0; display:flex; flex-direction:column; align-items:center; gap:.35rem; min-width:0; }
    .bar {
        width:100%; max-width:34px; background:var(--brand); border-radius:5px 5px 0 0;
        min-height:3px; transition:opacity .15s;
    }
    .bar-wrap:hover .bar { opacity:.75; }
    .bar-label { font-size:.66rem; color:var(--muted); white-space:nowrap; transform:rotate(-45deg); transform-origin:center; }

    .trial-banner { display:flex; align-items:center; gap:.6rem; }
</style>

<!-- ট্রায়াল ব্যালেন্স মিলছে কি না — হিসাবের স্বাস্থ্য পরীক্ষা -->
<?php if (!$trial['is_balanced']): ?>
    <div class="msg msg-e trial-banner">
        <?= Menu::icon('alert') ?>
        <span>
            <strong>ট্রায়াল ব্যালেন্স মিলছে না।</strong>
            ডেবিট <?= number_format((float) $trial['debit'], 2) ?> ·
            ক্রেডিট <?= number_format((float) $trial['credit'], 2) ?> —
            কোথাও সরাসরি DB-তে এন্ট্রি হয়েছে কি না দেখুন।
        </span>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <div class="stat">
            <div class="stat-icon stat-<?= View::e($stat['tone']) ?>"><?= Menu::icon($stat['icon']) ?></div>
            <div style="min-width:0">
                <div class="stat-label"><?= View::e($stat['label']) ?></div>
                <div class="stat-value <?= $stat['value'] < 0 ? 'neg' : '' ?>"><?= $money((float) $stat['value']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="two-col">
    <div>
        <!-- দৈনিক বিক্রি -->
        <div class="card">
            <h2 class="card-title">
                শেষ ১৪ দিনের বিক্রি
                <span class="muted" style="margin-left:auto;font-size:.8rem">
                    সর্বোচ্চ <?= $money($maxSale) ?>
                </span>
            </h2>

            <?php if ($maxSale <= 0): ?>
                <div class="empty">এখনো কোনো বিক্রি হয়নি।</div>
            <?php else: ?>
                <div class="chart">
                    <?php foreach ($sales as $point): ?>
                        <?php $height = $maxSale > 0 ? max(3, (int) round((float) $point['amount'] / $maxSale * 118)) : 3; ?>
                        <div class="bar-wrap" title="<?= View::e($point['label']) ?> — <?= $money((float) $point['amount']) ?>">
                            <div class="bar" style="height:<?= $height ?>px"></div>
                            <div class="bar-label"><?= View::e($point['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- সাম্প্রতিক ভাউচার -->
        <div class="card">
            <h2 class="card-title">
                সাম্প্রতিক ভাউচার
                <a href="<?= View::e($appUrl . '/admin/vouchers') ?>"
                   style="margin-left:auto;font-size:.82rem;color:var(--brand)">সব দেখুন →</a>
            </h2>

            <?php if ($recent === []): ?>
                <div class="empty">
                    এখনো কোনো ভাউচার নাই।
                    <a href="<?= View::e($appUrl . '/admin/vouchers/entry') ?>" style="color:var(--brand)">প্রথমটা তৈরি করুন</a>।
                </div>
            <?php else: ?>
                <div class="scroll">
                    <table>
                        <thead>
                        <tr>
                            <th>কোড</th><th>তারিখ</th><th>ধরন</th>
                            <th>ডেবিট</th><th>ক্রেডিট</th><th class="num">পরিমাণ</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $voucher): ?>
                            <tr>
                                <td><a href="<?= View::e($appUrl . '/admin/vouchers/' . $voucher['id']) ?>"><?= View::e($voucher['code']) ?></a></td>
                                <td><?= View::e($voucher['date_display']) ?></td>
                                <td><?= View::e($voucher['type']) ?></td>
                                <td><?= View::e($voucher['debit_ledger_name']) ?><?= $voucher['is_compound'] ? ' <span class="muted">+</span>' : '' ?></td>
                                <td><?= View::e($voucher['credit_ledger_name']) ?><?= $voucher['is_compound'] ? ' <span class="muted">+</span>' : '' ?></td>
                                <td class="num"><?= number_format((float) $voucher['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <!-- আটকে থাকা টাকা -->
        <div class="card">
            <h2 class="card-title">আটকে থাকা টাকা</h2>
            <?php foreach ($pending as $row): ?>
                <div class="kv">
                    <span class="muted"><?= View::e($row['label']) ?></span>
                    <strong><?= $money((float) $row['value']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- মাস্টার অ্যাকাউন্ট সারাংশ -->
        <div class="card">
            <h2 class="card-title">হিসাবের সারাংশ</h2>
            <?php if ($masters === []): ?>
                <div class="empty">কোনো লেনদেন নাই।</div>
            <?php else: ?>
                <?php foreach ($masters as $master): ?>
                    <div class="kv">
                        <span>
                            <?= View::e($master['name']) ?>
                            <span class="muted" style="font-size:.76rem">(<?= View::e($master['nature']) ?>)</span>
                        </span>
                        <strong class="<?= $master['balance'] < 0 ? 'neg' : '' ?>"><?= $money((float) $master['balance']) ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- বড় ব্যালেন্সের লেজার -->
        <div class="card">
            <h2 class="card-title">
                বড় ব্যালেন্স
                <a href="<?= View::e($appUrl . '/admin/ledgers') ?>"
                   style="margin-left:auto;font-size:.82rem;color:var(--brand)">চার্ট →</a>
            </h2>

            <?php if ($top === []): ?>
                <div class="empty">কোনো লেজারে ব্যালেন্স নাই।</div>
            <?php else: ?>
                <?php foreach ($top as $ledger): ?>
                    <div class="kv">
                        <span style="min-width:0">
                            <a href="<?= View::e($appUrl . '/admin/ledgers/' . $ledger['id'] . '/statement') ?>"
                               style="color:var(--brand)"><?= View::e($ledger['name']) ?></a>
                            <span class="muted" style="display:block;font-size:.74rem"><?= View::e($ledger['chart_name']) ?></span>
                        </span>
                        <strong class="<?= $ledger['balance'] < 0 ? 'neg' : '' ?>">
                            <?= $money((float) $ledger['balance']) ?>
                            <span class="muted" style="font-weight:400;font-size:.76rem"><?= View::e($ledger['side']) ?></span>
                        </strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- হিসাবের স্বাস্থ্য -->
        <div class="card">
            <h2 class="card-title">হিসাবের স্বাস্থ্য</h2>
            <div class="kv">
                <span class="muted">ট্রায়াল ব্যালেন্স</span>
                <strong class="<?= $trial['is_balanced'] ? 'pos' : 'neg' ?>">
                    <?= $trial['is_balanced'] ? '✓ মিলছে' : '✗ মিলছে না' ?>
                </strong>
            </div>
            <div class="kv">
                <span class="muted">মোট ডেবিট</span>
                <strong><?= number_format((float) $trial['debit'], 2) ?></strong>
            </div>
            <div class="kv">
                <span class="muted">মোট ক্রেডিট</span>
                <strong><?= number_format((float) $trial['credit'], 2) ?></strong>
            </div>
            <div class="kv">
                <span class="muted">সক্রিয় লেজার</span>
                <strong><?= (int) $trial['ledgers'] ?></strong>
            </div>
        </div>
    </div>
</div>
