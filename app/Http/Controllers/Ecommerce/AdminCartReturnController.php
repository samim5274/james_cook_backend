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
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Customer;
use App\Models\Order;
use App\Services\RegGenerator;
use App\Http\Requests\CheckOutRequest;
use App\Http\Requests\OrderReturnRequest;
use App\Mail\OrderMail;
use App\Models\OrderPayment;
use App\Services\PointService;

class AdminCartReturnController extends Controller
{
    protected $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function index(string $reg, string $slug)
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
            | Get Order
            |--------------------------------------------------------------------------
            */

            $order = Order::query()
                ->with([
                    'customer',
                    'user',
                ])
                ->where('reg', $reg)
                ->where('slug', $slug)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Get Cart Items
            |--------------------------------------------------------------------------
            */

            $items = Cart::query()
                ->with([
                    'product.images',
                    'stock',
                    'user',
                ])
                ->where('reg', $order->reg)
                ->orderBy('id', 'asc')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Get Payments
            |--------------------------------------------------------------------------
            */

            $payments = OrderPayment::query()
                ->with([
                    'customer',
                    'user',
                    'receiver',
                    'verifier',
                ])
                ->where('order_id', $order->id)
                ->orderBy('paid_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Get Returns
            |--------------------------------------------------------------------------
            */

            $returns = OrderReturn::query()
                ->with([
                    'user',
                    'customer',
                    'approvedBy',
                    'items.product.images',
                    'items.stock',
                    'items.cart',
                ])
                ->where('order_id', $order->id)
                ->latest('id')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Cart Summary
            |--------------------------------------------------------------------------
            */

            $totalQuantity = (int) $items->sum(
                fn ($item) => (int) $item->quantity
            );

            $subtotal = round(
                $items->sum(
                    fn ($item) =>
                        (float) $item->price *
                        (int) $item->quantity
                ),
                2
            );

            $discount = round(
                $items->sum(
                    fn ($item) =>
                        (float) ($item->discount ?? 0) *
                        (int) $item->quantity
                ),
                2
            );

            $total = round(
                max(
                    0,
                    $subtotal - $discount
                ),
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Payment Summary
            |--------------------------------------------------------------------------
            */

            $totalPaid = round(
                $payments
                    ->where(
                        'payment_type',
                        OrderPayment::TYPE_PAYMENT
                    )
                    ->sum(
                        fn ($payment) =>
                            (float) $payment->amount
                    ),
                2
            );

            $totalRefunded = round(
                abs(
                    $payments
                        ->where(
                            'payment_type',
                            OrderPayment::TYPE_REFUND
                        )
                        ->sum(
                            fn ($payment) =>
                                (float) $payment->amount
                        )
                ),
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Return Summary
            |--------------------------------------------------------------------------
            */

            $returnedQuantity = (int) $returns
                ->flatMap(
                    fn ($return) => $return->items
                )
                ->sum(
                    fn ($item) => (int) $item->quantity
                );

            $returnSubtotal = round(
                $returns->sum(
                    fn ($return) =>
                        (float) $return->subtotal
                ), 2
            );

            $returnDiscount = round(
                $returns->sum(
                    fn ($return) =>
                        (float) $return->discount
                ),
                2
            );

            $returnVat = round(
                $returns->sum(
                    fn ($return) =>
                        (float) $return->vat
                ),
                2
            );

            $returnAmount = round(
                $returns->sum(
                    fn ($return) =>
                        (float) $return->refund_amount
                ),
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Final Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,
                'message' => 'Order details fetched successfully.',
                'reg' => $order->reg,
                'data' => [
                    'order' => $order,
                    'cart_items' => $items,
                    'payments' => $payments,
                    'returns' => $returns,
                ],
                'summary' => [
                    'cart' => [
                        'total_items' => $items->count(),
                        'total_quantity' => $totalQuantity,
                        'subtotal' => $subtotal,
                        'discount' => $discount,
                        'total' => $total,
                    ],

                    'payment' => [
                        'total_paid' => $totalPaid,
                        'total_refunded' => $totalRefunded,
                        'net_received' => round(
                            $totalPaid - $totalRefunded, 2
                        ),
                    ],

                    'return' => [
                        'total_returns' => $returns->count(),
                        'returned_quantity' => $returnedQuantity,
                        'subtotal' => $returnSubtotal,
                        'discount' => $returnDiscount,
                        'vat' => $returnVat,
                        'refund_amount' => $returnAmount,
                    ],
                ],
            ], 200);

        } catch (\Throwable $e) {

            Log::error('POS Order Details Fetch Failed', [
                'user_id' => $userId,
                'reg' => $reg,
                'slug' => $slug,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch order details. Please try again.',
            ], 500);
        }
    }

    public function updateQty(Request $request, $reg, $product_id)
    {
        $data = $request->validate([
            'quantity' => [
                'required',
                'integer',
                'min:0',
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

            $cart = Cart::where('reg', $reg)
                    ->where('product_id', $product_id)
                    ->first();

            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'This product is not available in this shipment.',
                ], 404);
            }

            if ($data['quantity'] > $cart->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Maximum {$cart->quantity} quantity returnable.",
                    'data' => [
                        'returned_quantity' => $cart->returned_quantity,
                    ],
                ], 422);
            }

            // Absolute SET (increment/+= na)
            $cart->returned_quantity = $data['quantity'];
            $cart->save();

            return response()->json([
                'success' => true,
                'message' => 'Return quantity updated successfully.',
                'data' => [
                    'cart_id' => $cart->id,
                    'product_id' => $cart->product_id,
                    'stock_id' => $cart->stock_id,
                    'quantity' => $cart->quantity,
                    'returned_quantity' => $cart->returned_quantity,
                    'available_return_quantity' =>
                        $cart->quantity - $cart->returned_quantity,
                ],
            ]);

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
                'quantity' => $data['quantity'] ?? null,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update cart quantity.',
            ], 500);
        }
    }

    public function checkOutReturn(OrderReturnRequest $request, string $reg)
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
            $result = DB::transaction(function () use ($validated, $user, $reg, $request) {

                /*
                |--------------------------------------------------------------------------
                | Lock Order
                |--------------------------------------------------------------------------
                */
                $order = Order::query()
                    ->where('reg', $reg)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw ValidationException::withMessages([
                        'reg' => ['Order not found.'],
                    ]);
                }

                if (!in_array($order->status, [
                    Order::STATUS_COMPLETED,
                    Order::STATUS_PARTIALLY_PAID,
                    Order::STATUS_PARTIALLY_RETURNED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'order' => ['This order is not eligible for return.'],
                    ]);
                }

                $subtotal = abs((float) $order->subtotal);
                $orderDiscount = abs((float) $order->discount);
                $vatPercentage = abs((float) $order->vat_percentage);

                // Ratio at which original discount reduced the subtotal.
                // Used to prorate discount to each returned line so partial
                // returns stay consistent with the original order totals.
                $discountRatio = $subtotal > 0
                    ? min(1, $orderDiscount / $subtotal)
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Customer
                |--------------------------------------------------------------------------
                */
                $customer = $order->customer_id
                    ? Customer::query()
                        ->whereKey($order->customer_id)
                        ->lockForUpdate()
                        ->first()
                    : null;

                /*
                |--------------------------------------------------------------------------
                | Validate & Lock Requested Items
                |--------------------------------------------------------------------------
                */
                $requestedItems = collect($validated['items']);
                $cartIds = $requestedItems->pluck('cart_id')->all();

                $cartItems = Cart::query()
                    ->where('reg', $reg)
                    ->whereIn('id', $cartIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($cartItems->count() !== count($cartIds)) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more selected items do not belong to this order.'],
                    ]);
                }

                $lineSubtotalTotal = 0.0;
                $lineDiscountTotal = 0.0;
                $lineVatTotal = 0.0;
                $lineRefundTotal = 0.0;
                $lineDetails = [];

                foreach ($requestedItems as $requestedItem) {

                    $cartItem = $cartItems->get($requestedItem['cart_id']);
                    $returnQty = (int) $requestedItem['quantity'];

                    $alreadyReturned = (int) $cartItem->returned_quantity;
                    $availableToReturn = (int) $cartItem->quantity - $alreadyReturned;

                    if ($returnQty > $availableToReturn) {
                        throw ValidationException::withMessages([
                            'items' => [
                                "Cannot return {$returnQty} unit(s) for product ID {$cartItem->product_id}. " .
                                "Only {$availableToReturn} unit(s) eligible for return."
                            ],
                        ]);
                    }

                    $unitPrice = (float) $cartItem->price;
                    $unitDiscount = (float) ($cartItem->discount ?? 0);

                    $lineGrossSubtotal = round($unitPrice * $returnQty, 2);

                    $lineDiscount = round(
                        min($lineGrossSubtotal, $lineGrossSubtotal * $discountRatio),
                        2
                    );

                    $lineDiscountedSubtotal = round(
                        max(0, $lineGrossSubtotal - $lineDiscount),
                        2
                    );

                    $lineVat = round(
                        ($lineDiscountedSubtotal * $vatPercentage) / 100,
                        2
                    );

                    $lineRefundAmount = round($lineDiscountedSubtotal + $lineVat, 2);

                    $lineSubtotalTotal += $lineGrossSubtotal;
                    $lineDiscountTotal += $lineDiscount;
                    $lineVatTotal += $lineVat;
                    $lineRefundTotal += $lineRefundAmount;

                    $lineDetails[] = [
                        'cart_item' => $cartItem,
                        'return_qty' => $returnQty,
                        'unit_price' => $unitPrice,
                        'unit_discount' => $unitDiscount,
                        'line_subtotal' => $lineGrossSubtotal,
                        'line_discount' => $lineDiscount,
                        'line_vat' => $lineVat,
                        'line_refund_amount' => $lineRefundAmount,
                    ];
                }

                $lineSubtotalTotal = round($lineSubtotalTotal, 2);
                $lineDiscountTotal = round($lineDiscountTotal, 2);
                $lineVatTotal = round($lineVatTotal, 2);
                $lineRefundTotal = round($lineRefundTotal, 2);

                if ($lineRefundTotal <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Return amount must be greater than zero.'],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Guard: don't refund more than what's left on the order
                |--------------------------------------------------------------------------
                */
                $payableAmount = abs((float) $order->payable_amount);
                $alreadyRefunded = abs((float) $order->refunded_amount);
                $refundableRemaining = round(max(0, $payableAmount - $alreadyRefunded), 2);

                if ($lineRefundTotal > $refundableRemaining) {
                    throw ValidationException::withMessages([
                        'items' => [
                            "Refund amount ({$lineRefundTotal}) exceeds remaining refundable amount ({$refundableRemaining})."
                        ],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Create Order Return (header)
                |--------------------------------------------------------------------------
                */
                $orderReturn = OrderReturn::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'customer_id' => $customer?->id,
                    'reg' => $reg,

                    'subtotal' => $lineSubtotalTotal,
                    'discount' => $lineDiscountTotal,
                    'vat_percentage' => $vatPercentage,
                    'vat' => $lineVatTotal,
                    'refund_amount' => $lineRefundTotal,

                    'refund_method' => $order->payment_method,
                    'remarks' => $validated['remarks'] ?? "Return processed by user: {$user->name}",

                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Per-line: record return, restock
                |--------------------------------------------------------------------------
                */
                foreach ($lineDetails as $line) {

                    /** @var Cart $cartItem */
                    $cartItem = $line['cart_item'];

                    OrderReturnItem::create([
                        'order_return_id' => $orderReturn->id,
                        'cart_id' => $cartItem->id,
                        'product_id' => $cartItem->product_id,
                        'stock_id' => $cartItem->stock_id,

                        'quantity' => $line['return_qty'],
                        'unit_price' => $line['unit_price'],
                        'unit_discount' => $line['unit_discount'],

                        'subtotal' => $line['line_subtotal'],
                        'discount' => $line['line_discount'],
                        'vat' => $line['line_vat'],
                        'refund_amount' => $line['line_refund_amount'],
                    ]);

                    $cartItem->increment('returned_quantity', $line['return_qty']);

                    // Restock: reverse exactly what checkout did (batch-accurate).
                    if ($cartItem->stock_id) {
                        $stock = Stock::query()
                            ->whereKey($cartItem->stock_id)
                            ->where('product_id', $cartItem->product_id)
                            ->lockForUpdate()
                            ->first();

                        if ($stock) {
                            $stock->decrement('stockOut', $line['return_qty']);
                        }
                    }

                    $product = Product::query()
                        ->whereKey($cartItem->product_id)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $product->increment('stock_quantity', $line['return_qty']);
                    }

                    Cache::forget('public_products');
                }

                /*
                |--------------------------------------------------------------------------
                | Refund Payment Record
                |--------------------------------------------------------------------------
                | Stored as a POSITIVE amount because sale payments were stored
                | negative (-$paidAmount) in checkOutReturn — this line reverses
                | that ledger entry, keeping the sign convention consistent.
                */
                $refundPayment = OrderPayment::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'customer_id' => $customer?->id,
                    'received_by' => $user->id,

                    'payment_type' => OrderPayment::TYPE_REFUND,
                    'payment_method' => $order->payment_method,

                    'amount' => $lineRefundTotal,
                    'currency' => OrderPayment::CURRENCY_BDT,

                    'paid_at' => now(),
                    'remarks' => $validated['remarks'] ?? 'Order return refund',

                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Update Order totals / status
                |--------------------------------------------------------------------------
                */
                $newRefundedTotal = round($alreadyRefunded + $lineRefundTotal, 2);

                $isFullyReturned = $newRefundedTotal >= $payableAmount
                    && $order->cartItems()
                        ->whereColumn('returned_quantity', '<', 'quantity')
                        ->doesntExist();

                $order->update([
                    'refunded_amount' => $newRefundedTotal,
                    'status' => $isFullyReturned
                        ? Order::STATUS_RETURNED
                        : Order::STATUS_PARTIALLY_RETURNED,
                    'returned_at' => now(),
                    'returned_by' => $user->id,
                ]);

                $order->load(['customer', 'payments']);

                return [
                    'order' => $order,
                    'order_return' => $orderReturn->load('items'),
                    'refund_payment' => $refundPayment,
                    'refund_amount' => $lineRefundTotal,
                ];

            }, 3);

            return response()->json([
                'success' => true,
                'message' => 'Return processed successfully.',
                'data' => $result,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Order return failed', [
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
