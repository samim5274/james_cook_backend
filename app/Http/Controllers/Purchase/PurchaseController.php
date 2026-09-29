<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

use App\Models\Product;
use App\Models\Stock;
use App\Models\PurchaseCart;
use App\Models\PurchaseOrder;
use App\Models\Supplyer;
use App\Models\PurchaseOrderPayment;
use App\Services\PurchaseRegGenerator;
use App\Http\Requests\CheckOutPurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseCartRequest;

class PurchaseController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized user',
            ], 401);
        }

        $reg = PurchaseRegGenerator::generateOrderReg($userId);

        $items = PurchaseCart::with(['product.images','user'])
            ->where('user_id', $userId)
            ->where('reg', $reg)
            ->get();

        $supplyers = Supplyer::select([
            'id',
            'name',
            'company_name',
            'phone',
            'code',
        ])->orderBy('name')->get();

        return response()->json([
            'message' => 'Cart items',
            'reg' => $reg,
            'data' => [
                'items' => $items,
                'supplyers' => $supplyers,
            ],
        ], 200);
    }

    public function adminAddToCart(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity'   => ['nullable', 'integer', 'min:1'],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $requestedQty = (int) ($data['quantity'] ?? 1);

        try {
            return DB::transaction(function () use ($data, $user, $requestedQty) {

                // Lock product row
                $product = Product::query()
                    ->lockForUpdate()
                    ->find($data['product_id']);

                if (!$product) {
                    throw ValidationException::withMessages([
                        'product_id' => ['Product not found.'],
                    ]);
                }

                if (!$product->is_active) {
                    throw ValidationException::withMessages([
                        'product_id' => ['This product is currently inactive.'],
                    ]);
                }

                // Generate current purchase cart registration
                $reg = PurchaseRegGenerator::generateOrderReg($user->id);

                if (!$reg) {
                    throw ValidationException::withMessages([
                        'reg' => ['Failed to generate cart session.'],
                    ]);
                }

                $basePrice = round(max(0, (float) $product->purchase_price), 2);
                $salePrice = round(max(0, (float) $product->price), 2);

                // Find user's cart item and lock it
                $cartItem = PurchaseCart::query()
                    ->where('user_id', $user->id)
                    ->where('reg', $reg)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                $currentQty = (int) ($cartItem?->quantity ?? 0);
                $newQty = $currentQty + $requestedQty;

                $totalAmount = round($basePrice * $newQty, 2);

                if ($cartItem) {

                    $cartItem->update([
                        'quantity'     => $newQty,
                        'price'        => $basePrice,
                        'sale_price'   => $salePrice,
                        'total_amount' => $totalAmount,
                    ]);

                } else {

                    $cartItem = PurchaseCart::create([
                        'reg'          => $reg,
                        'user_id'      => $user->id,
                        'product_id'   => $product->id,
                        'quantity'     => $requestedQty,
                        'price'        => $basePrice,
                        'sale_price'   => $salePrice,
                        'total_amount' => round($basePrice * $requestedQty, 2),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Product added to cart successfully.',
                    'data' => [
                        'cart_id'    => $cartItem->id,
                        'product_id' => $product->id,
                        'quantity'   => (int) $cartItem->quantity,
                        'price'      => (float) $cartItem->price,
                        'total'      => (float) $cartItem->total_amount,
                    ],
                ], 201);
            });

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('Purchase Add To Cart Failed', [
                'user_id'    => $user->id,
                'product_id' => $data['product_id'] ?? null,
                'quantity'   => $requestedQty,
                'message'    => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
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
            'product'  => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $search = trim($data['product']);
        $requestedQty = (int) ($data['quantity'] ?? 1);

        try {
            return DB::transaction(function () use ($data, $user, $search, $requestedQty) {

                /*
                * Product search
                */
                $product = Product::query()
                    ->where(function ($query) use ($search) {

                        $query->where('sku', $search)
                            ->orWhere('slug', $search)
                            ->orWhere('sku', 'LIKE', "{$search}%")
                            ->orWhere('slug', 'LIKE', "{$search}%")
                            ->orWhere('name', 'LIKE', "%{$search}%");

                        if (ctype_digit($search)) {
                            $query->orWhere('id', (int) $search);
                        }
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw ValidationException::withMessages([
                        'product' => ['Product not found.'],
                    ]);
                }

                if (!$product->is_active) {
                    throw ValidationException::withMessages([
                        'product' => ['This product is currently inactive.'],
                    ]);
                }

                /*
                * Generate current purchase cart registration
                */
                $reg = PurchaseRegGenerator::generateOrderReg($user->id);

                if (!$reg) {
                    throw ValidationException::withMessages([
                        'reg' => ['Failed to generate cart session.'],
                    ]);
                }

                $basePrice = round(max(0, (float) $product->purchase_price), 2);
                $salePrice = round(max(0, (float) $product->price), 2);

                /*
                * Find user's cart item and lock it
                */
                $cartItem = PurchaseCart::query()
                    ->where('user_id', $user->id)
                    ->where('reg', $reg)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                $currentQty = (int) ($cartItem?->quantity ?? 0);
                $newQty = $currentQty + $requestedQty;

                $totalAmount = round($basePrice * $newQty, 2);

                /*
                * Update / create cart item
                */
                if ($cartItem) {

                    $cartItem->update([
                        'quantity'     => $newQty,
                        'price'        => $basePrice,
                        'sale_price'   => $salePrice,
                        'total_amount' => $totalAmount,
                    ]);

                } else {

                    $cartItem = PurchaseCart::create([
                        'reg'          => $reg,
                        'user_id'      => $user->id,
                        'product_id'   => $product->id,
                        'quantity'     => $requestedQty,
                        'price'        => $basePrice,
                        'sale_price'   => $salePrice,
                        'total_amount' => round($basePrice * $requestedQty, 2),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Product added to cart successfully.',
                    'data' => [
                        'cart_id'    => $cartItem->id,
                        'product_id' => $product->id,
                        'quantity'   => (int) $cartItem->quantity,
                        'price'      => (float) $cartItem->price,
                        'total'      => (float) $cartItem->total_amount,
                    ],
                ], 201);
            });

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('Purchase Add To Cart Search Failed', [
                'user_id'  => $user->id,
                'product'  => $data['product'] ?? null,
                'quantity' => $requestedQty,
                'message'  => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to add product to cart. Please try again.',
            ], 500);
        }
    }

    public function updateQty(Request $request, string $reg, int $product_id)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {
            return DB::transaction(function () use ($data, $reg, $product_id, $user) {

                $cartItem = PurchaseCart::query()
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

                $newQty = (int) $data['quantity'];

                $price = round((float) $cartItem->price, 2);

                $totalAmount = round($price * $newQty, 2);

                $cartItem->update([
                    'quantity'     => $newQty,
                    'total_amount' => $totalAmount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Quantity updated successfully.',
                    'data' => [
                        'cart_id'     => $cartItem->id,
                        'product_id'  => $cartItem->product_id,
                        'quantity'    => $newQty,
                        'price'       => (float) $price,
                        'total_amount'=> (float) $totalAmount,
                    ],
                ], 200);
            }, 3);

        } catch (\Throwable $e) {

            Log::error('Purchase cart quantity update failed', [
                'user_id'    => $user->id,
                'reg'        => $reg,
                'product_id' => $product_id,
                'quantity'   => $data['quantity'] ?? null,
                'message'    => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update cart quantity. Please try again.',
            ], 500);
        }
    }

    public function updateCartItem(UpdatePurchaseCartRequest $request, int $id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {
            $validated = $request->validated();

            $cartItem = DB::transaction(function () use ($validated, $id, $user) {

                $cartItem = PurchaseCart::query()
                    ->where('id', $id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (!$cartItem) {
                    throw ValidationException::withMessages([
                        'cart' => ['Cart item not found.'],
                    ]);
                }

                $price = round(
                    max(0, (float) $validated['price']),
                    2
                );

                $salePrice = round(
                    max(0, (float) $validated['sale_price']),
                    2
                );

                $quantity = (int) $validated['quantity'];

                $totalAmount = round(
                    $price * $quantity,
                    2
                );

                $cartItem->update([
                    'price'        => $price,
                    'quantity'     => $quantity,
                    'sale_price'   => $salePrice,
                    'total_amount' => $totalAmount,
                ]);

                return $cartItem
                    ->fresh()
                    ->load('product');
            }, 3);

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated successfully.',
                'data' => $cartItem,
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('Purchase cart item update failed', [
                'user_id'     => $user->id,
                'cart_item_id'=> $id,
                'message'     => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update cart item. Please try again.',
            ], 500);
        }
    }

    public function removeToCart(int $cart_id, string $reg, int $product_id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {

            return DB::transaction(function () use (
                $cart_id,
                $reg,
                $product_id,
                $user
            ) {

                $cartItem = PurchaseCart::query()
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

                $cartItem->delete();

                $remainingItems = PurchaseCart::query()
                    ->where('user_id', $user->id)
                    ->where('reg', $reg)
                    ->count();

                return response()->json([
                    'success' => true,
                    'message' => 'Item removed successfully.',
                    'remaining_items' => $remainingItems,
                ], 200);

            }, 3);

        } catch (\Throwable $e) {

            Log::error('Purchase cart remove failed', [
                'user_id'    => $user->id,
                'cart_id'    => $cart_id,
                'reg'        => $reg,
                'product_id' => $product_id,
                'message'    => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->isProduction()
                    ? 'Unable to remove cart item.'
                    : $e->getMessage(),
            ], 500);
        }
    }

    public function getCartItem($reg)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $items = PurchaseCart::with(['product.images','user'])->where('user_id', $user->id)
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

    public function confirmOrder(CheckOutPurchaseOrderRequest $request, string $reg)
    {
        $validated = $request->validated();
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        try {
            $result = DB::transaction(function () use ($validated, $user, $reg, $request) {

                $cartItems = PurchaseCart::query()
                    ->where('user_id', $user->id)
                    ->where('reg', $reg)
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => ['Cart is empty or checkout has already been completed.'],
                    ]);
                }

                $supplier = null;
                $supplierId = $validated['supplier_id'] ?? null;
                $supplierName = isset($validated['supplier_name']) ? trim($validated['supplier_name']) : null;
                $supplierPhone = isset($validated['supplier_phone']) ? trim($validated['supplier_phone']) : null;

                if ($supplierId) {
                    $supplier = Supplyer::query()
                        ->whereKey($supplierId)
                        ->lockForUpdate()
                        ->first();

                    if (!$supplier) {
                        throw ValidationException::withMessages([
                            'supplier_id' => ['Selected supplier does not exist.'],
                        ]);
                    }
                } elseif ($supplierPhone) {
                    $supplier = Supplyer::query()
                        ->where('phone', $supplierPhone)
                        ->lockForUpdate()
                        ->first();

                    if (!$supplier) {
                        if (!$supplierName) {
                            throw ValidationException::withMessages([
                                'supplier_name' => ['Supplier name is required when creating a new supplier.'],
                            ]);
                        }

                        $supplier = Supplyer::create([
                            'name' => $supplierName,
                            'phone' => $supplierPhone,
                        ]);
                    } elseif ($supplierName && $supplier->name !== $supplierName) {
                        $supplier->update(['name' => $supplierName]);
                    }
                }

                $subtotal = round($cartItems->sum(
                    fn ($item) => (float) $item->price * (int) $item->quantity
                ), 2);

                $manualDiscount = round(max(0, (float) ($validated['discount'] ?? 0)), 2);
                $discount = round(min($subtotal, $manualDiscount), 2);

                $vatPercentage = round(
                    min(100, max(0, (float) ($validated['vat'] ?? 0))),
                    2
                );

                $taxableAmount = round(max(0, $subtotal - $discount), 2);
                $vat = round(($taxableAmount * $vatPercentage) / 100, 2);
                $payableAmount = round(max(0, $taxableAmount + $vat), 2);

                $receivedAmount = round(
                    max(0, (float) ($validated['received_amount'] ?? 0)),
                    2
                );

                $paidAmount = round(min($receivedAmount, $payableAmount), 2);
                $dueAmount = round(max(0, $payableAmount - $paidAmount), 2);
                $changeAmount = round(max(0, $receivedAmount - $payableAmount), 2);

                if ($payableAmount > 0 && $paidAmount <= 0) {
                    throw ValidationException::withMessages([
                        'received_amount' => ['Payment amount must be greater than zero.'],
                    ]);
                }

                $isPartiallyPaid = $paidAmount > 0 && $paidAmount < $payableAmount;

                if ($isPartiallyPaid && !$supplier) {
                    throw ValidationException::withMessages([
                        'supplier_id' => ['Supplier is required for partial payment.'],
                    ]);
                }

                $paymentMethod = $validated['payment_method'] ?? PurchaseOrderPayment::METHOD_CASH;

                if (!in_array($paymentMethod, PurchaseOrderPayment::PAYMENT_METHODS, true)) {
                    throw ValidationException::withMessages([
                        'payment_method' => ['Invalid payment method.'],
                    ]);
                }

                if ($payableAmount <= 0 || $paidAmount >= $payableAmount) {
                    $orderStatus = PurchaseOrder::STATUS_COMPLETED;
                } elseif ($paidAmount > 0) {
                    $orderStatus = PurchaseOrder::STATUS_PARTIALLY_PAID;
                } else {
                    $orderStatus = PurchaseOrder::STATUS_UNPAID;
                }

                $orderNumber = 'PO-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
                $slug = Str::slug($orderNumber);

                $order = PurchaseOrder::create([
                    'reg' => $reg,
                    'order_number' => $orderNumber,
                    'slug' => $slug,
                    'order_date' => now()->toDateString(),
                    'user_id' => $user->id,
                    'supplier_id' => $supplier?->id,
                    'supplier_name' => $supplier?->name ?: $supplierName,
                    'supplier_phone' => $supplier?->phone ?: $supplierPhone,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'vat_percentage' => $vatPercentage,
                    'vat' => $vat,
                    'due_amount' => $dueAmount,
                    'payable_amount' => $payableAmount,
                    'payment_method' => $paymentMethod,
                    'currency' => PurchaseOrder::CURRENCY_BDT,
                    'status' => $orderStatus,
                    'completed_at' => $orderStatus === PurchaseOrder::STATUS_COMPLETED ? now() : null,
                    'remarks' => $validated['remarks'] ?? "Purchase order created by user: {$user->name}",
                    'paid_at' => $paidAmount > 0 ? now() : null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                $payment = null;

                if ($paidAmount > 0) {
                    $payment = PurchaseOrderPayment::create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'supplier_id' => $supplier?->id,
                        'payment_type' => PurchaseOrderPayment::TYPE_PAYMENT,
                        'payment_method' => $paymentMethod,
                        'amount' => $paidAmount,
                        'currency' => PurchaseOrderPayment::CURRENCY_BDT,
                        'paid_at' => now(),
                        'received_by' => $user->id,
                        'remarks' => $validated['remarks'] ?? "Purchase order payment received by user: {$user->name}",
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                }

                foreach ($cartItems as $item) {
                    $product = Product::query()
                        ->lockForUpdate()
                        ->find($item->product_id);

                    if (!$product) {
                        throw ValidationException::withMessages([
                            'product' => ["Product not found: {$item->product_id}"],
                        ]);
                    }

                    $quantity = (int) $item->quantity;

                    if ($quantity < 1) {
                        throw ValidationException::withMessages([
                            'quantity' => ["Invalid quantity for product {$product->id}."],
                        ]);
                    }

                    $currentStock = (float) ($product->stock_quantity ?? 0);
                    $quantity = (int) $item->quantity;

                    if ($quantity < 1) {
                        throw ValidationException::withMessages([
                            'quantity' => ["Invalid quantity for product {$product->id}."],
                        ]);
                    }

                    $product->update([
                        'stock_quantity' => $currentStock + $quantity,
                        'purchase_price' => round((float) $item->price, 2),
                        'price' => round((float) ($item->sale_price ?? $product->price), 2),
                    ]);

                    Stock::create([
                        'product_id' => $product->id,
                        'batch_no' => $item->batch_no ?? null,
                        'reg' => $reg,
                        'date' => now()->toDateString(),
                        'purchase_price' => round((float) $item->price, 2),
                        'sale_price' => round((float) ($item->sale_price ?? $product->price), 2),
                        'stockIn' => $quantity,
                        'stockOut' => 0,
                        'expiry_date' => $item->expiry_date ?? null,
                        'remark' => "Purchase order: {$order->order_number}",
                        'status' => 'active',
                    ]);
                }

                // PurchaseCart::query()
                //     ->where('user_id', $user->id)
                //     ->where('reg', $reg)
                //     ->delete();

                $order->load(['supplier', 'payments']);

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
                'message' => 'Purchase order placed successfully.',
                'data' => [
                    'order' => $result['order'],
                    'payment' => $result['payment'],
                    'paid_amount' => $result['paid_amount'],
                    'due_amount' => $result['due_amount'],
                    'received_amount' => $result['received_amount'],
                    'change_amount' => $result['change_amount'],
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Purchase order confirmation failed', [
                'user_id' => $user->id,
                'reg' => $reg,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->isProduction()
                    ? 'Something went wrong. Please try again.'
                    : $e->getMessage(),
            ], 500);
        }
    }

    public function purchaseOrder(Request $request)
    {
        try {
            $perPage = min((int) $request->input('per_page', 20), 100);

            $query = PurchaseOrder::query()
                ->with([
                    'user:id,name',
                    'supplier:id,name',
                ])
                ->when($request->filled('search'), function ($query) use ($request) {

                    $search = trim($request->input('search'));

                    $query->where(function ($q) use ($search) {

                        // Purchase Order fields
                        $q->where('reg', 'LIKE', "%{$search}%")
                            ->orWhere('order_number', 'LIKE', "%{$search}%")
                            ->orWhere('slug', 'LIKE', "%{$search}%")
                            ->orWhere('supplier_name', 'LIKE', "%{$search}%")
                            ->orWhere('supplier_phone', 'LIKE', "%{$search}%")
                            ->orWhere('payment_method', 'LIKE', "%{$search}%")
                            ->orWhere('currency', 'LIKE', "%{$search}%")
                            ->orWhere('status', 'LIKE', "%{$search}%")
                            ->orWhere('remarks', 'LIKE', "%{$search}%")

                            // User relation
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery->where('name', 'LIKE', "%{$search}%");
                            })

                            // Supplier relation
                            ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                                $supplierQuery->where('name', 'LIKE', "%{$search}%");
                            });
                    });
                })

                // Status filter
                ->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('status', $request->input('status'));
                })

                // Supplier filter
                ->when($request->filled('supplier_id'), function ($query) use ($request) {
                    $query->where('supplier_id', $request->input('supplier_id'));
                })

                // User filter
                ->when($request->filled('user_id'), function ($query) use ($request) {
                    $query->where('user_id', $request->input('user_id'));
                })

                ->latest('id');

            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Purchase orders fetched successfully.',
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Purchase order fetch failed.', [
                'user_id' => Auth::id(),
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch purchase orders.',
            ], 500);
        }
    }

    public function getPurchaseDetails($reg)
    {
        try {
            $order = PurchaseOrder::with([
                'user:id,name,user_id',
                'supplier',
                'items.product:id,name',
                'items.product.images:id,product_id,image_path,is_primary,sort_order',
                'payments.user:id,name,user_id',
                'payments.supplier',
            ])
            ->where('reg', $reg)
            ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Purchase order not found.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Purchase order fetched successfully.',
                'data' => [
                    'order' => $order,
                    'payments' => $order->payments,
                    'cartItems' => $order->items,
                    'user' => auth()->user(),
                ],
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Purchase order details fetch failed', [
                'reg' => $reg,
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching purchase order details.',
            ], 500);
        }
    }

    public function dueCollection(Request $request)
    {
        $validated = $request->validate([
            'reg' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'discount' => ['nullable', 'numeric', 'gte:0'],
            'payment_method' => [
                'required',
                'string',
                'in:cash,bkash,nagad,card,bank'
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {
            $result = DB::transaction(function () use (
                $validated,
                $user,
                $request
            ) {

                /*
                |--------------------------------------------------------------------------
                | Lock Order
                |--------------------------------------------------------------------------
                */

                $order = PurchaseOrder::where('reg', $validated['reg'])
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw ValidationException::withMessages([
                        'reg' => ['Order not found.'],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Current Due
                |--------------------------------------------------------------------------
                */

                $currentDue = round((float) $order->due_amount, 2);

                if ($currentDue <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => [
                            'This order has no due amount.'
                        ],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Payment + Discount
                |--------------------------------------------------------------------------
                */

                $paymentAmount = round(
                    (float) $validated['amount'],
                    2
                );

                $discount = round(
                    (float) ($validated['discount'] ?? 0),
                    2
                );

                /*
                |--------------------------------------------------------------------------
                | Validate Discount
                |--------------------------------------------------------------------------
                */

                if ($discount > $currentDue) {
                    throw ValidationException::withMessages([
                        'discount' => [
                            'Discount cannot exceed current due amount.'
                        ],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Amount After Discount
                |--------------------------------------------------------------------------
                */

                $dueAfterDiscount = round(
                    $currentDue - $discount,
                    2
                );

                /*
                |--------------------------------------------------------------------------
                | Validate Payment
                |--------------------------------------------------------------------------
                */

                if ($paymentAmount > $dueAfterDiscount) {
                    throw ValidationException::withMessages([
                        'amount' => [
                            'Payment cannot exceed remaining due after discount.'
                        ],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate Remaining Due
                |--------------------------------------------------------------------------
                */

                $remainingDue = round(
                    $dueAfterDiscount - $paymentAmount,
                    2
                );

                if ($remainingDue < 0) {
                    $remainingDue = 0;
                }

                /*
                |--------------------------------------------------------------------------
                | Create Payment
                |--------------------------------------------------------------------------
                */

                $payment = PurchaseOrderPayment::create([
                    'order_id'       => $order->id,
                    'user_id'        => $user->id,
                    'received_by'    => $user->id,
                    'customer_id'    => $order->customer_id,

                    'payment_number' => 'PAY-' . strtoupper(Str::random(12)),
                    'receipt_no'     => 'REC-' . strtoupper(Str::random(12)),

                    'amount'         => $paymentAmount,
                    'discount'       => $discount,

                    'payment_method' => $validated['payment_method'],
                    'paid_at'        => now(),

                    'remarks'        => $validated['remarks'] ?? 'Due Collection',

                    'ip_address'     => $request->ip(),
                    'user_agent'     => $request->userAgent(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Update Order
                |--------------------------------------------------------------------------
                */

                $order->due_amount = $remainingDue;

                if ($remainingDue == 0.00) {

                    $order->due_amount = 0;

                    // Only set paid_at when becoming fully paid
                    if (!$order->paid_at) {
                        $order->paid_at = now();
                    }

                    $order->status = 'paid';

                } else {

                    $order->status = 'partially_paid';
                }

                $order->save();

                /*
                |--------------------------------------------------------------------------
                | Payment Summary
                |--------------------------------------------------------------------------
                */

                $totalPaid = round(
                    (float) PurchaseOrderPayment::where('order_id', $order->id)
                        ->sum('amount'),
                    2
                );

                $totalDiscount = round(
                    (float) PurchaseOrderPayment::where('order_id', $order->id)
                        ->sum('discount'),
                    2
                );

                $totalSettled = round(
                    $totalPaid + $totalDiscount,
                    2
                );

                return [
                    'order' => $order->fresh(),
                    'payment' => $payment->load('user'),
                    'total_paid' => $totalPaid,
                    'total_discount' => $totalDiscount,
                    'total_settled' => $totalSettled,
                    'current_due' => $currentDue,
                    'payment_amount' => $paymentAmount,
                    'discount' => $discount,
                    'remaining_due' => $remainingDue,
                    'is_fully_paid' => $remainingDue === 0.00,
                ];
            });

            return response()->json([
                'success' => true,

                'message' => $result['is_fully_paid']
                    ? 'Payment collected successfully. Order is fully paid.'
                    : 'Due payment collected successfully.',

                'data' => $result,
            ], 200);

        } catch (ValidationException $e) {

            throw $e;

        } catch (\Throwable $e) {

            Log::error('Due collection failed', [
                'reg' => $request->reg,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                // 'message' => 'Something went wrong while collecting payment.',
            ], 500);
        }
    }





    // Purchase report
    public function purchaseReport(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 20);

            // Prevent invalid / excessive pagination
            $perPage = max(1, min($perPage, 100));

            $search = trim((string) $request->input('search', ''));

            $query = PurchaseOrder::query()
                ->with([
                    'user:id,name',
                    'supplier:id,name',
                ])

                // Search
                ->when($search !== '', function ($query) use ($search) {

                    $query->where(function ($q) use ($search) {

                        $q->where('reg', 'LIKE', "%{$search}%")
                            ->orWhere('order_number', 'LIKE', "%{$search}%")
                            ->orWhere('slug', 'LIKE', "%{$search}%")
                            ->orWhere('supplier_name', 'LIKE', "%{$search}%")
                            ->orWhere('supplier_phone', 'LIKE', "%{$search}%")
                            ->orWhere('payment_method', 'LIKE', "%{$search}%")
                            ->orWhere('currency', 'LIKE', "%{$search}%")
                            ->orWhere('status', 'LIKE', "%{$search}%")
                            ->orWhere('remarks', 'LIKE', "%{$search}%")

                            // User name
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery->where(
                                    'name',
                                    'LIKE',
                                    "%{$search}%"
                                );
                            })

                            // Supplier name
                            ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                                $supplierQuery->where(
                                    'name',
                                    'LIKE',
                                    "%{$search}%"
                                );
                            });
                    });
                })

                // Status filter
                ->when(
                    $request->filled('status'),
                    function ($query) use ($request) {
                        $query->where(
                            'status',
                            $request->input('status')
                        );
                    }
                )

                // Supplier filter
                ->when(
                    $request->filled('supplier_id'),
                    function ($query) use ($request) {
                        $query->where(
                            'supplier_id',
                            $request->input('supplier_id')
                        );
                    }
                )

                // User filter
                ->when(
                    $request->filled('user_id'),
                    function ($query) use ($request) {
                        $query->where(
                            'user_id',
                            $request->input('user_id')
                        );
                    }
                )

                ->latest('id');

            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Purchase orders fetched successfully.',

                'data' => $orders->items(),

                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page'     => $orders->perPage(),
                    'total'        => $orders->total(),
                    'last_page'    => $orders->lastPage(),
                    'from'         => $orders->firstItem(),
                    'to'           => $orders->lastItem(),
                ],
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Purchase order report failed.', [
                'user_id' => Auth::id(),
                'request' => $request->all(),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch purchase orders.',
                'error'   => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    public function reportSaleFilter(Request $request)
    {
        $validated = $request->validate([
            'start_date'  => 'nullable|date|date_format:Y-m-d',
            'end_date'    => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'search'      => 'nullable|string|max:255',
            'status'      => 'nullable|string|max:50',
            'supplier_id' => 'nullable|integer',
            'user_id'     => 'nullable|integer',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $startDate = $validated['start_date'] ?? now()->toDateString();
            $endDate = $validated['end_date'] ?? now()->toDateString();
            $perPage = min((int) ($validated['per_page'] ?? 20), 100);

            $query = PurchaseOrder::query()
                ->with([
                    'user:id,user_id,name,email',
                    'supplier:id,name',
                ])
                ->whereBetween('order_date', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ])
                ->when(!empty($validated['search']), function ($query) use ($validated) {
                    $search = trim($validated['search']);

                    $query->where(function ($q) use ($search) {
                        $q->where('reg', 'LIKE', "%{$search}%")
                            ->orWhere('order_number', 'LIKE', "%{$search}%")
                            ->orWhere('slug', 'LIKE', "%{$search}%")
                            ->orWhere('supplier_name', 'LIKE', "%{$search}%")
                            ->orWhere('supplier_phone', 'LIKE', "%{$search}%")
                            ->orWhere('payment_method', 'LIKE', "%{$search}%")
                            ->orWhere('currency', 'LIKE', "%{$search}%")
                            ->orWhere('status', 'LIKE', "%{$search}%")
                            ->orWhere('remarks', 'LIKE', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('user_id', 'LIKE', "%{$search}%");
                            })
                            ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                                $supplierQuery->where('name', 'LIKE', "%{$search}%");
                            });
                    });
                })
                ->when(!empty($validated['status']), function ($query) use ($validated) {
                    $query->where('status', $validated['status']);
                })
                ->when(!empty($validated['supplier_id']), function ($query) use ($validated) {
                    $query->where('supplier_id', $validated['supplier_id']);
                })
                ->when(!empty($validated['user_id']), function ($query) use ($validated) {
                    $query->where('user_id', $validated['user_id']);
                })
                ->latest('id');

            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Purchase orders fetched successfully.',
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Purchase order report filter failed.', [
                'user_id' => Auth::id(),
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch purchase orders. Please try again later.',
            ], 500);
        }
    }
}
