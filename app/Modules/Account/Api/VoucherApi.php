<?php

namespace App\Modules\Account\Api;

use App\Core\Auth;
use App\Core\Message;
use App\Core\Request;
use App\Core\Response;
use App\Core\Utility;
use App\Core\Validator;
use App\Enum\TransactionType;
use App\Enum\VoucherType;
use App\Modules\Account\Services\LedgerAccounts;
use App\Modules\Account\Services\Voucher;
use App\Modules\Account\Services\VoucherDetails;
use RuntimeException;

/**
 * ভাউচার API।
 *
 * এই ক্লাসের প্রতিটা মেথড `Response::payload()` শেপের array ফেরত দেয়।
 * দুই জায়গা থেকেই কল হয় (Decision D-04, doc/01-architecture.md):
 *
 *   ১. HTTP API রাউট (routes/api.php) → রেসপন্স JSON হয়ে যায়
 *   ২. admin/web কন্ট্রোলার → একই array নিয়ে ভিউ রেন্ডার করে
 *
 * অর্থাৎ web-এ যা দেখা যায় তার পুরোটাই API-র মধ্য দিয়ে আসে, আলাদা কোনো
 * ডেটা-পাথ নাই।
 */
final class VoucherApi
{
    /**
     * GET /api/v1/vouchers
     * ফিল্টার: type, ledger_id, reference, from, to, code, page, per_page
     *
     * @return array<string,mixed>
     */
    public static function index(): array
    {
        $result = VoucherDetails::search([
            'type'      => Request::array('type'),
            'ledger_id' => Request::array('ledger_id'),
            'reference' => Request::string('reference'),
            'user_id'   => Request::array('user_id'),
            'from'      => Request::string('from'),
            'to'        => Request::string('to'),
            'code'      => Request::string('code'),
            'page'      => Request::int('page', 1),
            'per_page'  => Request::int('per_page', VoucherDetails::DEFAULT_PER_PAGE),
        ]);

        Response::ok();
        Response::set('vouchers', $result['data']);
        Response::set('pagination', [
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
            'pages'    => $result['per_page'] > 0 ? (int) ceil($result['total'] / $result['per_page']) : 1,
        ]);

        return Response::payload();
    }

    /**
     * GET /api/v1/vouchers/{id}
     *
     * @return array<string,mixed>
     */
    public static function show(): array
    {
        $voucherId = Request::paramInt('id');
        $voucher   = VoucherDetails::byId($voucherId);

        if ($voucher === []) {
            return Response::error('Voucher not found.');
        }

        return Response::success('', ['voucher' => $voucher]);
    }

    /**
     * GET /api/v1/vouchers/code/{code}
     *
     * @return array<string,mixed>
     */
    public static function showByCode(): array
    {
        $voucher = VoucherDetails::byCode((string) Request::param('code', ''));

        if ($voucher === []) {
            return Response::error('Voucher not found.');
        }

        return Response::success('', ['voucher' => $voucher]);
    }

    /**
     * POST /api/v1/vouchers
     *
     * সাধারণ ভাউচার:
     *   type, amount, debit_ledger, credit_ledger, date, note, reference
     *
     * কম্পাউন্ড ভাউচার (amount/debit_ledger এর বদলে):
     *   type, date, note, lines[] = [{ledger_id, debit, credit, note}, ...]
     *
     * @return array<string,mixed>
     */
    public static function store(): array
    {
        $type = self::resolveType(Request::int('type'));

        if ($type === null) {
            return Response::error('Invalid voucher type.');
        }

        if (!$type->isManual()) {
            return Response::error($type->label() . ' vouchers cannot be created manually — the system creates them itself.');
        }

        $lines = Request::array('lines');
        $time  = Utility::toTime(Request::string('date'));
        $note  = Request::string('note');

        try {
            if ($lines !== []) {
                $voucherId = Voucher::createCompound(
                    $type,
                    self::normalizeLineInput($lines),
                    $time,
                    $note,
                    Request::string('reference')
                );
            } else {
                if (!Validator::check(Request::all(), [
                    'amount'        => 'required|numeric|min:0.0001',
                    'debit_ledger'  => 'required|int',
                    'credit_ledger' => 'required|int',
                    'date'          => 'required|date',
                    'note'          => 'max:255',
                ])) {
                    return Response::payload();
                }

                $voucherId = Voucher::create(
                    $type,
                    Request::float('amount'),
                    Request::int('debit_ledger'),
                    Request::int('credit_ledger'),
                    $time,
                    $note,
                    Request::string('reference')
                );
            }
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Voucher saved.', [
            'voucher_id' => $voucherId,
            'voucher'    => VoucherDetails::byId($voucherId),
        ]);
    }

    /**
     * POST /api/v1/vouchers/opening
     * ওপেনিং ব্যালেন্স: type, ledger_id, amount, nature (1=Debit, 2=Credit), date, reference
     *
     * @return array<string,mixed>
     */
    public static function storeOpening(): array
    {
        $type = self::resolveType(Request::int('type'));

        if ($type === null || !$type->isOpening()) {
            return Response::error('Invalid opening voucher type.');
        }

        if (!Validator::check(Request::all(), [
            'ledger_id' => 'required|int',
            'amount'    => 'required|numeric|min:0.0001',
            'nature'    => 'required|in:1,2',
            'date'      => 'required|date',
        ])) {
            return Response::payload();
        }

        try {
            $voucherId = Voucher::opening(
                $type,
                Request::int('ledger_id'),
                Request::float('amount'),
                TransactionType::from(Request::int('nature')),
                Utility::toTime(Request::string('date')),
                Request::string('reference')
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Opening balance saved.', [
            'voucher_id' => $voucherId,
            'voucher'    => VoucherDetails::byId($voucherId),
        ]);
    }

    /**
     * PUT /api/v1/vouchers/{id}
     *
     * @return array<string,mixed>
     */
    public static function update(): array
    {
        $voucherId = Request::paramInt('id');
        $existing  = VoucherDetails::byId($voucherId);

        if ($existing === []) {
            return Response::error('Voucher not found.');
        }

        $type = VoucherType::tryFrom($existing['type_id']);

        if ($type === null || !$type->isManual()) {
            return Response::error('System-generated vouchers cannot be edited — change it from the source document.');
        }

        try {
            Voucher::update(
                $voucherId,
                Request::has('amount') ? Request::float('amount') : null,
                Request::has('note') ? Request::string('note') : null,
                Request::has('date') ? Utility::toTime(Request::string('date')) : null,
                Request::has('debit_ledger') ? Request::int('debit_ledger') : null,
                Request::has('credit_ledger') ? Request::int('credit_ledger') : null
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::success('Voucher updated.', [
            'voucher' => VoucherDetails::byId($voucherId),
        ]);
    }

    /**
     * DELETE /api/v1/vouchers/{id}
     *
     * @return array<string,mixed>
     */
    public static function destroy(): array
    {
        $voucherId = Request::paramInt('id');
        $existing  = VoucherDetails::byId($voucherId);

        if ($existing === []) {
            return Response::error('Voucher not found.');
        }

        $type = VoucherType::tryFrom($existing['type_id']);

        if ($type === null || !$type->isManual()) {
            return Response::error('System-generated vouchers cannot be deleted — cancel the source document instead.');
        }

        if (!Auth::isAdmin()) {
            return Response::error('You do not have permission to delete vouchers.');
        }

        Voucher::delete($voucherId);

        return Response::success('Voucher deleted.', ['voucher_id' => $voucherId]);
    }

    /**
     * GET /api/v1/vouchers/form-data
     * ভাউচার এন্ট্রি ফর্মের ড্রপডাউন ডেটা — টাইপ লিস্ট + লেজার লিস্ট।
     *
     * @return array<string,mixed>
     */
    public static function formData(): array
    {
        return Response::success('', [
            'voucher_types' => VoucherType::manual(),
            'ledgers'       => LedgerAccounts::options(),
            'natures'       => [
                ['id' => TransactionType::Debit->value,  'name' => TransactionType::Debit->label()],
                ['id' => TransactionType::Credit->value, 'name' => TransactionType::Credit->label()],
            ],
            'today'         => date('Y-m-d'),
        ]);
    }

    // -------------------------------------------------------------------------
    // হেল্পার
    // -------------------------------------------------------------------------

    private static function resolveType(int $value): ?VoucherType
    {
        return VoucherType::tryFrom($value);
    }

    /**
     * ফর্ম থেকে আসা lines[] কে Voucher::createCompound() এর শেপে আনে।
     *
     * @param  array<int|string,mixed> $lines
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeLineInput(array $lines): array
    {
        $out = [];

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            $out[] = [
                'ledger_id' => (int) ($line['ledger_id'] ?? 0),
                'debit'     => (float) ($line['debit'] ?? 0),
                'credit'    => (float) ($line['credit'] ?? 0),
                'note'      => (string) ($line['note'] ?? ''),
            ];
        }

        if ($out === []) {
            Message::error('No voucher lines were found.');
        }

        return $out;
    }
}
