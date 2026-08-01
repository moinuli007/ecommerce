<?php

/**
 * একমাত্র এন্ট্রি পয়েন্ট। nginx এর try_files সব রিকোয়েস্ট এখানেই পাঠায়।
 */

use App\Core\Router;

require_once __DIR__ . '/../bootstrap.php';

Router::dispatch();
