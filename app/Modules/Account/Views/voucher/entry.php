<?php

use App\Core\View;

/** @var array<int,array<string,mixed>> $types */
/** @var array<int,array{id:int,name:string,code:string,chart:string}> $ledgers */
/** @var string $today */
/** @var string $appUrl */
?>
<div class="card">
    <p class="muted">
        এই ফর্মটা সরাসরি <code>POST /api/v1/vouchers</code> এ পাঠায় — অ্যাডমিন পেজের
        নিজস্ব কোনো সেভ-পাথ নাই, API-ই একমাত্র রাস্তা।
    </p>

    <form id="voucher-form">
        <div class="filters">
            <div>
                <label for="type">ধরন</label>
                <select id="type" name="type" required>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int) $type['id'] ?>"><?= View::e($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="date">তারিখ</label>
                <input id="date" name="date" type="date" value="<?= View::e($today) ?>" required>
            </div>
            <div>
                <label for="amount">পরিমাণ</label>
                <input id="amount" name="amount" type="number" step="0.01" min="0.01" required>
            </div>
        </div>

        <div class="filters" style="margin-top:.75rem">
            <div>
                <label for="debit_ledger">ডেবিট লেজার</label>
                <select id="debit_ledger" name="debit_ledger" required>
                    <option value="">— বাছুন —</option>
                    <?php foreach ($ledgers as $ledger): ?>
                        <option value="<?= (int) $ledger['id'] ?>"><?= View::e($ledger['chart'] . ' › ' . $ledger['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="credit_ledger">ক্রেডিট লেজার</label>
                <select id="credit_ledger" name="credit_ledger" required>
                    <option value="">— বাছুন —</option>
                    <?php foreach ($ledgers as $ledger): ?>
                        <option value="<?= (int) $ledger['id'] ?>"><?= View::e($ledger['chart'] . ' › ' . $ledger['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1 1 240px">
                <label for="note">বিবরণ</label>
                <input id="note" name="note" maxlength="255" style="width:100%">
            </div>
        </div>

        <div style="margin-top:1rem">
            <button type="submit">সেভ করুন</button>
        </div>
    </form>

    <div id="result" style="margin-top:1rem"></div>
</div>

<script>
document.getElementById('voucher-form').addEventListener('submit', async function (event) {
    event.preventDefault();

    const result = document.getElementById('result');
    const body   = Object.fromEntries(new FormData(this).entries());

    result.innerHTML = '<p class="muted">সেভ হচ্ছে…</p>';

    try {
        const response = await fetch('<?= View::e($appUrl) ?>/api/v1/vouchers', {
            method:      'POST',
            headers:     { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body:        JSON.stringify(body)
        });

        const data = await response.json();

        result.innerHTML = (data.m || [])
            .map(m => '<div class="msg msg-' + m[0] + '">' + m[1] + '</div>')
            .join('');

        if (data.status === 1) {
            this.reset();
            document.getElementById('date').value = '<?= View::e($today) ?>';

            if (data.voucher) {
                result.innerHTML += '<p>ভাউচার কোড: <a href="<?= View::e($appUrl) ?>/admin/vouchers/'
                    + data.voucher.id + '">' + data.voucher.code + '</a></p>';
            }
        }
    } catch (error) {
        result.innerHTML = '<div class="msg msg-e">রিকোয়েস্ট পাঠানো যায়নি: ' + error.message + '</div>';
    }
});
</script>
