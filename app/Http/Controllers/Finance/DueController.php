<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

use App\Models\Order;
use App\Models\Cart;
use App\Models\OrderPayment;


class DueController extends Controller
{
    public function index(Request $request)
    {
        try{
            $query = Order::query()->with('user')->where('status', Order::STATUS_PARTIALLY_PAID);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('reg', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
                    // transaction_id column
                    if (\Schema::hasColumn('orders', 'transaction_id')) {
                        $q->orWhere('transaction_id', 'like', "%{$search}%");
                    }

                    $q->orWhereHas('user', function ($user) use ($search) {
                        $user->where('name', 'like', "%{$search}%");
                        // user_id column
                        if (\Schema::hasColumn('users', 'user_id')) {
                            $user->orWhere('user_id', 'like', "%{$search}%");
                        }
                    });
                });
            }

            $orders = $query->latest()->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Orders fetched successfully.',
                'data' => $orders,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders. Please try again later.',
            ], 500);
        }
    }

    public function getOrderDetails($reg)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $order = Order::with([
                'user:id,name,user_id',
                'customer:id,customer_name,phone,address',
            ])
            ->where('reg', $reg)
            ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                ], 404);
            }

            $payments = OrderPayment::with([
                'user:id,name', 'receiver'
            ])
            ->where('order_id', $order->id)
            ->latest('id')
            ->get();

            $totalPaid = round(
                (float) $payments->sum('amount'),
                2
            );

            $totalDiscount = round(
                (float) $payments->sum('discount'),
                2
            );

            $cartItems = Cart::with([
                'product:id,name',
                'product.images:id,product_id,image_path,is_primary,sort_order',
            ])
            ->where('reg', $order->reg)
            ->get();

            return response()->json([
                'success' => true,
                'message' => 'Order fetched successfully.',
                'data' => [
                    'order' => $order,
                    'payments' => $payments,
                    'total_paid' => $totalPaid,
                    'total_collection_discount' => $totalDiscount,
                    'user' => $user,
                    'cartItems' => $cartItems,
                ],
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Get order details failed', [
                'reg' => $reg,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch order details.',
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

                $order = Order::where('reg', $validated['reg'])
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

                $payment = OrderPayment::create([
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
                    (float) OrderPayment::where('order_id', $order->id)
                        ->sum('amount'),
                    2
                );

                $totalDiscount = round(
                    (float) OrderPayment::where('order_id', $order->id)
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
                // 'message' => $e->getMessage(),
                'message' => 'Something went wrong while collecting payment.',
            ], 500);
        }
    }
}
