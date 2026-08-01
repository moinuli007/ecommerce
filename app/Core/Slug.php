<?php

namespace App\Core;

/**
 * URL slug তৈরি — ক্যাটাগরি ও প্রোডাক্টের জন্য।
 *
 * বাংলা নাম দিলে বাংলা অক্ষরই slug এ থাকবে (URL-এ UTF-8 বৈধ, ব্রাউজার নিজে
 * এনকোড করে নেয়)। শুধু স্পেস/বিরামচিহ্ন হাইফেন হয়ে যায়।
 */
final class Slug
{
    public static function make(string $text): string
    {
        $slug = trim($text);
        $slug = mb_strtolower($slug, 'UTF-8');

        // অক্ষর (\p{L}), সংখ্যা (\p{N}) আর **কম্বাইনিং মার্ক** (\p{M}) ছাড়া বাকি সব হাইফেন।
        //
        // \p{M} বাদ দিলে বাংলা ভেঙে যায় — কার/মাত্রা/হসন্ত (ু ে া ্) অক্ষর নয়,
        // Unicode এ আলাদা "mark" ক্যাটাগরি। ফলে "পুরুষদের" হয়ে যেত "প-র-ষদ-র"।
        $slug = preg_replace('/[^\p{L}\p{N}\p{M}]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? 'item' : $slug;
    }

    /**
     * টেবিলে ইউনিক slug — একই নাম থাকলে `-2`, `-3` … বসে।
     *
     * @param int $ignoreId এডিটের সময় নিজের সারিটা বাদ দিতে
     */
    public static function unique(string $text, string $table, int $ignoreId = 0, string $column = 'slug'): string
    {
        $base = self::make($text);
        $slug = $base;
        $n    = 1;

        while (self::taken($slug, $table, $ignoreId, $column)) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    private static function taken(string $slug, string $table, int $ignoreId, string $column): bool
    {
        $row = DB::selectOne(
            "SELECT id FROM `$table` WHERE `$column` = ? AND id <> ? LIMIT 1",
            [$slug, $ignoreId]
        );

        return $row !== [];
    }
}
