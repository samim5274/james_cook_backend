<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\PurchaseCart;
use App\Models\PurchaseOrder;

class PurchaseRegGenerator
{
    public static function generateOrderReg(int $userId): string
    {
        return DB::transaction(function () use ($userId) {

            // 1. Check existing active cart
           $lastReg = PurchaseCart::where('user_id', $userId)
                ->latest('id')
                ->value('reg');

            // 2. If exists, check if already ordered
            if ($lastReg) {
                $isOrdered = PurchaseOrder::where('user_id', $userId)
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
            return 'PSTK-0' . $userId . '-' . $nextSeq;
        });
    }
}
