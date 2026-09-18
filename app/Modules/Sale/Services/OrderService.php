<?php

namespace App\Modules\Sale\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\QueryBuilder;
use App\Core\RequestTime;
use App\Enum\AutoLedger;
use App\Enum\OrderStatus;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\StockChangeType;
use App\Enum\VoucherType;
use App\Modules\Account\Services\CodeGenerator;
use App\Modules\Account\Services\LedgerAccounts;
use App\Modules\Account\Services\Voucher;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Purchase\Services\StockService;
use App\Modules\Sale\Models\CartItem;
use App\Modules\Sale\Models\DeliveryZone;
use App\Modules\Sale\Models\Order;
use App\Modules\Sale\Models\OrderItem;
use App\Modules\Sale\Models\OrderPayment;
use App\Modules\Sale\Models\OrderStatusLog;
use RuntimeException;

/**
 * চেকআউট + অর্ডার-অনুমোদন + স্ট্যাটাস ট্রানজিশন + ভাউচার পোস্ট।
 * doc/10-storefront-order.md §৫, §৬, §৮।
 */
final class OrderService
{
    // -------------------------------------------------------------------------
    // চেকআউট
    // -------------------------------------------------------------------------

    /**
     * কার্ট → অর্ডার। স্টক এখনো কমে না, ভাউচারও পোস্ট হয় না — সেটা `Shipped`
     * এ যাওয়ার সময় (§৮)। শুধু availability চেক হয়।
     *
     * @param  array<string,mixed> $data recipient_name, recipient_phone, shipping_address,
     *   delivery_zone_id, payment_method, sender_number?, transaction_id?, note?
     * @return array<string,mixed> নতুন orders রো
     */
    public static function checkout(string $cartToken, array $data): array
    {
        $cart  = CartService::resolveCart($cartToken);
        $items = CartItem::ofCart((int) $cart['id']);

        if ($items === []) {
            throw new RuntimeException('Your cart is empty.');
        }

        $recipientName  = trim((string) ($data['recipient_name'] ?? ''));
        $recipientPhone = trim((string) ($data['recipient_phone'] ?? ''));
        $address        = trim((string) ($data['shipping_address'] ?? ''));

        if ($recipientName === '' || $recipientPhone === '' || $address === '') {
            throw new RuntimeException('Name, phone and address are required.');
        }

        $zone = DeliveryZone::find((int) ($data['delivery_zone_id'] ?? 0));

        if ($zone === [] || (int) $zone['isActive'] !== 1) {
            throw new RuntimeException('Please select a delivery area.');
        }

        $method = PaymentMethod::tryFrom((int) ($data['payment_method'] ?? PaymentMethod::Cod->value)) ?? PaymentMethod::Cod;

        $senderNumber  = trim((string) ($data['sender_number'] ?? ''));
        $transactionId = trim((string) ($data['transaction_id'] ?? ''));

        if ($method->requiresReference() && ($senderNumber === '' || $transactionId === '')) {
            throw new RuntimeException('Please provide the sender number and transaction ID.');
        }

        [$lines, $subTotal] = self::priceCartLines($items);

        $freeThreshold = (float) $zone['free_delivery_threshold'];
        $deliveryFee   = $freeThreshold > 0 && $subTotal >= $freeThreshold ? 0.0 : (float) $zone['fee'];
        $grandTotal    = round($subTotal + $deliveryFee, 4);

        $customer = CustomerService::findOrCreateByPhone([
            'name'  => $recipientName,
            'phone' => $recipientPhone,
        ]);

        return DB::transaction(static function () use (
            $lines, $subTotal, $deliveryFee, $grandTotal, $zone, $customer,
            $recipientName, $recipientPhone, $address, $method, $senderNumber, $transactionId, $data, $cart
        ): array {
            $orderId = Order::create([
                'code'               => CodeGenerator::next('order', 'ORD'),
                'customer_id'        => (int) $customer['id'],
                'status'             => OrderStatus::Pending->value,
                'recipient_name'     => mb_substr($recipientName, 0, 150),
                'recipient_phone'    => mb_substr($recipientPhone, 0, 20),
                'shipping_address'   => $address,
                'delivery_zone_id'   => (int) $zone['id'],
                'delivery_zone_name' => (string) $zone['name'],
                'sub_total'          => $subTotal,
                'discount_total'     => 0,
                'delivery_fee'       => $deliveryFee,
                'grand_total'        => $grandTotal,
                'payment_method'     => $method->value,
                'advance_paid'       => 0,
                'note'               => mb_substr(trim((string) ($data['note'] ?? '')), 0, 500),
                'placed_at'          => RequestTime::now(),
            ]);

            foreach ($lines as $line) {
                $line['order_id'] = $orderId;
                DB::insert('order_items', $line);
            }

            DB::insert('order_status_log', [
                'order_id'    => $orderId,
                'from_status' => 0,
                'to_status'   => OrderStatus::Pending->value,
                'note'        => 'Order placed',
                'created_at'  => RequestTime::now(),
                'created_by'  => 0,
            ]);

            if ($method->requiresReference()) {
                OrderPayment::create([
                    'order_id'       => $orderId,
                    'method'         => $method->value,
                    'sender_number'  => mb_substr($senderNumber, 0, 20),
                    'transaction_id' => mb_substr($transactionId, 0, 50),
                    'amount'         => $grandTotal,
                    'status'         => PaymentStatus::Pending->value,
                ]);
            }

            DB::table('cart_items')->where('cart_id', (int) $cart['id'])->delete();

            return Order::find($orderId);
        });
    }

    /**
     * প্রতি কার্ট-লাইনে checkout-মুহূর্তের দাম/স্টক যাচাই করে `order_items`
     * এর জন্য স্ন্যাপশট রো বানায়।
     *
     * @param  array<int,array<string,mixed>> $items
     * @return array{0:array<int,array<string,mixed>>,1:float} [lines, sub_total]
     */
    private static function priceCartLines(array $items): array
    {
        $lines    = [];
        $subTotal = 0.0;

        foreach ($items as $item) {
            $product = Product::find((int) $item['product_id']);

            if ($product === [] || (int) $product['isActive'] !== 1) {
                throw new RuntimeException('One of the items in your cart is no longer available.');
            }

            $variantId = (int) $item['variant_id'];
            $variant   = $variantId > 0 ? ProductVariant::find($variantId) : [];

            if ($variantId > 0 && ($variant === [] || (int) $variant['isActive'] !== 1)) {
                throw new RuntimeException('One of the selected variants is no longer available.');
            }

            $qty    = (float) $item['qty'];
            $onHand = StockService::onHand((int) $product['id'], $variantId);

            if ($onHand < $qty) {
                throw new RuntimeException($product['name'] . ' is out of stock.');
            }

            $price     = ProductService::effectivePrice($product, $variant);
            $lineTotal = round($price['price'] * $qty, 4);
            $subTotal += $lineTotal;

            $lines[] = [
                'product_id'   => (int) $product['id'],
                'variant_id'   => $variantId,
                'product_name' => mb_substr((string) $product['name'], 0, 191),
                'variant_name' => mb_substr((string) ($variant['name'] ?? ''), 0, 191),
                'sku'          => mb_substr((string) ($variant !== [] ? $variant['sku'] : $product['sku']), 0, 80),
                'unit_price'   => $price['price'],
                'qty'          => $qty,
                'line_total'   => $lineTotal,
                'cost_price'   => (float) ($variant !== [] ? $variant['purchase_price'] : $product['purchase_price']),
            ];
        }

        return [$lines, round($subTotal, 4)];
    }

    // -------------------------------------------------------------------------
    // অর্ডার-অনুমোদন — COD এর একমাত্র গেট (§৫)
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function approve(int $orderId, string $note = ''): array
    {
        $order = self::requireStatus($orderId, OrderStatus::Pending, 'approved');

        return self::transition($order, OrderStatus::Confirmed, $note !== '' ? $note : 'Approved');
    }

    /** @return array<string,mixed> */
    public static function reject(int $orderId, string $note): array
    {
        if (trim($note) === '') {
            throw new RuntimeException('Please provide a reason for rejection.');
        }

        $order = self::requireStatus($orderId, OrderStatus::Pending, 'rejected');

        return self::transition($order, OrderStatus::Cancelled, $note);
    }

    // -------------------------------------------------------------------------
    // পরের ধাপের স্ট্যাটাস (Processing/Shipped/Delivered/Returned)
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function changeStatus(int $orderId, int $toStatusValue, string $note = ''): array
    {
        $order = Order::find($orderId);

        if ($order === []) {
            throw new RuntimeException('Order not found.');
        }

        $to = OrderStatus::tryFrom($toStatusValue);

        if ($to === null) {
            throw new RuntimeException('Unknown order status.');
        }

        $current = OrderStatus::from((int) $order['status']);

        if (!$current->canTransitionTo($to)) {
            throw new RuntimeException("Cannot move an order from {$current->label()} to {$to->label()}.");
        }

        // bKash/Nagad — Confirmed এর পরে যেতে ভেরিফায়েড পেমেন্ট লাগবে (§৫)
        if ($to !== OrderStatus::Cancelled) {
            $method = PaymentMethod::from((int) $order['payment_method']);

            if ($method->requiresReference() && OrderPayment::verifiedTotal($orderId) < (float) $order['grand_total']) {
                throw new RuntimeException('Payment must be verified before this order can proceed.');
            }
        }

        return self::transition($order, $to, $note);
    }

    // -------------------------------------------------------------------------
    // ভেতরের কাজ
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private static function requireStatus(int $orderId, OrderStatus $expected, string $action): array
    {
        $order = Order::find($orderId);

        if ($order === []) {
            throw new RuntimeException('Order not found.');
        }

        if ((int) $order['status'] !== $expected->value) {
            throw new RuntimeException("Only {$expected->label()} orders can be $action.");
        }

        return $order;
    }

    /**
     * @param  array<string,mixed> $order
     * @return array<string,mixed>
     */
    private static function transition(array $order, OrderStatus $to, string $note): array
    {
        $orderId = (int) $order['id'];
        $from    = (int) $order['status'];

        return DB::transaction(static function () use ($orderId, $from, $to, $note): array {
            $updates = ['status' => $to->value];

            if ($to === OrderStatus::Shipped) {
                $updates['shipped_at'] = RequestTime::now();
            }

            Order::updateById($orderId, $updates);

            DB::insert('order_status_log', [
                'order_id'    => $orderId,
                'from_status' => $from,
                'to_status'   => $to->value,
                'note'        => mb_substr($note, 0, 255),
                'created_at'  => RequestTime::now(),
                'created_by'  => Auth::id(),
            ]);

            $fresh = Order::find($orderId);

            if ($to === OrderStatus::Shipped) {
                self::postShipmentVouchers($fresh);
            } elseif ($to === OrderStatus::Returned) {
                self::postReturnVouchers($fresh);
            }

            return Order::find($orderId);
        });
    }

    /**
     * `Shipped` — কুরিয়ারে দেওয়া হলো। Sale + ShippingIncome (compound) +
     * CostOfGoodsSold ভাউচার, আর স্টক কমে। doc §৮।
     *
     * @param array<string,mixed> $order
     */
    private static function postShipmentVouchers(array $order): void
    {
        $orderId = (int) $order['id'];
        $items   = OrderItem::ofOrder($orderId);

        $drAuto = PaymentMethod::from((int) $order['payment_method']) === PaymentMethod::Cod
            ? AutoLedger::CodReceivable
            : AutoLedger::AdvanceFromCustomer; // অগ্রিম নেওয়া থাকলে সেই দায় এখানে ক্লিয়ার হয়

        Voucher::createCompound(VoucherType::Sale, [
            ['ledger_id' => LedgerAccounts::systemLedger($drAuto), 'debit' => (float) $order['grand_total'], 'credit' => 0],
            [
                'ledger_id' => LedgerAccounts::systemLedger(AutoLedger::Sales),
                'debit'     => 0,
                'credit'    => round((float) $order['sub_total'] - (float) $order['discount_total'], 4),
            ],
            ['ledger_id' => LedgerAccounts::systemLedger(AutoLedger::ShippingIncome), 'debit' => 0, 'credit' => (float) $order['delivery_fee']],
        ], (int) $order['shipped_at'], 'Order ' . $order['code'], (string) $orderId);

        $totalCost = 0.0;

        foreach ($items as $item) {
            $totalCost += (float) $item['cost_price'] * (float) $item['qty'];
        }

        if ($totalCost > 0) {
            Voucher::create(
                VoucherType::CostOfGoodsSold,
                round($totalCost, 4),
                LedgerAccounts::systemLedger(AutoLedger::CostOfGoodsSold),
                LedgerAccounts::systemLedger(AutoLedger::Inventory),
                (int) $order['shipped_at'],
                'COGS ' . $order['code'],
                (string) $orderId
            );
        }

        foreach ($items as $item) {
            // move() নিজেই সাইন বসায় (StockChangeType::Sale->sign() === -1) — এখানে সবসময় ধনাত্মক qty
            StockService::move(
                (int) $item['product_id'],
                (int) $item['variant_id'],
                (float) $item['qty'],
                StockChangeType::Sale,
                (int) $item['id'],
                (int) $order['shipped_at']
            );
        }
    }

    /**
     * `Returned` — টাকা ও স্টক দুটোই ফেরত। doc §৮।
     *
     * @param array<string,mixed> $order
     */
    private static function postReturnVouchers(array $order): void
    {
        $orderId = (int) $order['id'];
        $items   = OrderItem::ofOrder($orderId);
        $now     = RequestTime::now();

        $drAuto = PaymentMethod::from((int) $order['payment_method']) === PaymentMethod::Cod
            ? AutoLedger::CodReceivable
            : AutoLedger::AdvanceFromCustomer;

        Voucher::create(
            VoucherType::SaleReturn,
            (float) $order['grand_total'],
            LedgerAccounts::systemLedger(AutoLedger::SalesReturn),
            LedgerAccounts::systemLedger($drAuto),
            $now,
            'Return ' . $order['code'],
            (string) $orderId
        );

        $totalCost = 0.0;

        foreach ($items as $item) {
            $totalCost += (float) $item['cost_price'] * (float) $item['qty'];
        }

        if ($totalCost > 0) {
            Voucher::create(
                VoucherType::SaleReturn,
                round($totalCost, 4),
                LedgerAccounts::systemLedger(AutoLedger::Inventory),
                LedgerAccounts::systemLedger(AutoLedger::CostOfGoodsSold),
                $now,
                'Return stock ' . $order['code'],
                (string) $orderId
            );
        }

        foreach ($items as $item) {
            StockService::move(
                (int) $item['product_id'],
                (int) $item['variant_id'],
                (float) $item['qty'],
                StockChangeType::SaleReturn,
                (int) $item['id'],
                $now
            );
        }
    }

    // -------------------------------------------------------------------------
    // পড়া
    // -------------------------------------------------------------------------

    /**
     * অ্যাডমিন লিস্টের জন্য।
     *
     * @param  array<string,mixed> $filters status?, customer_id?, payment_method?, from?, to?, code?, page?, per_page?
     * @return array{data:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public static function list(array $filters = []): array
    {
        $total = self::listQuery($filters)->count();

        $perPage = (int) ($filters['per_page'] ?? 25);
        $page    = max(1, (int) ($filters['page'] ?? 1));

        $query = self::listQuery($filters)->orderBy('placed_at', 'DESC');

        if ($perPage > 0) {
            $query->page($page, min($perPage, 200));
        }

        return ['data' => $query->get(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * অ্যাডমিনের অনুমোদন-queue — শুধু `Pending`, পুরোনোটা আগে (আগে অর্ডার করা
     * কাস্টমারকে আগে কল করতে)। ছোট তালিকা বলে পেজিনেশন ছাড়াই সব একসাথে। §৫।
     *
     * @return array<int,array<string,mixed>>
     */
    public static function pendingQueue(): array
    {
        return self::listQuery(['status' => OrderStatus::Pending->value])
            ->orderBy('placed_at')
            ->get();
    }

    /** @param array<string,mixed> $filters */
    private static function listQuery(array $filters): QueryBuilder
    {
        $query = Order::query();

        if (!empty($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', (int) $filters['payment_method']);
        }

        if (!empty($filters['code'])) {
            $query->whereLike('code', '%' . $filters['code'] . '%');
        }

        if (!empty($filters['from'])) {
            $query->where('placed_at', strtotime((string) $filters['from']), '>=');
        }

        if (!empty($filters['to'])) {
            $query->where('placed_at', strtotime((string) $filters['to']) + 86399, '<=');
        }

        return $query;
    }

    /** @return array<string,mixed> */
    public static function details(int $orderId): array
    {
        $order = Order::find($orderId);

        if ($order === []) {
            return [];
        }

        return self::hydrate($order);
    }

    /** @return array<string,mixed> */
    public static function detailsByCodeAndPhone(string $code, string $phone): array
    {
        $order = Order::byCode($code);

        if ($order === [] || trim((string) $order['recipient_phone']) !== trim($phone)) {
            return [];
        }

        return self::hydrate($order);
    }

    /**
     * @param  array<string,mixed> $order
     * @return array<string,mixed>
     */
    private static function hydrate(array $order): array
    {
        $status = OrderStatus::from((int) $order['status']);

        $order['items']        = OrderItem::ofOrder((int) $order['id']);
        $order['status_log']   = OrderStatusLog::ofOrder((int) $order['id']);
        $order['payments']     = OrderPayment::ofOrder((int) $order['id']);
        $order['status_label'] = $status->label();
        $order['next_states']  = array_map(
            static fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            $status->nextStates()
        );
        $order['payment_method_label'] = PaymentMethod::from((int) $order['payment_method'])->label();

        return $order;
    }
}
