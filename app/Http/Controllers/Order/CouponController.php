<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Carbon\Carbon;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;

class CouponController extends Controller
{
    public function checkCoupon(Request $request)
    {
        $request->validate([
            'coupon' => 'required|string',
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $user = auth()->user();

        $coupon = Coupon::where('code', strtoupper(trim($request->coupon)))
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code.'
            ], 422);
        }

        // Active
        if (!$coupon->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon is inactive.'
            ], 422);
        }

        // Start Date
        if ($coupon->start_date && now()->lt($coupon->start_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon is not available yet.'
            ], 422);
        }

        // Expired
        if ($coupon->end_date && now()->gt($coupon->end_date)) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon has expired.'
            ], 422);
        }

        // Minimum Order
        if ($request->subtotal < $coupon->minimum_order_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount is Tk. ' . number_format($coupon->minimum_order_amount, 2)
            ], 422);
        }

        // Total Usage Limit
        if (
            !is_null($coupon->usage_limit) &&
            $coupon->used_count >= $coupon->usage_limit
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon usage limit exceeded.'
            ], 422);
        }

        // Per User Usage
        $userUsed = Order::where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->count();

        if ($userUsed >= $coupon->usage_limit_per_user) {
            return response()->json([
                'success' => false,
                'message' => 'You have already used this coupon.'
            ], 422);
        }

        // Discount Calculate
        if ($coupon->discount_type == 'percent') {

            $discount = ($request->subtotal * $coupon->discount) / 100;

            if (
                $coupon->maximum_discount_amount &&
                $discount > $coupon->maximum_discount_amount
            ) {
                $discount = $coupon->maximum_discount_amount;
            }
        } else {
            $discount = $coupon->discount;
        }

        // Never exceed subtotal
        $discount = min($discount, $request->subtotal);

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied successfully.',
            'data' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'discount_type' => $coupon->discount_type,
                'discount' => $coupon->discount,
                'discount_amount' => round($discount, 2),
                'subtotal' => $request->subtotal,
                'payable' => round($request->subtotal - $discount, 2),
            ]
        ]);
    }
}
