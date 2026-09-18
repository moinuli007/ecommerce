<?php

namespace App\Modules\Sale\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\RequestTime;
use App\Enum\AutoLedger;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\VoucherType;
use App\Modules\Account\Services\LedgerAccounts;
use App\Modules\Account\Services\Voucher;
use App\Modules\Sale\Models\Order;
use App\Modules\Sale\Models\OrderPayment;
use RuntimeException;

/**
 * ম্যানুয়াল bKash/Nagad পেমেন্ট রেফারেন্স — রেকর্ড/ভেরিফাই/রিজেক্ট।
 * গেটওয়ে ইন্টিগ্রেশন না, অ্যাডমিন হাতে TrxID মিলিয়ে ভেরিফাই করে।
 * doc/10-storefront-order.md §৭, §৮।
 */
final class PaymentService
{
    /**
     * অ্যাডমিন হাতেও একটা পেমেন্ট রেকর্ড করতে পারে (যেমন ফোনে কনফার্ম করে) —
     * চেকআউটের সময় `OrderService::checkout()` এর ভেতরেই সরাসরি ইনসার্ট হয়,
     * এই মেথড দিয়ে না।
     *
     * @param  array<string,mixed> $data order_id, method, sender_number?, transaction_id?, amount
     * @return int নতুন order_payments id
     */
    public static function record(array $data): int
    {
        $orderId = (int) ($data['order_id'] ?? 0);

        if (Order::find($orderId) === []) {
            throw new RuntimeException('Order not found.');
        }

        $method = PaymentMethod::tryFrom((int) ($data['method'] ?? 0));

        if ($method === null || $method === PaymentMethod::Cod) {
            throw new RuntimeException('Invalid payment method.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 4);

        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        return OrderPayment::create([
            'order_id'       => $orderId,
            'method'         => $method->value,
            'sender_number'  => mb_substr(trim((string) ($data['sender_number'] ?? '')), 0, 20),
            'transaction_id' => mb_substr(trim((string) ($data['transaction_id'] ?? '')), 0, 50),
            'amount'         => $amount,
            'status'         => PaymentStatus::Pending->value,
        ]);
    }

    /**
     * ভেরিফাই — `CustomerReceive` ভাউচার পোস্ট (Dr MobileBanking / Cr
     * AdvanceFromCustomer, doc §৮ O-05) + `orders.advance_paid` বাড়ে।
     *
     * @return array<string,mixed>
     */
    public static function verify(int $paymentId): array
    {
        $payment = self::requirePending($paymentId);
        $order   = Order::find((int) $payment['order_id']);

        if ($order === []) {
            throw new RuntimeException('Order not found.');
        }

        return DB::transaction(static function () use ($payment, $order): array {
            $paymentId = (int) $payment['id'];

            OrderPayment::updateById($paymentId, [
                'status'      => PaymentStatus::Verified->value,
                'verified_by' => Auth::id(),
                'verified_at' => RequestTime::now(),
            ]);

            Voucher::create(
                VoucherType::CustomerReceive,
                (float) $payment['amount'],
                LedgerAccounts::systemLedger(AutoLedger::MobileBanking),
                LedgerAccounts::systemLedger(AutoLedger::AdvanceFromCustomer),
                RequestTime::now(),
                'Advance ' . PaymentMethod::from((int) $payment['method'])->label() . ' — ' . $order['code'],
                (string) $order['id']
            );

            Order::updateById((int) $order['id'], [
                'advance_paid' => round((float) $order['advance_paid'] + (float) $payment['amount'], 4),
            ]);

            return OrderPayment::find($paymentId);
        });
    }

    /** @return array<string,mixed> */
    public static function reject(int $paymentId, string $note = ''): array
    {
        $payment = self::requirePending($paymentId);

        OrderPayment::updateById((int) $payment['id'], [
            'status'      => PaymentStatus::Rejected->value,
            'note'        => mb_substr($note, 0, 255),
            'verified_by' => Auth::id(),
            'verified_at' => RequestTime::now(),
        ]);

        return OrderPayment::find((int) $payment['id']);
    }

    /** @return array<string,mixed> */
    private static function requirePending(int $paymentId): array
    {
        $payment = OrderPayment::find($paymentId);

        if ($payment === []) {
            throw new RuntimeException('Payment not found.');
        }

        if ((int) $payment['status'] !== PaymentStatus::Pending->value) {
            throw new RuntimeException('Only pending payments can be verified or rejected.');
        }

        return $payment;
    }
}
