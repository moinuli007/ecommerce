<?php

namespace App\Core;

/**
 * অ্যাডমিন সাইডবার মেনু।
 *
 * `Auth::isSuperAdmin()` এর মাধ্যমে বর্তমান ইউজার দেখে কিছু আইটেম
 * (যেমন Admin Users) ফিল্টার হয় — admin() কল হয় শুধু guard: admin/
 * super_admin পাশ করা রিকোয়েস্টে (resources/views/layouts/admin.php),
 * তাই এখানে Auth::check() আলাদা করে যাচাই লাগে না।
 *
 * erp_saas এ মেনু `module` টেবিল থেকে আসে; এখানে কোডে (সিদ্ধান্ত D-02 এর ধারাবাহিকতা) —
 * রাউট যেহেতু কোডে, মেনুও কোডে থাকলে দুটো কখনো আলাদা হয়ে যাবে না।
 *
 * নতুন মডিউল যোগ করলে এখানে একটা গ্রুপ/আইটেম যোগ করবেন। `guard` দিয়ে কোন আইটেম
 * কে দেখবে সেটা ঠিক হয়; `permission` পরে DB-ভিত্তিক পারমিশন এলে ব্যবহার হবে।
 */
final class Menu
{
    /**
     * @return array<int,array{title:string,icon:string,items:array<int,array{label:string,path:string,icon?:string,match?:string}>}>
     */
    public static function admin(): array
    {
        $groups = [
            [
                'title' => 'Main',
                'icon'  => 'grid',
                'items' => [
                    ['label' => 'Dashboard', 'path' => '/admin', 'icon' => 'home', 'match' => 'exact'],
                ],
            ],
            [
                'title' => 'Catalog',
                'icon'  => 'tag',
                'items' => [
                    ['label' => 'Products',    'path' => '/admin/products',   'icon' => 'box'],
                    ['label' => 'Categories',  'path' => '/admin/categories', 'icon' => 'folder'],
                    ['label' => 'Size / Color', 'path' => '/admin/attributes', 'icon' => 'sliders'],
                    ['label' => 'Units',       'path' => '/admin/units',      'icon' => 'ruler'],
                ],
            ],
            [
                'title' => 'Purchase',
                'icon'  => 'trend-down',
                'items' => [
                    ['label' => 'Purchase Entry',     'path' => '/admin/purchases',          'icon' => 'in', 'match' => 'exact'],
                    ['label' => 'Purchase Return',    'path' => '/admin/stock/returns',      'icon' => 'out'],
                    ['label' => 'Stock Adjustment',   'path' => '/admin/stock/adjustments',  'icon' => 'sliders'],
                    ['label' => 'Suppliers',          'path' => '/admin/suppliers',          'icon' => 'user'],
                ],
            ],
            [
                'title' => 'Sale',
                'icon'  => 'truck',
                'items' => [
                    ['label' => 'Pending Orders', 'path' => '/admin/orders/pending', 'icon' => 'alert', 'match' => 'exact'],
                    ['label' => 'Orders',         'path' => '/admin/orders',         'icon' => 'list'],
                    ['label' => 'Customers',      'path' => '/admin/customers',      'icon' => 'user'],
                    ['label' => 'Delivery Zones', 'path' => '/admin/delivery-zones', 'icon' => 'truck'],
                ],
            ],
            [
                'title' => 'Accounts',
                'icon'  => 'book',
                'items' => [
                    ['label' => 'Voucher List',        'path' => '/admin/vouchers',       'icon' => 'list', 'match' => 'exact'],
                    ['label' => 'New Voucher',         'path' => '/admin/vouchers/entry', 'icon' => 'plus'],
                    ['label' => 'Chart of Accounts',   'path' => '/admin/ledgers',        'icon' => 'layers', 'match' => 'exact'],
                ],
            ],
            [
                'title' => 'Reports',
                'icon'  => 'bar-chart',
                'items' => [
                    ['label' => 'Trial Balance', 'path' => '/admin/reports/trial-balance', 'icon' => 'scale'],
                ],
            ],
            [
                'title' => 'Settings',
                'icon'  => 'settings',
                'items' => [
                    // শুধু Super Admin — route guard ও 'super_admin' (doc/13 §৪),
                    // এখানে না লুকালেও রাউট নিজেই আটকাবে, কিন্তু মেনুতে দেখানো
                    // ঠিক না যা ক্লিক করা যাবে না
                    ['label' => 'Admin Users', 'path' => '/admin/users', 'icon' => 'user', 'superAdminOnly' => true],
                ],
            ],

            // ফেজ ২–৫ এ এখানে যোগ হবে: ক্যাটালগ, অর্ডার, ইনভেনটরি, কাস্টমার, সেটিংস
        ];

        $isSuperAdmin = Auth::isSuperAdmin();

        foreach ($groups as &$group) {
            $group['items'] = array_values(array_filter(
                $group['items'],
                static fn (array $item) => !($item['superAdminOnly'] ?? false) || $isSuperAdmin
            ));
        }
        unset($group);

        return array_values(array_filter($groups, static fn (array $g) => $g['items'] !== []));
    }

    /**
     * বর্তমান URL এই মেনু আইটেমের সাথে মেলে কি না।
     *
     * `match: 'exact'` না দিলে প্রিফিক্স ম্যাচ হয় — যেমন `/admin/vouchers/12` ও
     * "ভাউচার লিস্ট" কে active দেখাবে।
     *
     * @param array<string,mixed> $item
     */
    public static function isActive(array $item, string $currentPath): bool
    {
        $path = (string) $item['path'];

        if (($item['match'] ?? '') === 'exact') {
            return $currentPath === $path;
        }

        return $currentPath === $path || str_starts_with($currentPath, $path . '/');
    }

    /**
     * ব্রেডক্রাম্বের জন্য — বর্তমান পাথের সবচেয়ে নির্দিষ্ট মিল।
     *
     * @return array{group:string,label:string}|null
     */
    public static function locate(string $currentPath): ?array
    {
        $best = null;

        foreach (self::admin() as $group) {
            foreach ($group['items'] as $item) {
                if (!self::isActive($item, $currentPath)) {
                    continue;
                }

                if ($best === null || strlen((string) $item['path']) > strlen($best['path'])) {
                    $best = [
                        'group' => $group['title'],
                        'label' => $item['label'],
                        'path'  => (string) $item['path'],
                    ];
                }
            }
        }

        if ($best === null) {
            return null;
        }

        return ['group' => $best['group'], 'label' => $best['label']];
    }

    /**
     * টপ-রাইট প্রোফাইল ড্রপডাউনের আইটেম।
     *
     * @return array<int,array{label:string,path:string,icon:string,danger?:bool}>
     */
    public static function profile(): array
    {
        // সেটিংস পেজ ফেজ ৭-এ আসবে; ডেড লিংক না রাখতে এখন বাদ
        return [
            ['label' => 'My Profile', 'path' => '/admin/profile', 'icon' => 'user'],
            ['label' => 'Logout',     'path' => '/admin/logout',  'icon' => 'power', 'danger' => true],
        ];
    }

    /**
     * ইনলাইন SVG আইকন — কোনো এক্সটার্নাল আইকন লাইব্রেরি লোড করা হয় না
     * (feather icons এর সাবসেট, একই ভিজ্যুয়াল ভাষা)।
     */
    public static function icon(string $name): string
    {
        $paths = [
            'home'      => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'grid'      => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
            'book'      => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            'list'      => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
            'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
            'layers'    => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
            'bar-chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            'scale'     => '<path d="M12 3v18"/><path d="M5 7h14"/><path d="M8 7l-4 7h8z"/><path d="M16 7l-4 7h8z"/>',
            'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'power'     => '<path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/>',
            'wallet'    => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
            'trend-up'  => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
            'trend-down' => '<polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/>',
            'in'        => '<path d="M21 12H9"/><polyline points="13 16 9 12 13 8"/><path d="M3 3v18"/>',
            'out'       => '<path d="M3 12h12"/><polyline points="11 8 15 12 11 16"/><path d="M21 3v18"/>',
            'tag'       => '<path d="M20.59 13.41 12 22l-9-9V3h10l7.59 7.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
            'box'       => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22" x2="12" y2="12"/>',
            'folder'    => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
            'sliders'   => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
            'ruler'     => '<path d="M16 2 22 8 8 22 2 16z"/><line x1="7" y1="11" x2="9" y2="13"/><line x1="10" y1="8" x2="12" y2="10"/><line x1="13" y1="5" x2="15" y2="7"/>',
            'truck'     => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
            'chevron'   => '<polyline points="6 9 12 15 18 9"/>',
            'menu'      => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
            'check'     => '<polyline points="20 6 9 17 4 12"/>',
            'alert'     => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        ];

        $body = $paths[$name] ?? $paths['grid'];

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
    }
}
