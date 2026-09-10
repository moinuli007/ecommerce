<?php

namespace App\Modules\Sale\Api;

use App\Core\Request;
use App\Modules\Sale\Services\CartService;

/**
 * `cart_token` কুকি (httpOnly) / `X-Cart-Token` হেডার পড়া ও বসানো — `CartApi`
 * আর `CheckoutApi` দুটোতেই লাগে, তাই আলাদা করা। doc/10-storefront-order.md §৪।
 */
final class CartTokenResolver
{
    private const COOKIE_NAME = 'cart_token';
    private const COOKIE_DAYS = 30;

    /** কুকি/হেডার থেকে টোকেন পড়ে, কার্ট (থাকলে/না থাকলে বানিয়ে) নিশ্চিত করে, কুকি বসায়। */
    public static function resolve(): string
    {
        $incoming = trim((string) ($_COOKIE[self::COOKIE_NAME] ?? ''));

        if ($incoming === '') {
            $incoming = trim(Request::header('X-Cart-Token'));
        }

        $cart  = CartService::resolveCart($incoming);
        $token = (string) $cart['token'];

        self::ensureCookie($token);

        return $token;
    }

    private static function ensureCookie(string $token): void
    {
        if (($_COOKIE[self::COOKIE_NAME] ?? '') === $token || headers_sent()) {
            return;
        }

        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + self::COOKIE_DAYS * 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);

        $_COOKIE[self::COOKIE_NAME] = $token;
    }
}
