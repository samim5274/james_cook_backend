<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Cart;
use App\Models\Order;

class RegGenerator
{
    public static function generateOrderReg(int $userId): string
    {
        // return DB::transaction(function () use ($userId) {

        //     $prefix = now()->format('Ymd') . $userId;

        //     $lastReg = order::where('user_id', $userId)
        //         ->whereDate('created_at', today())
        //         ->lockForUpdate()
        //         ->latest('id')
        //         ->value('reg');

        //     if ($lastReg && str_starts_with($lastReg, $prefix)) {
        //         $lastSeq = (int) substr($lastReg, -3);
        //         $nextSeq = str_pad($lastSeq + 1, 3, '0', STR_PAD_LEFT);
        //         return $prefix . $nextSeq;
        //     }

        //     return $prefix . '001';
        // });


        return DB::transaction(function () use ($userId) {

            // 1. Check existing active cart
           $lastReg = Cart::where('user_id', $userId)
                ->latest('id')
                ->value('reg');

            // 2. If exists, check if already ordered
            if ($lastReg) {
                $isOrdered = Order::where('user_id', $userId)
                    ->where('reg', $lastReg)
                    ->exists();

                if (!$isOrdered) {
                    return $lastReg; // active cart
                }

                // extract last sequence
                $lastSeq = (int) substr($lastReg, -3);
                $nextSeq = str_pad($lastSeq + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $nextSeq = '001';
            }

            // 3. Generate new reg
            return 'ORD-0' . $userId . '-' . $nextSeq;
        });
    }
}
