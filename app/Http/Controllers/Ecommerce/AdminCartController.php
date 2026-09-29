<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

use App\Models\User;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Services\RegGenerator;
use App\Http\Requests\CheckOutRequest;
use App\Mail\OrderMail;
use App\Models\OrderPayment;
use App\Services\PointService;


class AdminCartController extends Controller
{
    protected $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function index()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user',
            ], 401);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Current Cart REG
            |--------------------------------------------------------------------------
            */

            $reg = RegGenerator::generateOrderReg($userId);

            if (!$reg) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to generate cart session.',
                ], 422);
            }


            /*
            |--------------------------------------------------------------------------
            | CART ITEMS
            |--------------------------------------------------------------------------
            */

            $items = Cart::query()
                ->with([
                    'product.images',
                    'stock',
                    'user',
                ])
                ->where('user_id', $userId)
                ->where('reg', $reg)
                ->orderBy('id', 'asc')
                ->get();


            /*
            |--------------------------------------------------------------------------
            | EMPTY CART
            |--------------------------------------------------------------------------
            */

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cart is empty.',
                    'reg' => $reg,
                    'data' => [],
                    'summary' => [
                        'total_items' => 0,
                        'total_quantity' => 0,
                        'subtotal' => 0,
                        'discount' => 0,
                        'total' => 0,
                    ],
                ], 200);
            }


            /*
            |--------------------------------------------------------------------------
            | CART SUMMARY
            |--------------------------------------------------------------------------
            */

            $totalQuantity = (int) $items->sum(
                fn ($item) => (int) $item->quantity
            );

            $subtotal = round(
                $items->sum(
                    fn ($item) =>
                        (float) $item->price * (int) $item->quantity
                ),
                2
            );

            $discount = round(
                $items->sum(
                    fn ($item) =>
                        (float) ($item->discount ?? 0)
                        * (int) $item->quantity
                ),
                2
            );

            $total = round(
                max(0, $subtotal - $discount),
                2
            );


            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Cart items fetched successfully.',

                'reg' => $reg,

                'data' => $items,

                'summary' => [
                    'total_items' => $items->count(),
                    'total_quantity' => $totalQuantity,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                ],
            ], 200);

        } catch (\Throwable $e) {

            Log::error('POS Cart Fetch Failed', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch cart items. Please try again.',
            ], 500);
        }
    }

    public function adminAddToCart(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {

            return $this->addProductToCartFIFO(
                (int) $data['product_id'],
                (int) ($data['quantity'] ?? 1),
                $user
            );

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('POS Add To Cart Failed', [
                'user_id' => $user->id,
                'product_id' => $data['product_id'] ?? null,
                'quantity' => $data['quantity'] ?? 1,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to add product to cart. Please try again.',
            ], 500);
        }
    }

    public function adminAddToCartSearch(Request $request)
    {
        $data = $request->validate([
            'product' => [
                'required',
                'string',
                'max:255',
            ],
            'quantity' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $search = trim($data['product']);

        if ($search === '') {
            return response()->json([
                'success' => false,
                'message' => 'Product search value is required.',
            ], 422);
        }

        try {

            $product = Product::query()
                ->where(function ($query) use ($search) {

                    if (ctype_digit($search)) {
                        $query->where('id', (int) $search)
                            ->orWhere('sku', $search)
                            ->orWhere('slug', $search);
                    } else {
                        $query->where('sku', $search)
                            ->orWhere('slug', $search);
                    }

                    $query->orWhere(
                        'name',
                        'LIKE',
                        '%' . addcslashes($search, '%_') . '%'
                    );
                })
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            if (!$product->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'This product is currently inactive.',
                ], 422);
            }

            // Reuse the same FIFO cart logic
            $quantity = (int) ($data['quantity'] ?? 1);

            return $this->addProductToCartFIFO(
                $product->id,
                $quantity,
                $user
            );

        } catch (\Throwable $e) {

            Log::error('POS Add To Cart Search Failed', [

                'user_id' => $user->id,

                'search' => $search,

                'quantity' => $data['quantity'] ?? 1,

                'message' => $e->getMessage(),

                'file' => $e->getFile(),

                'line' => $e->getLine(),

            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to add product to cart. Please try again.',
            ], 500);
        }
    }

    private function addProductToCartFIFO(
        int $productId,
        int $requestedQty,
        $user
    ) {
        return DB::transaction(function () use (
            $productId,
            $requestedQty,
            $user
        ) {

            // =====================================================
            // PRODUCT
            // =====================================================

            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Product not found.',
                ]);
            }

            if (!$product->is_active) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is currently inactive.',
                ]);
            }


            // =====================================================
            // CART REG
            // =====================================================

            $reg = RegGenerator::generateOrderReg($user->id);

            if (!$reg) {
                throw ValidationException::withMessages([
                    'reg' => 'Failed to generate cart session.',
                ]);
            }


            // =====================================================
            // EXISTING PRODUCT QTY IN CART
            // =====================================================

            $existingQty = (int) Cart::query()
                ->where('reg', $reg)
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->sum('quantity');


            $newTotalQty = $existingQty + $requestedQty;


            // =====================================================
            // LOCK STOCKS
            // =====================================================

            $stocks = Stock::query()
                ->where('product_id', $product->id)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query
                        ->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', today());
                })
                ->whereRaw('stockIn > stockOut')
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();


            if ($stocks->isEmpty()) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is out of stock.',
                ]);
            }


            // =====================================================
            // TOTAL AVAILABLE
            // =====================================================

            $totalAvailableStock = $stocks->sum(
                fn ($stock) => max(
                    0,
                    (int) $stock->stockIn
                    - (int) $stock->stockOut
                )
            );


            if ($newTotalQty > $totalAvailableStock) {

                throw ValidationException::withMessages([
                    'quantity' => [
                        "Only {$totalAvailableStock} item(s) available in stock."
                    ],
                ]);
            }


            // =====================================================
            // REBUILD PRODUCT CART FIFO
            // =====================================================

            Cart::query()
                ->where('reg', $reg)
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->delete();


            $remainingQty = $newTotalQty;

            $cartItems = [];


            foreach ($stocks as $stock) {

                if ($remainingQty <= 0) {
                    break;
                }


                $availableQty = max(
                    0,
                    (int) $stock->stockIn
                    - (int) $stock->stockOut
                );


                if ($availableQty <= 0) {
                    continue;
                }


                $allocateQty = min(
                    $remainingQty,
                    $availableQty
                );


                $salePrice = round(
                    (float) $stock->sale_price,
                    2
                );


                if ($salePrice < 0) {
                    throw ValidationException::withMessages([
                        'product_id' => 'Invalid stock sale price.',
                    ]);
                }


                $discountAmount = round(
                    max(
                        0,
                        (float) ($product->discount ?? 0)
                    ),
                    2
                );


                $finalPrice = round(
                    max(
                        0,
                        $salePrice - $discountAmount
                    ),
                    2
                );


                $cartItem = Cart::create([

                    'reg' => $reg,

                    'user_id' => $user->id,

                    'product_id' => $product->id,

                    'stock_id' => $stock->id,

                    'quantity' => $allocateQty,

                    'price' => $salePrice,

                    'discount' => $discountAmount,

                    'total_amount' => round(
                        $finalPrice * $allocateQty,
                        2
                    ),

                    'point' => $product->point,
                ]);


                $cartItems[] = $cartItem;

                $remainingQty -= $allocateQty;
            }


            if ($remainingQty > 0) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        'Unable to allocate quantity using FIFO stock.'
                    ],
                ]);
            }


            return response()->json([

                'success' => true,

                'message' => 'Product added to cart successfully.',

                'data' => [

                    'reg' => $reg,

                    'product_id' => $product->id,

                    'product_name' => $product->name,

                    'quantity' => $newTotalQty,

                    'available_stock' => $totalAvailableStock,

                    'remaining_stock' => max(
                        0,
                        $totalAvailableStock - $newTotalQty
                    ),

                    'items' => collect($cartItems)
                        ->map(function ($item) {

                            return [
                                'cart_id' => $item->id,
                                'product_id' => $item->product_id,
                                'stock_id' => $item->stock_id,
                                'quantity' => (int) $item->quantity,
                                'price' => (float) $item->price,
                                'discount' => (float) $item->discount,
                                'total' => (float) $item->total_amount,
                            ];

                        })
                        ->values(),
                ],

            ], 201);
        });
    }

    public function updateQty(Request $request, $reg, $product_id)
    {
        $data = $request->validate([
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
            ],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {

            return $this->rebuildCartProductFIFO(
                $reg,
                (int) $product_id,
                (int) $data['quantity'],
                $user
            );

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('Cart Qty Update Error', [
                'user_id' => $user->id,
                'reg' => $reg,
                'product_id' => $product_id,
                'quantity' => $data['quantity'],
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update cart quantity.',
            ], 500);
        }
    }

    private function rebuildCartProductFIFO(
        string $reg,
        int $productId,
        int $quantity,
        $user
    ) {
        return DB::transaction(function () use (
            $reg,
            $productId,
            $quantity,
            $user
        ) {

            // =====================================================
            // PRODUCT
            // =====================================================

            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw ValidationException::withMessages([
                    'product_id' => 'Product not found.',
                ]);
            }

            if (!$product->is_active) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is currently inactive.',
                ]);
            }


            // =====================================================
            // LOCK STOCKS
            // =====================================================

            $stocks = Stock::query()
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query
                        ->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', today());
                })
                ->whereRaw('stockIn > stockOut')
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();


            $totalAvailableStock = $stocks->sum(
                fn ($stock) => max(
                    0,
                    (int) $stock->stockIn
                    - (int) $stock->stockOut
                )
            );


            if ($quantity > $totalAvailableStock) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        "Only {$totalAvailableStock} item(s) available in stock."
                    ],
                ]);
            }


            // =====================================================
            // DELETE CURRENT ALLOCATION
            // =====================================================

            Cart::query()
                ->where('reg', $reg)
                ->where('user_id', $user->id)
                ->where('product_id', $productId)
                ->delete();


            // =====================================================
            // FIFO REBUILD
            // =====================================================

            $remainingQty = $quantity;

            $items = [];


            foreach ($stocks as $stock) {

                if ($remainingQty <= 0) {
                    break;
                }


                $availableQty = max(
                    0,
                    (int) $stock->stockIn
                    - (int) $stock->stockOut
                );


                if ($availableQty <= 0) {
                    continue;
                }


                $allocateQty = min(
                    $remainingQty,
                    $availableQty
                );


                $salePrice = round(
                    (float) $stock->sale_price,
                    2
                );


                $discountAmount = round(
                    max(
                        0,
                        (float) ($product->discount ?? 0)
                    ),
                    2
                );


                $finalPrice = round(
                    max(
                        0,
                        $salePrice - $discountAmount
                    ),
                    2
                );


                $items[] = Cart::create([

                    'reg' => $reg,

                    'user_id' => $user->id,

                    'product_id' => $productId,

                    'stock_id' => $stock->id,

                    'quantity' => $allocateQty,

                    'price' => $salePrice,

                    'discount' => $discountAmount,

                    'total_amount' => round(
                        $finalPrice * $allocateQty,
                        2
                    ),

                    'point' => $product->point,
                ]);


                $remainingQty -= $allocateQty;
            }


            if ($remainingQty > 0) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        'Unable to allocate FIFO stock.',
                    ],
                ]);
            }


            return response()->json([
                'success' => true,
                'message' => 'Cart quantity updated successfully.',
                'data' => [
                    'reg' => $reg,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'items' => collect($items)->map(function ($item) {
                        return [
                            'cart_id' => $item->id,
                            'stock_id' => $item->stock_id,
                            'quantity' => (int) $item->quantity,
                            'price' => (float) $item->price,
                            'discount' => (float) $item->discount,
                            'total' => (float) $item->total_amount,
                        ];
                    })->values(),
                ],
            ], 200);
        });
    }

    public function removeToCart(
        Request $request,
        $cart_id,
        $reg,
        $product_id
    ) {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {

            return DB::transaction(function () use (
                $user,
                $cart_id,
                $reg,
                $product_id
            ) {

                $cartItem = Cart::query()
                    ->where('id', $cart_id)
                    ->where('user_id', $user->id)
                    ->where('reg', $reg)
                    ->where('product_id', $product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$cartItem) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cart item not found.',
                    ], 404);
                }


                // =====================================================
                // Delete ONLY this cart allocation
                // =====================================================

                $cartItem->delete();


                // =====================================================
                // Remaining cart items
                // =====================================================

                $remaining = Cart::query()
                    ->where('user_id', $user->id)
                    ->where('reg', $reg)
                    ->count();


                return response()->json([
                    'success' => true,
                    'message' => 'Cart item removed successfully.',
                    'remaining_items' => $remaining,
                ], 200);
            });

        } catch (\Throwable $e) {

            Log::error('Cart Remove Error', [
                'user_id' => $user->id,
                'cart_id' => $cart_id,
                'reg' => $reg,
                'product_id' => $product_id,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to remove cart item.',
            ], 500);
        }
    }

    public function getCartItem($reg)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {

            $items = Cart::query()
                ->with([
                    'product.images',
                    'user',
                    'stock:id,product_id,batch_no,reg,sale_price,purchase_price,stockIn,stockOut,expiry_date,status',
                ])
                ->where('reg', $reg)
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->get();


            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No cart items found.',
                    'data' => [],
                ], 404);
            }


            return response()->json([
                'success' => true,
                'message' => 'Cart items fetched successfully.',
                'reg' => $reg,
                'data' => $items,
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Cart fetch error', [
                'user_id' => $user->id,
                'reg' => $reg,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching cart items.',
            ], 500);
        }
    }

    public function checkOut(CheckOutRequest $request, string $reg)
    {
        $validated = $request->validated();
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user',
            ], 401);
        }

        try {
            $result = DB::transaction(function () use (
                $validated,
                $user,
                $reg,
                $request
            ) {
                $cartItems = Cart::query()
                    ->where('reg', $reg)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => [
                            'Cart is empty or checkout has already been completed.'
                        ],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Customer
                |--------------------------------------------------------------------------
                */

                $customerPhone = isset($validated['phone_number'])
                    ? trim($validated['phone_number'])
                    : null;

                $customerName = isset($validated['customer_name'])
                    ? trim($validated['customer_name'])
                    : null;

                $customer = null;

                if ($customerPhone) {
                    $customer = Customer::query()
                        ->where('phone', $customerPhone)
                        ->lockForUpdate()
                        ->first();

                    if (!$customer) {
                        $customer = Customer::create([
                            'phone' => $customerPhone,
                            'customer_name' => $customerName ?: 'Walk-in Customer',
                        ]);
                    } elseif (
                        $customerName &&
                        blank($customer->customer_name)
                    ) {
                        $customer->update([
                            'customer_name' => $customerName,
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Payment Method
                |--------------------------------------------------------------------------
                */

                $paymentMethod = $validated['payment_method']
                    ?? OrderPayment::METHOD_CASH;

                if (!in_array(
                    $paymentMethod,
                    OrderPayment::PAYMENT_METHODS,
                    true
                )) {
                    throw ValidationException::withMessages([
                        'payment_method' => ['Invalid payment method.'],
                    ]);
                }

                $isWalletPayment = $paymentMethod === 'wallet';

                /*
                |--------------------------------------------------------------------------
                | Amount Calculation
                |--------------------------------------------------------------------------
                */

                $subtotal = round(
                    $cartItems->sum(
                        fn ($item) =>
                            (float) $item->price * (int) $item->quantity
                    ),
                    2
                );

                $cartDiscount = round(
                    $cartItems->sum(
                        fn ($item) =>
                            (float) ($item->discount ?? 0) *
                            (int) $item->quantity
                    ),
                    2
                );

                $manualDiscount = round(
                    max(0, (float) ($validated['discount'] ?? 0)),
                    2
                );

                $discount = round(
                    min(
                        $subtotal,
                        $cartDiscount + $manualDiscount
                    ),
                    2
                );

                $discountedSubtotal = round(
                    max(0, $subtotal - $discount),
                    2
                );

                $vatPercentage = round(
                    min(
                        100,
                        max(0, (float) ($validated['vat'] ?? 0))
                    ),
                    2
                );

                $vat = round(
                    ($discountedSubtotal * $vatPercentage) / 100,
                    2
                );

                $payableAmount = round(
                    max(0, $discountedSubtotal + $vat),
                    2
                );

                /*
                |--------------------------------------------------------------------------
                | Point Calculation
                |--------------------------------------------------------------------------
                */

                $point = (int) $cartItems->sum(
                    fn ($item) =>
                        (int) ($item->point ?? 0) *
                        (int) $item->quantity
                );

                /*
                |--------------------------------------------------------------------------
                | Wallet Payment
                |--------------------------------------------------------------------------
                */

                if ($isWalletPayment) {

                    if (!$customer) {
                        throw ValidationException::withMessages([
                            'customer' => [
                                'Customer is required for wallet payment.'
                            ],
                        ]);
                    }

                    if ($point <= 0) {
                        throw ValidationException::withMessages([
                            'point' => [
                                'This sale has no required points.'
                            ],
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Lock customer before checking point balance
                    |--------------------------------------------------------------------------
                    */

                    $customer = Customer::query()
                        ->whereKey($customer->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $availablePoints = $this->pointService
                        ->getBalance($customer->id);

                    if ($availablePoints < $point) {
                        throw ValidationException::withMessages([
                            'point' => [
                                "Insufficient points. Available: {$availablePoints}, Required: {$point}."
                            ],
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Wallet never accepts cash
                    |--------------------------------------------------------------------------
                    */

                    $receivedAmount = 0;
                    $paidAmount = $payableAmount;
                    $dueAmount = 0;
                    $changeAmount = 0;

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Normal Payment
                    |--------------------------------------------------------------------------
                    */

                    $receivedAmount = round(
                        max(
                            0,
                            (float) ($validated['received_amount'] ?? 0)
                        ),
                        2
                    );

                    $paidAmount = round(
                        min(
                            $receivedAmount,
                            $payableAmount
                        ),
                        2
                    );

                    $dueAmount = round(
                        max(
                            0,
                            $payableAmount - $paidAmount
                        ),
                        2
                    );

                    $changeAmount = round(
                        max(
                            0,
                            $receivedAmount - $payableAmount
                        ),
                        2
                    );

                    if ($payableAmount > 0 && $paidAmount <= 0) {
                        throw ValidationException::withMessages([
                            'received_amount' => [
                                'Payment amount must be greater than zero.'
                            ],
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Partial Payment
                    |--------------------------------------------------------------------------
                    */

                    $isPartiallyPaid = $paidAmount < $payableAmount;

                    if ($isPartiallyPaid) {

                        if (!$customerPhone) {
                            throw ValidationException::withMessages([
                                'phone_number' => [
                                    'Customer phone number is required for partial payments.'
                                ],
                            ]);
                        }

                        if (!$customerName) {
                            throw ValidationException::withMessages([
                                'customer_name' => [
                                    'Customer name is required for partial payments.'
                                ],
                            ]);
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Order Status
                |--------------------------------------------------------------------------
                */

                if ($payableAmount <= 0) {
                    $orderStatus = Order::STATUS_COMPLETED;
                } elseif ($paidAmount >= $payableAmount) {
                    $orderStatus = Order::STATUS_COMPLETED;
                } elseif ($paidAmount > 0) {
                    $orderStatus = Order::STATUS_PARTIALLY_PAID;
                } else {
                    $orderStatus = Order::STATUS_UNPAID;
                }

                /*
                |--------------------------------------------------------------------------
                | Create Order
                |--------------------------------------------------------------------------
                */

                $order = Order::create([
                    'reg' => $reg,
                    'order_date' => now()->toDateString(),

                    'user_id' => $user->id,
                    'customer_id' => $customer?->id,

                    'customer_name' =>
                        $customerName
                        ?: $customer?->customer_name
                        ?: 'Walk-in Customer',

                    'customer_phone' => $customerPhone,

                    'subtotal' => $subtotal,
                    'discount' => $discount,

                    'vat_percentage' => $vatPercentage,
                    'vat' => $vat,

                    'due_amount' => $dueAmount,
                    'payable_amount' => $payableAmount,

                    'payment_method' => $paymentMethod,
                    'currency' => Order::CURRENCY_BDT,

                    'point' => $point,

                    'status' => $orderStatus,

                    'completed_at' =>
                        $orderStatus === Order::STATUS_COMPLETED
                            ? now()
                            : null,

                    'remarks' =>
                        $validated['remarks']
                        ?? "Order created by user: {$user->name}",

                    'paid_at' =>
                        $paidAmount > 0
                            ? now()
                            : null,

                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Payment
                |--------------------------------------------------------------------------
                */

                $payment = null;

                if ($paidAmount > 0) {
                    $payment = OrderPayment::create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'customer_id' => $customer?->id,

                        'received_by' => $user->id,

                        'payment_type' =>
                            OrderPayment::TYPE_PAYMENT,

                        'payment_method' => $paymentMethod,

                        'amount' => $paidAmount,

                        'currency' =>
                            OrderPayment::CURRENCY_BDT,

                        'paid_at' => now(),

                        'remarks' =>
                            $validated['remarks']
                            ?? 'Order payment',

                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Stock Validation + Sale
                |--------------------------------------------------------------------------
                */

                foreach ($cartItems as $cartItem) {

                    if (!$cartItem->stock_id) {
                        throw ValidationException::withMessages([
                            'cart' => [
                                "Stock allocation missing for product ID {$cartItem->product_id}."
                            ],
                        ]);
                    }

                    $stock = Stock::query()
                        ->whereKey($cartItem->stock_id)
                        ->where('product_id', $cartItem->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock) {
                        throw ValidationException::withMessages([
                            'cart' => [
                                "Stock not found for product ID {$cartItem->product_id}."
                            ],
                        ]);
                    }

                    if ($stock->status !== 'active') {
                        throw ValidationException::withMessages([
                            'cart' => [
                                "Stock batch {$stock->batch_no} is no longer active."
                            ],
                        ]);
                    }

                    if (
                        $stock->expiry_date &&
                        $stock->expiry_date->lt(today())
                    ) {
                        throw ValidationException::withMessages([
                            'cart' => [
                                "Stock batch {$stock->batch_no} has expired."
                            ],
                        ]);
                    }

                    $quantity = (int) $cartItem->quantity;

                    $availableQty = (int) $stock->stockIn - (int) $stock->stockOut;

                    if ( (int) $cartItem->quantity > $availableQty ) {
                        throw ValidationException::withMessages([
                            'cart' => [
                                "Insufficient stock for product ID {$cartItem->product_id}."
                            ],
                        ]);
                    }

                    // Lock product
                    $product = Product::query()
                        ->whereKey($cartItem->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw ValidationException::withMessages([
                            'cart' => [
                                "Product not found for product ID {$cartItem->product_id}."
                            ],
                        ]);
                    }

                    if ((int) $product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'cart' => [
                                "Insufficient product stock for product ID {$cartItem->product_id}."
                            ],
                        ]);
                    }

                    $stock->increment( 'stockOut', (int) $cartItem->quantity);

                    $product->decrement('stock_quantity', $quantity);

                    Cache::forget('public_products');
                }

                /*
                |--------------------------------------------------------------------------
                | Point Transaction
                |--------------------------------------------------------------------------
                */

                if ($isWalletPayment) {

                    $this->pointService->redeemPoint(
                        $customer,
                        $order,
                        $point
                    );

                } elseif ($customer && $point > 0) {

                    $this->pointService->salePoint(
                        $customer,
                        $order->reg,
                        $point
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Load Relations
                |--------------------------------------------------------------------------
                */

                $order->load([
                    'customer',
                    'payments',
                ]);

                return [
                    'order' => $order,
                    'payment' => $payment,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'change_amount' => $changeAmount,
                    'received_amount' => $receivedAmount,
                ];
            }, 3);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'data' => [
                    'order' => $result['order'],
                    'payment' => $result['payment'],
                    'change_amount' => $result['change_amount'],
                ],
            ], 201);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())
                    ->flatten()
                    ->first(),
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('Order confirmation failed', [
                'user_id' => $user?->id,
                'reg' => $reg,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->isProduction()
                    ? 'Something went wrong. Please try again.'
                    : $e->getMessage(),
            ], 500);
        }
    }

}
