<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Services\RegGenerator;

class CartController extends Controller
{
    public function index(){

        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized user',
            ], 401);
        }

        $reg = RegGenerator::generateOrderReg($userId);

        $items = Cart::with(['product.images','variant','user'])
            ->where('user_id', $userId)
            ->where('reg', $reg)
            ->get();

        return response()->json([
            'message' => 'Cart items',
            'reg' => $reg,
            'data' => $items
        ], 200);
    }

    public function addToCart(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $reg = RegGenerator::generateOrderReg($user->id);
        if (!$reg) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate cart session.'.$user->id
            ], 500);
        }

        try{
            return DB::transaction(function () use ($data, $user, $reg) {

                $product = Product::lockForUpdate()->findOrFail($data['product_id']);

                // ======================
                // Variant handling
                // ======================
                $variant = null;

                if (!empty($data['variant_id'])) {
                    $variant = ProductVariant::lockForUpdate()
                        ->where('product_id', $product->id)
                        ->findOrFail($data['variant_id']);
                }

                // ======================
                // Stock source
                // ======================
                $source = $variant ?: $product;
                // $availableStock = $source->stock_quantity ?? 0;

                // ======================
                // Price
                // ======================
                // if ($variant) {
                //     $basePrice = $variant->price ?? $product->price;
                //     $variantDiscount = $variant->discount ?? $product->discount ?? 0;
                //     $finalPrice = $variantDiscount > 0
                //         ? $basePrice - $variantDiscount
                //         : $basePrice;
                //     $discountAmount = $variantDiscount > 0
                //         ? $variantDiscount
                //         : 0;
                // } else {
                //     $basePrice = $product->price;
                //     $finalPrice = $product->price;
                //     $discountAmount = 0;
                // }

                if ($variant) {
                    $basePrice = (float) $variant->price;
                    $discountAmount = (float) ($variant->discount ?? 0);
                } else {
                    $basePrice = (float) $product->price;
                    $discountAmount = (float) ($product->discount ?? 0);
                }
                $finalPrice = max(0, $basePrice - $discountAmount);

                // ======================
                // Cart item find
                // ======================
                $query = Cart::where('reg', $reg)
                    ->where('product_id', $product->id);

                if ($variant) {
                    $query->where('variant_id', $variant->id);
                } else {
                    $query->whereNull('variant_id');
                }

                $cartItem = $query->first();

                // ======================
                // Quantity logic
                // ======================
                $requestedQty = (int) $data['quantity'];
                $currentQty = $cartItem->quantity ?? 0;
                $newQty = $currentQty + $requestedQty;

                // ======================
                // Stock validation
                // ======================
                // if ($newQty > $availableStock) {
                //     throw new \Exception('Requested quantity exceeds available stock.', 409);
                // }

                // ======================
                // Save cart
                // ======================
                if ($cartItem) {
                    $cartItem->update([
                        'quantity'          => $newQty,
                        'price'             => $basePrice,
                        'discount'          => $discountAmount,
                        'payable_amount'    => $finalPrice,
                    ]);
                } else {
                    $cartItem = Cart::create([
                        'reg'               => $reg,
                        'user_id'           => $user->id,
                        'product_id'        => $product->id,
                        'variant_id'        => $variant?->id,
                        'quantity'          => $requestedQty,
                        'price'             => $basePrice,
                        'discount'          => $discountAmount,
                        'payable_amount'    => $finalPrice,
                        'point'             => $product->point,
                    ]);
                }

                // ======================
                // RESPONSE (OUTSIDE EXCEPTION FLOW STYLE)
                // ======================
                return response()->json([
                    'success' => true,
                    'message' => 'Product added to cart successfully.',
                    'data' => [
                        'cart_id'    => $cartItem->id,
                        'product_id' => $product->id,
                        'variant_id' => $variant?->id,
                        'quantity'   => $cartItem->quantity,
                        'price'      => (float) $finalPrice,
                        'total'      => (float) ($finalPrice * $cartItem->quantity)
                    ]
                ], 201);

            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    public function updateQty(Request $request, $reg, $product_id, $variant_id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $cartItem = Cart::where('reg', $reg)
            ->where('product_id', $product_id)
            ->when(
                !empty($variant_id) && $variant_id !== 'null',
                fn ($query) => $query->where('variant_id', $variant_id),
                fn ($query) => $query->whereNull('variant_id')
            )
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        $cartItem->update([
            'quantity' => $request->quantity,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Qty updated successfully',
            'quantity' => $cartItem->quantity,
        ]);
    }

    public function removeToCart(Request $request, $cart_id, $reg, $product_id, $variant_id){

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try{
            if (!$reg || !$product_id || !$variant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data'
                ], 422);
            }

            $cartItem = Cart::where('id', $cart_id)
                ->where('user_id', $user->id)
                ->where('reg', $reg)
                ->where('product_id', $product_id)
                ->when(
                    !empty($variant_id) && $variant_id !== 'null',
                    fn ($query) => $query->where('variant_id', $variant_id),
                    fn ($query) => $query->whereNull('variant_id'),
                )
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $cartItem->delete();

            $remaining = Cart::where('user_id', $user->id)
                ->where('reg', $reg)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart successfully',
                'remaining_items' => $remaining
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Cart Remove Error', [
                'user_id' => $user->id,
                'cart_id' => $cart_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    public function getCartItem($reg)
    {
        try {
            $items = Cart::with(['product.images','variant','user'])
                        ->where('reg', $reg)->get();

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No cart items found.',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cart items fetched successfully.',
                'reg' => $reg,
                'data' => $items
            ], 200);

        } catch (\Throwable $e) {

            \Log::error('Cart fetch error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching cart items.',
            ], 500);
        }
    }

}
