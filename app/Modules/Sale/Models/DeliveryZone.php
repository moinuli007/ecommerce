<?php

namespace App\Modules\Sale\Models;

use App\Core\Model;

/**
 * ডেলিভারি জোন — ঢাকার ভিতরে/বাইরে আলাদা চার্জ। ডেটা-চালিত (হার্ডকোড না),
 * নতুন জোন অ্যাডমিন থেকেই যোগ করা যায়।
 *
 * doc/10-storefront-order.md §২।
 */
final class DeliveryZone extends Model
{
    protected static string $table = 'delivery_zones';
}
