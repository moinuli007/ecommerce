<?php

/**
 * API রাউট — সব `/api/v1` প্রিফিক্সের নিচে, রেসপন্স সবসময় JSON।
 *
 * guard:
 *   guest    — লগইন লাগে না
 *   auth     — যেকোনো লগইন করা ইউজার
 *   admin    — super_admin বা staff
 *   customer — শুধু কাস্টমার
 */

use App\Core\Router;
use App\Modules\Account\Api\LedgerApi;
use App\Modules\Account\Api\VoucherApi;
use App\Modules\Auth\Api\AuthApi;
use App\Modules\Auth\Api\UserApi;
use App\Modules\Catalog\Api\AttributeApi;
use App\Modules\Catalog\Api\CategoryApi;
use App\Modules\Catalog\Api\ProductApi;
use App\Modules\Catalog\Api\UnitApi;
use App\Modules\Dashboard\Api\DashboardApi;
use App\Modules\Purchase\Api\PurchaseApi;
use App\Modules\Purchase\Api\StockApi;
use App\Modules\Purchase\Api\SupplierApi;
use App\Modules\Sale\Api\CartApi;
use App\Modules\Sale\Api\CheckoutApi;
use App\Modules\Sale\Api\CustomerApi;
use App\Modules\Sale\Api\DeliveryZoneApi;
use App\Modules\Sale\Api\OrderApi;
use App\Modules\Sale\Api\PaymentApi;

Router::group(['prefix' => '/api/v1', 'json' => true], function (): void {

    // ---------------------------------------------------------------------
    // অথ (doc/11-customer-account.md §৭)
    // ---------------------------------------------------------------------
    Router::post('/auth/register', [AuthApi::class, 'register'], ['name' => 'api.auth.register']);
    Router::post('/auth/login',    [AuthApi::class, 'login'],    ['name' => 'api.auth.login']);
    Router::post('/auth/logout',   [AuthApi::class, 'logout'],   ['guard' => 'auth', 'name' => 'api.auth.logout']);
    Router::get('/auth/me',        [AuthApi::class, 'me'],       ['guard' => 'auth', 'name' => 'api.auth.me']);
    Router::put('/auth/profile',   [AuthApi::class, 'updateProfile'],  ['guard' => 'auth', 'name' => 'api.auth.profile']);
    Router::put('/auth/password',  [AuthApi::class, 'changePassword'], ['guard' => 'auth', 'name' => 'api.auth.password']);
    Router::delete('/auth/account', [AuthApi::class, 'deleteAccount'], ['guard' => 'customer', 'name' => 'api.auth.deleteAccount']);

    // ---------------------------------------------------------------------
    // স্টোরফ্রন্ট — ক্যাটালগ ব্রাউজিং (guard: guest, doc/10-storefront-order.md §৬)
    // ---------------------------------------------------------------------
    Router::get('/storefront/categories',            [CategoryApi::class, 'publicTree'],       ['name' => 'api.storefront.category.index']);
    Router::get('/storefront/categories/{slug}',      [CategoryApi::class, 'publicShow'],       ['name' => 'api.storefront.category.show']);
    Router::get('/storefront/products',               [ProductApi::class, 'publicIndex'],       ['name' => 'api.storefront.product.index']);
    Router::get('/storefront/products/{slug}',        [ProductApi::class, 'publicShowBySlug'],  ['name' => 'api.storefront.product.show']);
    Router::get('/storefront/delivery-zones',         [DeliveryZoneApi::class, 'publicIndex'],  ['name' => 'api.storefront.deliveryZone.index']);

    // ---------------------------------------------------------------------
    // স্টোরফ্রন্ট — কার্ট (guard: guest, doc/10-storefront-order.md §৪, §৬)
    // ---------------------------------------------------------------------
    Router::get('/storefront/cart',               [CartApi::class, 'show'],       ['name' => 'api.storefront.cart.show']);
    Router::post('/storefront/cart/items',         [CartApi::class, 'addItem'],    ['name' => 'api.storefront.cart.addItem']);
    Router::put('/storefront/cart/items/{id}',     [CartApi::class, 'updateItem'], ['name' => 'api.storefront.cart.updateItem']);
    Router::delete('/storefront/cart/items/{id}',  [CartApi::class, 'removeItem'], ['name' => 'api.storefront.cart.removeItem']);
    Router::delete('/storefront/cart',             [CartApi::class, 'clear'],      ['name' => 'api.storefront.cart.clear']);

    // ---------------------------------------------------------------------
    // স্টোরফ্রন্ট — চেকআউট ও অর্ডার ট্র্যাকিং (guard: guest, doc/10-storefront-order.md §৬)
    // ---------------------------------------------------------------------
    Router::post('/storefront/checkout',           [CheckoutApi::class, 'store'], ['name' => 'api.storefront.checkout']);
    Router::get('/storefront/orders/{code}',       [CheckoutApi::class, 'track'], ['name' => 'api.storefront.order.track']);

    // ---------------------------------------------------------------------
    // ভাউচার
    // ---------------------------------------------------------------------
    Router::group(['guard' => 'admin'], function (): void {
        // -----------------------------------------------------------------
        // ড্যাশবোর্ড
        // -----------------------------------------------------------------
        Router::get('/dashboard', [DashboardApi::class, 'index'], ['name' => 'api.dashboard']);

        // -----------------------------------------------------------------
        // নিজের পাসওয়ার্ড বদল — যেকোনো লগইন করা অ্যাডমিন (Super Admin/Admin)।
        // `/users/{id}/...` এর আগে বসানো, নাহলে {id} প্যাটার্ন "me" কেই
        // ম্যাচ করে ফেলত (স্থির পাথ আগে কনভেনশন, doc/13 §৪)
        // -----------------------------------------------------------------
        Router::put('/users/me/password', [UserApi::class, 'changeOwnPassword'], ['name' => 'api.user.selfPassword']);

        // -----------------------------------------------------------------
        // অ্যাডমিন ইউজার ম্যানেজমেন্ট — শুধু Super Admin (doc/13 §৪)
        // -----------------------------------------------------------------
        Router::group(['guard' => 'super_admin'], function (): void {
            Router::get('/users',              [UserApi::class, 'index'],         ['name' => 'api.user.index']);
            Router::get('/users/{id}',         [UserApi::class, 'show'],          ['name' => 'api.user.show']);
            Router::post('/users',             [UserApi::class, 'store'],         ['name' => 'api.user.store']);
            Router::put('/users/{id}',         [UserApi::class, 'update'],        ['name' => 'api.user.update']);
            Router::delete('/users/{id}',      [UserApi::class, 'destroy'],       ['name' => 'api.user.destroy']);
            Router::put('/users/{id}/password', [UserApi::class, 'resetPassword'], ['name' => 'api.user.resetPassword']);
        });

        Router::get('/vouchers',              [VoucherApi::class, 'index'],       ['name' => 'api.voucher.index']);
        Router::get('/vouchers/form-data',    [VoucherApi::class, 'formData'],    ['name' => 'api.voucher.form']);
        Router::get('/vouchers/code/{code}',  [VoucherApi::class, 'showByCode'],  ['name' => 'api.voucher.byCode']);
        Router::get('/vouchers/{id}',         [VoucherApi::class, 'show'],        ['name' => 'api.voucher.show']);
        Router::post('/vouchers',             [VoucherApi::class, 'store'],       ['name' => 'api.voucher.store']);
        Router::post('/vouchers/opening',     [VoucherApi::class, 'storeOpening'], ['name' => 'api.voucher.opening']);
        Router::put('/vouchers/{id}',         [VoucherApi::class, 'update'],      ['name' => 'api.voucher.update']);
        Router::delete('/vouchers/{id}',      [VoucherApi::class, 'destroy'],     ['name' => 'api.voucher.destroy']);

        // -----------------------------------------------------------------
        // চার্ট অব অ্যাকাউন্টস / লেজার
        // -----------------------------------------------------------------
        Router::get('/ledgers',                  [LedgerApi::class, 'index'],     ['name' => 'api.ledger.index']);
        Router::get('/ledgers/tree',             [LedgerApi::class, 'tree'],      ['name' => 'api.ledger.tree']);
        Router::get('/ledgers/options',          [LedgerApi::class, 'options'],   ['name' => 'api.ledger.options']);
        Router::post('/ledgers',                 [LedgerApi::class, 'store'],     ['name' => 'api.ledger.store']);
        Router::get('/ledgers/{id}/statement',   [LedgerApi::class, 'statement'], ['name' => 'api.ledger.statement']);
        Router::get('/ledgers/{id}/balance',     [LedgerApi::class, 'balance'],   ['name' => 'api.ledger.balance']);

        // -----------------------------------------------------------------
        // রিপোর্ট
        // -----------------------------------------------------------------
        Router::get('/reports/trial-balance', [LedgerApi::class, 'trialBalance'], ['name' => 'api.report.trialBalance']);

        // =================================================================
        //  ক্যাটালগ
        // =================================================================

        // ক্যাটাগরি
        Router::get('/categories',         [CategoryApi::class, 'index'],   ['name' => 'api.category.index']);
        Router::get('/categories/{id}',    [CategoryApi::class, 'show'],    ['name' => 'api.category.show']);
        Router::post('/categories',        [CategoryApi::class, 'store'],   ['name' => 'api.category.store']);
        Router::put('/categories/{id}',    [CategoryApi::class, 'update'],  ['name' => 'api.category.update']);
        Router::delete('/categories/{id}', [CategoryApi::class, 'destroy'], ['name' => 'api.category.destroy']);
        Router::post('/categories/{id}/image',   [CategoryApi::class, 'uploadImage'], ['name' => 'api.category.image.store']);
        Router::delete('/categories/{id}/image', [CategoryApi::class, 'removeImage'], ['name' => 'api.category.image.destroy']);

        // ইউনিট
        Router::get('/units',              [UnitApi::class, 'index'],        ['name' => 'api.unit.index']);
        Router::get('/units/convert',      [UnitApi::class, 'convert'],      ['name' => 'api.unit.convert']);
        Router::post('/units',             [UnitApi::class, 'store'],        ['name' => 'api.unit.store']);
        Router::delete('/units/{id}',      [UnitApi::class, 'destroy'],      ['name' => 'api.unit.destroy']);
        Router::post('/unit-groups',       [UnitApi::class, 'storeGroup'],   ['name' => 'api.unitGroup.store']);
        Router::delete('/unit-groups/{id}', [UnitApi::class, 'destroyGroup'], ['name' => 'api.unitGroup.destroy']);

        // অ্যাট্রিবিউট (Size, Color …)
        Router::get('/attributes',              [AttributeApi::class, 'index'],        ['name' => 'api.attribute.index']);
        Router::post('/attributes',             [AttributeApi::class, 'store'],        ['name' => 'api.attribute.store']);
        Router::delete('/attributes/{id}',      [AttributeApi::class, 'destroy'],      ['name' => 'api.attribute.destroy']);
        Router::post('/attribute-values',       [AttributeApi::class, 'storeValue'],   ['name' => 'api.attributeValue.store']);
        Router::delete('/attribute-values/{id}', [AttributeApi::class, 'destroyValue'], ['name' => 'api.attributeValue.destroy']);

        // =================================================================
        //  ক্রয় — সাপ্লায়ার
        // =================================================================
        Router::get('/suppliers',         [SupplierApi::class, 'index'],   ['name' => 'api.supplier.index']);
        Router::get('/suppliers/{id}',    [SupplierApi::class, 'show'],    ['name' => 'api.supplier.show']);
        Router::post('/suppliers',        [SupplierApi::class, 'store'],   ['name' => 'api.supplier.store']);
        Router::put('/suppliers/{id}',    [SupplierApi::class, 'update'],  ['name' => 'api.supplier.update']);
        Router::delete('/suppliers/{id}', [SupplierApi::class, 'destroy'], ['name' => 'api.supplier.destroy']);

        // ক্রয় — স্থির পাথ আগে, {id} পরে
        Router::get('/purchases',            [PurchaseApi::class, 'index'],    ['name' => 'api.purchase.index']);
        Router::get('/purchases/form-data',  [PurchaseApi::class, 'formData'], ['name' => 'api.purchase.form']);
        Router::get('/purchases/{id}',       [PurchaseApi::class, 'show'],     ['name' => 'api.purchase.show']);
        Router::post('/purchases',           [PurchaseApi::class, 'store'],    ['name' => 'api.purchase.store']);
        Router::put('/purchases/{id}',       [PurchaseApi::class, 'update'],   ['name' => 'api.purchase.update']);
        Router::delete('/purchases/{id}',    [PurchaseApi::class, 'destroy'],  ['name' => 'api.purchase.destroy']);

        // স্টক — খতিয়ান, সমন্বয়, ক্রয় ফেরত
        Router::get('/stock/ledger',              [StockApi::class, 'ledger'],            ['name' => 'api.stock.ledger']);
        Router::get('/stock/adjustments',         [StockApi::class, 'adjustments'],       ['name' => 'api.stock.adjustment.index']);
        Router::get('/stock/adjustments/{id}',    [StockApi::class, 'showAdjustment'],    ['name' => 'api.stock.adjustment.show']);
        Router::post('/stock/adjustments',        [StockApi::class, 'storeAdjustment'],   ['name' => 'api.stock.adjustment.store']);
        Router::put('/stock/adjustments/{id}',    [StockApi::class, 'updateAdjustment'],  ['name' => 'api.stock.adjustment.update']);
        Router::delete('/stock/adjustments/{id}', [StockApi::class, 'destroyAdjustment'], ['name' => 'api.stock.adjustment.destroy']);
        Router::get('/stock/returns',             [StockApi::class, 'returns'],           ['name' => 'api.stock.return.index']);
        Router::get('/stock/returns/{id}',        [StockApi::class, 'showReturn'],        ['name' => 'api.stock.return.show']);
        Router::post('/stock/returns',            [StockApi::class, 'storeReturn'],       ['name' => 'api.stock.return.store']);
        Router::delete('/stock/returns/{id}',     [StockApi::class, 'destroyReturn'],     ['name' => 'api.stock.return.destroy']);

        // প্রোডাক্ট — স্থির পাথ আগে, {id} পরে
        Router::get('/products',                 [ProductApi::class, 'index'],        ['name' => 'api.product.index']);
        Router::get('/products/form-data',       [ProductApi::class, 'formData'],     ['name' => 'api.product.form']);
        Router::get('/products/slug/{slug}',     [ProductApi::class, 'showBySlug'],   ['name' => 'api.product.bySlug']);
        Router::get('/products/{id}',            [ProductApi::class, 'show'],         ['name' => 'api.product.show']);
        Router::post('/products',                [ProductApi::class, 'store'],        ['name' => 'api.product.store']);
        Router::put('/products/{id}',            [ProductApi::class, 'update'],       ['name' => 'api.product.update']);
        Router::delete('/products/{id}',         [ProductApi::class, 'destroy'],      ['name' => 'api.product.destroy']);
        Router::post('/products/{id}/variants',  [ProductApi::class, 'syncVariants'], ['name' => 'api.product.variants']);
        Router::post('/products/{id}/images',    [ProductApi::class, 'addImage'],     ['name' => 'api.product.addImage']);
        Router::put('/variants/{id}',            [ProductApi::class, 'updateVariant'], ['name' => 'api.variant.update']);
        Router::put('/images/{id}',              [ProductApi::class, 'updateImage'],  ['name' => 'api.image.update']);
        Router::delete('/images/{id}',           [ProductApi::class, 'deleteImage'],  ['name' => 'api.image.destroy']);

        // =================================================================
        //  বিক্রয় — ডেলিভারি জোন (doc/10-storefront-order.md)
        // =================================================================
        Router::get('/delivery-zones',         [DeliveryZoneApi::class, 'index'],   ['name' => 'api.deliveryZone.index']);
        Router::get('/delivery-zones/{id}',    [DeliveryZoneApi::class, 'show'],    ['name' => 'api.deliveryZone.show']);
        Router::post('/delivery-zones',        [DeliveryZoneApi::class, 'store'],   ['name' => 'api.deliveryZone.store']);
        Router::put('/delivery-zones/{id}',    [DeliveryZoneApi::class, 'update'],  ['name' => 'api.deliveryZone.update']);
        Router::delete('/delivery-zones/{id}', [DeliveryZoneApi::class, 'destroy'], ['name' => 'api.deliveryZone.destroy']);

        Router::get('/customers',         [CustomerApi::class, 'index'],   ['name' => 'api.customer.index']);
        Router::get('/customers/{id}',    [CustomerApi::class, 'show'],    ['name' => 'api.customer.show']);
        Router::post('/customers',        [CustomerApi::class, 'store'],   ['name' => 'api.customer.store']);
        Router::put('/customers/{id}',    [CustomerApi::class, 'update'],  ['name' => 'api.customer.update']);
        Router::delete('/customers/{id}', [CustomerApi::class, 'destroy'], ['name' => 'api.customer.destroy']);

        // =================================================================
        //  বিক্রয় — অর্ডার (doc/10-storefront-order.md §৫, §৯)
        // =================================================================
        Router::get('/orders',                [OrderApi::class, 'index'],        ['name' => 'api.order.index']);
        Router::get('/orders/pending',        [OrderApi::class, 'pending'],      ['name' => 'api.order.pending']);
        Router::get('/orders/{id}',           [OrderApi::class, 'show'],         ['name' => 'api.order.show']);
        Router::post('/orders/{id}/approve',  [OrderApi::class, 'approve'],      ['name' => 'api.order.approve']);
        Router::post('/orders/{id}/reject',   [OrderApi::class, 'reject'],       ['name' => 'api.order.reject']);
        Router::put('/orders/{id}/status',    [OrderApi::class, 'updateStatus'], ['name' => 'api.order.status']);

        // পেমেন্ট — ম্যানুয়াল bKash/Nagad রেফারেন্স ভেরিফিকেশন (doc §৭)
        Router::get('/orders/{id}/payments',        [PaymentApi::class, 'forOrder'], ['name' => 'api.order.payments']);
        Router::post('/order-payments',             [PaymentApi::class, 'store'],    ['name' => 'api.orderPayment.store']);
        Router::put('/order-payments/{id}/verify',  [PaymentApi::class, 'verify'],   ['name' => 'api.orderPayment.verify']);
        Router::put('/order-payments/{id}/reject',  [PaymentApi::class, 'reject'],   ['name' => 'api.orderPayment.reject']);
    });
});
