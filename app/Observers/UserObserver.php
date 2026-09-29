<?php

namespace App\Observers;

use App\Models\User;
use App\Models\PointTransaction;
use Carbon\Carbon;

class UserObserver
{
    public function updating(User $user)
    {
        $total = $user->total_calculation;

        /*
        |--------------------------------------------------------------------------
        | 1. Rank Condition
        |--------------------------------------------------------------------------
        */

        // 1. Rank Condition
        $left  = $user->left_total_point ?? 0;
        $right = $user->right_total_point ?? 0;

        $newRank = "Bronze";
        $cashBonus = 0;

        if ($left >= 50000000 && $right >= 50000000) {
            $newRank = "PROJECT DIRECTOR (PD)";
            $cashBonus = 2500000; // 2.5-Core (25 Lakh)
        } elseif ($left >= 20000000 && $right >= 20000000) {
            $newRank = "EXECUTIVE DIRECTOR (ED)";
            $cashBonus = 10000000; // 1-Core (1 Crore)
        } elseif ($left >= 10000000 && $right >= 10000000) {
            $newRank = "GENERAL MANAGER (GM)";
            $cashBonus = 5000000; // 50 Lakh
        } elseif ($left >= 5000000 && $right >= 5000000) {
            $newRank = "ASSISTANT GENERAL MANAGER (AGM)";
            $cashBonus = 2500000; // 25 Lakh
        } elseif ($left >= 2000000 && $right >= 2000000) {
            $newRank = "ZONAL MANAGER (ZM)";
            $cashBonus = 1000000; // 10 Lakh
        } elseif ($left >= 1000000 && $right >= 1000000) {
            $newRank = "REGIONAL MANAGER (RM)";
            $cashBonus = 500000;  // 5 Lakh
        } elseif ($left >= 500000 && $right >= 500000) {
            $newRank = "EXECUTIVE MANAGER (EM)";
            $cashBonus = 250000;  // 2.5 Lakh
        } elseif ($left >= 200000 && $right >= 200000) {
            $newRank = "EXECUTIVE OFFICER (EO)";
            $cashBonus = 100000;  // 1 Lakh
        } elseif ($left >= 100000 && $right >= 100000) {
            $newRank = "MARKETING MANAGER (MM)";
            $cashBonus = 50000;   // 50,000 Tk
        } elseif ($left >= 50000 && $right >= 50000) {
            $newRank = "SALES MANAGER (SM)";
            $cashBonus = 25000;   // 25,000 Tk
        } elseif ($left >= 20000 && $right >= 20000) {
            $newRank = "MARKETING OFFICER (MO)";
            $cashBonus = 10000;   // 10,000 Tk
        } elseif ($left >= 10000 && $right >= 10000) {
            $newRank = "SALES OFFICER (SO)";
            $cashBonus = 5000;    // 5000 Tk
        }

        if ($user->rank !== $newRank) {

            $oldRank = $user->rank;
            $user->rank = $newRank;

            if ($cashBonus > 0 && $newRank !== "Bronze") {

                $bonusExists = PointTransaction::where('user_id', $user->id)
                    ->where('source', 'rank_bonus')
                    ->where('rank', $newRank)
                    ->where('note', 'like', "RANK_BONUS|{$newRank}|%")
                    ->exists();

                if (!$bonusExists) {

                    PointTransaction::create([
                        'user_id'        => $user->id,
                        'type'           => 'bonus',
                        'points'         => 0,
                        'bonus_amount'   => $cashBonus,
                        'bonus_status'   => 'credit',
                        'source'         => 'rank_bonus',
                        'reference_id'   => null,
                        'rank'           => $newRank,
                        'note'           => "RANK_BONUS|{$newRank}|{$cashBonus}",
                    ]);

                    $user->increment('wallet_balance', $cashBonus);
                }
            }

            $user->save();
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Match Condition
        |--------------------------------------------------------------------------
        */
        $user->is_match = (
            $user->left_child_id &&
            $user->right_child_id
        ) ? true : false;

    }
}
