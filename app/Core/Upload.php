<?php

namespace App\Core;

use finfo;
use RuntimeException;

/**
 * ইমেজ আপলোড হেল্পার — $_FILES এন্ট্রি যাচাই করে `public/uploads/{folder}/`
 * এ সেভ করে, ওয়েব-রিলেটিভ পাথ ("/uploads/products/ab12….webp") ফেরত দেয়।
 *
 * ইউজারের দেওয়া ফাইলনেম/এক্সটেনশন কখনো বিশ্বাস করা হয় না — আসল MIME `finfo`
 * দিয়ে চেক হয়, ফাইলনেম র‍্যান্ডম জেনারেট হয় (doc/09-media-and-purchase-pricing.md §৬ M-03)।
 */
final class Upload
{
    private const MAX_BYTES = 2 * 1024 * 1024; // ২MB

    /** @var array<string,string> mime => extension */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @param array<string,mixed> $file $_FILES['xxx'] এর একটা এন্ট্রি
     */
    public static function save(array $file, string $folder): string
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Image size must not exceed 2MB.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Image upload failed.');
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        $ext  = self::ALLOWED[$mime] ?? null;

        if ($ext === null) {
            throw new RuntimeException('Only JPEG, PNG, or WebP images are allowed.');
        }

        $folder = trim($folder, '/');
        $dir    = self::baseDir() . '/' . $folder;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create the upload folder.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        if (!move_uploaded_file($tmpPath, $dir . '/' . $filename)) {
            throw new RuntimeException('Could not save the image.');
        }

        return '/uploads/' . $folder . '/' . $filename;
    }

    /**
     * "/uploads/…" পাথের ফাইল ডিলিট — না থাকলে বা পাথ uploads এর বাইরে হলে চুপচাপ।
     */
    public static function delete(string $path): void
    {
        $path = trim($path);

        if ($path === '' || !str_starts_with($path, '/uploads/')) {
            return;
        }

        $full = self::baseDir() . substr($path, strlen('/uploads'));
        $real = realpath($full);
        $base = self::baseDir();

        if ($real !== false && str_starts_with($real, $base) && is_file($real)) {
            @unlink($real);
        }
    }

    private static function baseDir(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads';
    }
}
