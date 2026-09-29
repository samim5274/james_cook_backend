<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

use App\Http\Requests\ConfirmOrderRequest;
use App\Http\Requests\ConfirmPaymentRequest;
use App\Models\User;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Services\PointService;
use App\Mail\OrderMail;
use App\Models\Cart;
use App\Models\DeliveryChargePayment;
use App\Models\Division;
use App\Models\District;
use App\Models\Upazila;
use App\Models\PoliceStation;
use App\Models\ShippingZone;
use App\Models\CustomerAddress;
use App\Models\Transaction;
use App\Models\OrderPayment;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Notice;
use App\Models\Stock;
use App\Models\Product;

class OrderController extends Controller
{
    protected $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    private function generateTransactionId(): string
    {
        do {
            $transactionId = 'TXN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(10));
        } while (
            Transaction::where('transaction_id', $transactionId)->exists()
        );

        return $transactionId;
    }

    public function index(Request $request)
    {
        try{
            $query = Order::query()->with('user');

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

    public function orderReturn(Request $request, $reg, $slug, $id)
    {
        try {

            $result = DB::transaction(function () use (
                $request,
                $reg,
                $slug,
                $id
            ) {

                /*
                |--------------------------------------------------------------------------
                | Find Order
                |--------------------------------------------------------------------------
                */

                $order = Order::query()
                    ->where('id', $id)
                    ->where('reg', $reg)
                    ->where('slug', $slug)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {

                    throw ValidationException::withMessages([
                        'order' => [
                            'Order not found.'
                        ],
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Already Returned
                |--------------------------------------------------------------------------
                */

                if ($order->status === Order::STATUS_RETURNED) {

                    throw ValidationException::withMessages([
                        'order' => [
                            'This order has already been returned.'
                        ],
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Returnable Status
                |--------------------------------------------------------------------------
                |
                | Make sure "paid" is included because your POS may use
                | "paid" instead of "completed".
                |
                */

                $returnableStatuses = [
                    Order::STATUS_COMPLETED,
                    Order::STATUS_PARTIALLY_PAID,
                    Order::STATUS_UNPAID,
                    'paid',
                ];


                if (!in_array($order->status, $returnableStatuses, true)) {

                    throw ValidationException::withMessages([
                        'order' => [
                            "Order with status '{$order->status}' cannot be returned."
                        ],
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Get Original Payments
                |--------------------------------------------------------------------------
                |
                | Only original payment records.
                | Refund records are excluded.
                |
                */

                $payments = OrderPayment::query()
                    ->where('order_id', $order->id)
                    ->where(
                        'payment_type',
                        OrderPayment::TYPE_PAYMENT
                    )
                    ->lockForUpdate()
                    ->get();


                /*
                |--------------------------------------------------------------------------
                | Calculate Total Paid
                |--------------------------------------------------------------------------
                */

                $totalPaid = round(
                    (float) $payments->sum('amount'),
                    2
                );


                /*
                |--------------------------------------------------------------------------
                | Calculate Payable
                |--------------------------------------------------------------------------
                */

                $payableAmount = round(
                    (float) $order->payable_amount,
                    2
                );


                /*
                |--------------------------------------------------------------------------
                | Calculate Refund
                |--------------------------------------------------------------------------
                */

                $refundAmount = min(
                    $totalPaid,
                    $payableAmount
                );


                /*
                |--------------------------------------------------------------------------
                | Create Refund Payment
                |--------------------------------------------------------------------------
                */

                $refundPayment = null;

                if ($refundAmount > 0) {

                    /*
                    |--------------------------------------------------------------------------
                    | Refund payment method
                    |--------------------------------------------------------------------------
                    */

                    $paymentMethod =
                        $payments->first()?->payment_method
                        ?? ($order->payment_method ?? null)
                        ?? OrderPayment::METHOD_CASH;


                    /*
                    |--------------------------------------------------------------------------
                    | Unique Refund Payment Number
                    |--------------------------------------------------------------------------
                    */

                    $paymentNumber = null;

                    do {

                        $paymentNumber =
                            'REF-' .
                            now()->format('YmdHis') .
                            '-' .
                            $order->id .
                            '-' .
                            strtoupper(
                                Str::random(6)
                            );

                    } while (
                        OrderPayment::where(
                            'payment_number',
                            $paymentNumber
                        )->exists()
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Create Refund
                    |--------------------------------------------------------------------------
                    */

                    $refundPayment = OrderPayment::create([

                        'order_id' =>
                            $order->id,

                        'user_id' =>
                            auth()->id(),

                        'customer_id' =>
                            $order->customer_id,

                        'payment_number' =>
                            $paymentNumber,

                        'receipt_no' =>
                            null,

                        'payment_type' =>
                            OrderPayment::TYPE_REFUND,

                        'payment_method' =>
                            $paymentMethod,

                        'amount' =>
                            $refundAmount,

                        'currency' =>
                            $order->currency
                            ?? OrderPayment::CURRENCY_BDT,

                        'paid_at' =>
                            now(),

                        'verified_by' =>
                            auth()->id(),

                        'verified_at' =>
                            now(),

                        'remarks' =>
                            'Refund for returned order ' .
                            ($order->order_number ?? $order->reg),

                        'ip_address' =>
                            $request->ip(),

                        'user_agent' =>
                            $request->userAgent(),
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Update Order
                |--------------------------------------------------------------------------
                */

                $order->update([

                    'status' =>
                        Order::STATUS_RETURNED,

                    'returned_at' =>
                        now(),

                    'returned_by' =>
                        auth()->id(),
                ]);


                /*
                |--------------------------------------------------------------------------
                | Fresh Data
                |--------------------------------------------------------------------------
                */

                $order->refresh();

                if ($refundPayment) {
                    $refundPayment->refresh();
                }


                /*
                |--------------------------------------------------------------------------
                | Return Transaction Result
                |--------------------------------------------------------------------------
                */

                // Restore stock
                $cartItems = Cart::where('reg', $order->reg)
                    ->lockForUpdate()
                    ->get();

                foreach ($cartItems as $item) {
                    if (!$item->stock_id || !$item->quantity) continue;

                    $stock = Stock::where('id', $item->stock_id)
                        ->where('product_id', $item->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock) continue;

                    $qty = (float) $item->quantity;

                    $stock->decrement('stockOut', $qty);

                    // Product::where('id', $item->product_id)
                    //     ->lockForUpdate()
                    //     ->increment('stock_quantity', $qty);
                }

                return [

                    'order' =>
                        $order,

                    'refund' =>
                        $refundPayment,

                    'total_paid' =>
                        $totalPaid,

                    'payable_amount' =>
                        $payableAmount,

                    'refund_amount' =>
                        $refundAmount,

                ];
            });


            /*
            |--------------------------------------------------------------------------
            | Success Response
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'message' =>
                    'Order returned successfully.',

                'data' => $result,

            ], 200);


        } catch (ValidationException $e) {

            /*
            |--------------------------------------------------------------------------
            | Validation Error
            |--------------------------------------------------------------------------
            */

            $errors = $e->errors();

            $message = collect($errors)
                ->flatten()
                ->first()
                ?? 'Unable to return this order.';


            return response()->json([

                'success' => false,

                'message' =>
                    $message,

                'errors' =>
                    $errors,

            ], 422);


        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Log Error
            |--------------------------------------------------------------------------
            */

            Log::error('ORDER RETURN ERROR', [

                'order_id' =>
                    $id,

                'reg' =>
                    $reg,

                'slug' =>
                    $slug,

                'user_id' =>
                    auth()->id(),

                'message' =>
                    $e->getMessage(),

                'line' =>
                    $e->getLine(),

                'file' =>
                    $e->getFile(),

                'trace' =>
                    $e->getTraceAsString(),
            ]);


            /*
            |--------------------------------------------------------------------------
            | Error Response
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => false,

                'message' =>
                    config('app.debug')
                        ? $e->getMessage()
                        : 'Unable to return the order. Please try again later.',

            ], 500);
        }
    }

    public function statusFilter(Request $request)
    {
        try{
            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'in:' . implode(',', OrderPayment::PAYMENT_TYPES)],
                'payment_method' => ['nullable', 'in:' . implode(',', OrderPayment::PAYMENT_METHODS)],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $perPage = (int) ($validated['per_page'] ?? 20);


            $query = OrderPayment::query()
                ->with([
                    'user:id,name,user_id',
                    'order:id,reg,order_number,customer_name,customer_phone',
                ]);

            if (!empty($validated['status'])) {
                $query->where(
                    'payment_type',
                    $validated['status']
                );
            }

            if (!empty($validated['payment_method'])) {
                $query->where(
                    'payment_method',
                    $validated['payment_method']
                );
            }

            if (!empty($validated['search'])) {
                $search = trim($validated['search']);
                $query->where(function ($q) use ($search) {

                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('receipt_no', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%");

                    $q->orWhereHas('order', function ($order) use ($search) {

                        $order->where('order_number', 'like', "%{$search}%")
                            ->orWhere('reg', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%")
                            ->orWhere('customer_phone', 'like', "%{$search}%");
                    });

                    $q->orWhereHas('user', function ($user) use ($search) {
                        $user->where('name', 'like', "%{$search}%");
                        if (\Schema::hasColumn('users', 'user_id')) {
                            $user->orWhere(
                                'user_id',
                                'like',
                                "%{$search}%"
                            );
                        }
                    });
                });
            }

            $payments = $query->latest('id')->paginate($perPage)->withQueryString();

            return response()->json([
                'success' => true,
                'message' => 'Payment records fetched successfully.',
                'data' => $payments,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }















    public function getOrderDetails($reg)
    {
        try{
            $user = auth()->user();

            $order = Order::with([
                    'user:id,name,user_id',
                    'customer:id,customer_name,phone,address'
                ])
                ->where('reg', $reg)
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                    'data'    => null,
                ], 404);
            }

            $orderPayment = null;

            $orderPayment = OrderPayment::with(['order','user'])->where('order_id', $order->id)->first();

            $cartItems = Cart::with([
                    'product:id,name',
                    'product.images:id,product_id,image_path,is_primary,sort_order'
                ])
                ->where('reg', $order->reg)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Order fetched successfully.',
                'data' => [
                    'order' => $order,
                    'payment' => $orderPayment,
                    'user' => $user,
                    'cartItems' => $cartItems,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error($e);

            // return response()->json([
            //     'success' => false,
            //     'message' => $e->getMessage(),
            //     'file' => $e->getFile(),
            //     'line' => $e->getLine(),
            // ], 500);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching order details.',
            ], 500);
        }
    }

    public function getOrderPaymentDetails($paymentNumber, $orderId)
    {
        try {
            $user = auth()->user();

            $order = Order::with([
                    'user:id,name,user_id',
                    'customer:id,customer_name,phone,address',
                ])
                ->find($orderId);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                    'data'    => null,
                ], 404);
            }

            $orderPayment = OrderPayment::with([
                    'user:id,name,user_id',
                    'verifier:id,name,user_id',
                    'receiver:id,name,user_id',
                ])
                ->where('order_id', $orderId)
                ->orderBy('id', 'desc')
                ->get();

            if ($orderPayment->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment records found for this order.',
                    'data'    => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order payment details fetched successfully.',
                'data' => [
                    'order'        => $order,
                    'orderPayment' => $orderPayment,
                    'user'         => $user,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Get Order Payment Details Error', [
                'message'        => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'payment_number' => $paymentNumber,
                'order_id'       => $orderId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching order payment details.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function updateStatus(Request $request, $reg)
    {
        try {
            $statusInput = trim($request->status);

            $validStatuses = [
                'pending'          => 'Pending',
                'confirmed'        => 'Confirmed',
                'processing'       => 'Processing',
                'picked'           => 'Picked',
                'shipped'          => 'Shipped',
                'out for delivery' => 'Out for Delivery',
                'delivered'        => 'Delivered',
                'cancelled'        => 'Cancelled',
                'failed'           => 'Failed',
                'returned'         => 'Returned',
            ];

            $lowerInput = strtolower($statusInput);
            $normalizedStatus = $validStatuses[$lowerInput] ?? $statusInput;

            $request->merge(['status' => $normalizedStatus]);

            $validated = $request->validate([
                'status' => [
                    'required',
                    'string',
                    'in:' . implode(',', array_values($validStatuses))
                ]
            ]);

            $order = Order::where('reg', $reg)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                ], 404);
            }

            if ($order->status === $validated['status']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order status is already ' . $order->status,
                ], 200);
            }

            DB::beginTransaction();

            $currentStatus = $validated['status'];
            $statusKey = strtolower($currentStatus);

            $timestampMapping = [
                'confirmed' => 'confirmed_at',
                'shipped'   => 'shipped_at',
                'delivered' => 'delivered_at',
                'cancelled' => 'cancelled_at',
            ];

            $updateData = ['status' => $currentStatus];

            if (isset($timestampMapping[$statusKey])) {
                $column = $timestampMapping[$statusKey];
                $updateData[$column] = now()->toDateString();
            }

            // if ($statusKey === 'delivered') {
            //     $updateData['paid_at'] = now()->toDateString();
            //     $updateData['payment_status'] = "Paid";
            // }

            $order->update($updateData);

            // if ($statusKey === 'delivered') {
            //     $exists = PointTransaction::where('reference_id', $order->reg)
            //         ->where('source', 'purchase')
            //         ->exists();

            //     if (!$exists) {
            //         PointTransaction::create([
            //             'user_id'        => $order->user_id,
            //             'type'           => 'earn',
            //             'points'         => (int) $order->point,
            //             'bonus_amount'   => 0,
            //             'bonus_status'   => 'credit',
            //             'source'         => 'purchase',
            //             'reference_id'   => $order->reg,
            //             'note'           => 'Points added for delivered order',
            //         ]);
            //     }
            // }

            if ($statusKey === 'delivered') {
                $user = User::find($order->user_id);
                if (!$user) return;

                $exists = PointTransaction::where('reference_id', $order->reg)
                    ->where('source', 'purchase')
                    ->exists();

                if (!$exists) {
                    if ($order->point > 0) {
                        $this->pointService->distributeOrderPoints($user, (int)$order->point, $order->reg);
                    }
                }

                Notice::create([
                    'title'        => "Order Delivered - {$order->reg}",
                    'description'  => 'Your order has been delivered successfully. Please share your valuable review and feedback. Thank you for choosing us.',
                    'publish_date' => Carbon::now(),
                    'user_id'      => $order->user_id,
                    'notice_type'  => 'Order_Update',
                ]);

                // referral bonus always safe guarded inside service
                $this->pointService->referralBonus($user, $order->reg, (int)$order->point);
            }

            $statusTitle = "Order {$currentStatus} - {$order->reg}";

            $statusDescription = match ($statusKey) {
                'confirmed'         => "Your order has been confirmed and will be processed shortly.",
                'processing'        => "Your order is currently being processed.",
                'picked'            => "Your order has been picked and is being prepared for shipment.",
                'shipped'           => "Your order has been shipped and is on its way.",
                'out for delivery'  => "Your order is out for delivery and will arrive soon.",
                'delivered'         => "Your order has been delivered successfully. Thank you for shopping with us.",
                'cancelled'         => "Your order has been cancelled.",
                'returned'          => "Your order has been returned successfully.",
                'failed'            => "Unfortunately, your order could not be completed.",
                default             => "Your order status has been updated to {$currentStatus}.",
            };

            Notice::create([
                'title'        => $statusTitle,
                'description'  => $statusDescription,
                'publish_date' => Carbon::now(),
                'user_id'      => $order->user_id,
                'notice_type'  => 'Order_Update',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully.',
                'data' => $order->fresh()
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status selected.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ORDER STATUS ERROR', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error. Please try again.',
            ], 500);
        }
    }

    public function getCustomerDetails($user_id){
        try{
            $customer = User::where('user_id', $user_id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Customer Details fetched successfully.',
                'data' => $customer,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer details. Please try again later.',
            ], 500);
        }
    }

    public function reportSale()
    {
        try{
            $orders = Order::with('user:id,user_id,name,email')
                ->where('order_date', today())
                ->latest()->paginate(20);

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

    public function reportSaleFilter(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date'   => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        try{

            $startDate = $request->start_date ?? now()->startOfDay()->toDateString();
            $endDate   = $request->end_date ?? now()->endOfDay()->toDateString();

            $query = Order::whereBetween('order_date', [$startDate, $endDate]);

            $orders = $query->with('user:id,user_id,name,email')
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Orders fetched successfully.',
                'data'    => $orders,
            ], 200);
        } catch (\Throwable $e) {


            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders. Please try again later.',
            ], 500);
        }
    }

    public function reportPayment()
    {
        try {
            $query = OrderPayment::with([
                'user:id,user_id,name,email',
                'order',
                'verifier',
                'receiver',
                'returnedBy',
            ])
            ->whereDate('paid_at', today());

            // Total amount from ALL today's payments
            $totalAmount = (clone $query)->sum('amount');

            // Paginated records
            $orderPayment = $query
                ->latest('id')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Payment report fetched successfully.',
                'data' => $orderPayment,
                'total_amount' => round((float) $totalAmount, 2),
            ], 200);

        } catch (\Throwable $e) {

            \Log::error('PAYMENT REPORT ERROR', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment report. Please try again later.',
            ], 500);
        }
    }

    public function reportPaymentFilter(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => ['nullable','date','date_format:Y-m-d',],
                'end_date' => ['nullable','date','date_format:Y-m-d','after_or_equal:start_date',],
                'page' => ['nullable','integer','min:1',],
                'per_page' => ['nullable','integer','min:1','max:100',
                ],
            ]);


            $startDate = $validated['start_date'] ?? now()->toDateString();
            $endDate = $validated['end_date'] ?? now()->toDateString();
            $perPage = (int) ($validated['per_page'] ?? 20);


            $query = OrderPayment::query()
                ->with([
                    'user:id,user_id,name,email',
                    'order:id,reg,slug,order_number,customer_name,customer_phone,payable_amount',
                    'verifier:id,user_id,name,email',
                    'receiver:id,user_id,name,email',
                    'returnedBy:id,user_id,name,email',
                ])
                ->whereBetween('paid_at', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ]);

            $payments = $query->latest('paid_at')->latest('id')->paginate($perPage)->withQueryString();

            return response()->json([
                'success' => true,
                'message' => 'Payment report fetched successfully.',
                'data' => $payments,
            ], 200);

        } catch (ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid filter parameters.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('PAYMENT REPORT FILTER ERROR', [
                'user_id' => auth()->id(),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment report. Please try again later.',
            ], 500);
        }
    }























    public function verifyPayment(Request $request, int $paymentId): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        try {

            $orderPayment = OrderPayment::find($paymentId);

            if (! $orderPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found.',
                ], 404);
            }

            if ($orderPayment->status === OrderPayment::STATUS_SUCCESS) {
                return response()->json([
                    'success' => false,
                    'message' => 'This payment has already been verified.',
                ], 422);
            }

            $order = Order::find($orderPayment->order_id);

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.',
                ], 404);
            }

            DB::transaction(function () use ($request, $orderPayment, $order, $user)
            {
                $orderPayment->update([
                    'status'      => OrderPayment::STATUS_SUCCESS,
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                    'remarks'     => 'Advance payment submitted. An admin payment verified.',
                    'ip_address'  => $request->ip(),
                    'user_agent'  => $request->userAgent(),
                ]);

                $order->update([
                    'payment_status' => Order::PAYMENT_PAID,
                    'paid_amount'    => $order->payable_amount,
                    'due_amount'     => ($orderPayment->net_amount - $order->payable_amount),
                    'paid_at'        => now(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully.',
                'data' => [
                    'payment_id' => $orderPayment->id,
                    'order_id'   => $order->id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment verification failed.', [
                'payment_id' => $paymentId,
                'user_id'    => $user->id,
                'message'    => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify payment. Please try again later.',
            ], 500);
        }
    }

    public function confirmPayment(ConfirmPaymentRequest $request, $reg)
    {
        $validated = $request->validated();

        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user',
            ], 401);
        }

        try
        {
            $payment = DB::transaction(function () use ($validated, $user, $request, $reg) {

                $order = Order::where('reg', $reg)->lockForUpdate()->first();

                if (! $order) {
                    throw ValidationException::withMessages([
                        'order' => ['Order not found.'],
                    ]);
                }

                if ($order->payment_status === Order::PAYMENT_PAID) {
                    throw ValidationException::withMessages([
                        'order' => ['This order has already been paid.'],
                    ]);
                }

                if ($validated['amount'] <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => ['Invalid payment amount.'],
                    ]);
                }

                // Re-check inside the transaction to close the race window
                // between the earlier read and this write.
                if (! empty($validated['transaction_id'])) {
                    $duplicate = OrderPayment::where('transaction_id', $validated['transaction_id'])
                        ->lockForUpdate()
                        ->exists();

                    if ($duplicate) {
                        throw ValidationException::withMessages([
                            'transaction_id' => [
                                'Transaction ID already exists.'
                            ],
                        ]);
                    }
                }

                $paidAmount = $order->payments()->success()->sum('net_amount');
                $remaining = $order->payable_amount - $paidAmount;

                if ($validated['amount'] > $remaining) {
                    throw ValidationException::withMessages([
                        'amount' => ['Payment exceeds due amount.'],
                    ]);
                }

                do {
                    $receiptNo = 'RCPT-' . Str::upper(Str::random(10));
                } while (OrderPayment::where('receipt_no', $receiptNo)->exists());

                $provider = match ($validated['payment_method']) {
                    OrderPayment::METHOD_CASH
                        => OrderPayment::PROVIDER_CASH,
                    OrderPayment::METHOD_BANK_TRANSFER
                        => OrderPayment::PROVIDER_BANK,
                    OrderPayment::METHOD_MOBILE_BANKING
                        => OrderPayment::PROVIDER_MANUAL,
                    OrderPayment::METHOD_CARD
                        => OrderPayment::PROVIDER_STRIPE,
                    OrderPayment::METHOD_PAYPAL
                        => OrderPayment::PROVIDER_PAYPAL,
                    default
                        => OrderPayment::PROVIDER_MANUAL,
                };

                if( $validated['payment_method'] === OrderPayment::METHOD_MOBILE_BANKING)
                {
                    $bankName           = $validated['provider'];
                    $accountNumber      = $validated['sender_mobile'] ?? null;
                    $accountHolderName  = $validated['sender_name'] ?? null;
                } else
                {
                    $bankName           = $validated['bank_name'] ?? null;
                    $accountNumber      = $validated['account_number'] ?? null;
                    $accountHolderName  = $validated['account_holder_name'] ?? null;
                }

                // Order Payment Table
                $orderPayment = OrderPayment::create([
                    'order_id'                  => $order->id,
                    'user_id'                   => $user->id,

                    'payment_method'            => $validated['payment_method'],
                    'provider'                  => $provider,
                    'payment_type'              => OrderPayment::TYPE_PAYMENT,

                    // Manual verification required
                    'channel'                   => OrderPayment::getChannel($validated['payment_method']),

                    // Transaction
                    'transaction_id'            => $validated['transaction_id'] ?? null,
                    'bank_name'                 => $bankName,
                    'account_number'            => $accountNumber,
                    'account_holder_name'       => $accountHolderName,
                    'sender_mobile'             => $validated['sender_mobile'] ?? null,
                    'sender_name'               => $validated['sender_name'] ?? null,

                    // Amount
                    'amount'                    => $validated['amount'],
                    'gateway_fee'               => 0,
                    'net_amount'                => $validated['amount'],
                    'currency'                  => OrderPayment::CURRENCY_BDT,

                    // Status
                    'status'                    => OrderPayment::STATUS_SUCCESS,
                    'paid_at'                   => now(),

                    // Security
                    'ip_address'                => $request->ip(),
                    'user_agent'                => $request->userAgent(),
                    'receipt_no'                => $receiptNo,

                    'verified_by'               => $user->id,
                    'received_by'               => $user->id,
                    'verified_at'               => now(),
                    'remarks'                   => $validated['remarks'] ?? null,
                ]);

                $newPaidAmount = $paidAmount + $validated['amount'];
                $newDueAmount = max($order->payable_amount - $newPaidAmount, 0);
                $paymentStatus = $newDueAmount <= 0
                    ? Order::PAYMENT_PAID
                    : Order::PAYMENT_PARTIAL;

                $order->update([
                    'payment_status' => $paymentStatus,
                    'paid_amount'    => $newPaidAmount,
                    'due_amount'     => $newDueAmount,
                    'paid_at'        => $paymentStatus === Order::PAYMENT_PAID ? now() : null,
                ]);

                // Delivery payment
                DeliveryChargePayment::create([
                        'order_id'            => $order->id,

                        'payment_date'        => now(),
                        'payment_method'      => $validated['payment_method'], // migration enum: bank | mobile | sslcommerz | cash

                        'amount'              => $order->shipping_charge,
                        'currency'            => 'BDT',

                        // bank_name column এ mobile banking provider (Bkash/Nagad/Rocket)
                        'bank_name'           => $bankName,
                        'branch_name'         => null, // frontend এ এই field নেই
                        'account_number'      => $accountNumber,
                        'mobile_number'       => $validated['sender_mobile'] ?? null,
                        'account_holder_name' => $accountHolderName,
                        'transaction_id'      => $validated['transaction_id'] ?? null,
                        'reference_no'        => $validated['remarks'] ?? null,

                        'payment_status'      => 'success', // admin manually verify
                        'paid_by'             => $user->id,

                        'notes'               => 'Delivery charge submitted by admin order by Cash on delivery',
                    ]);

                return $orderPayment;
            });

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully.',
                'data' => [
                    'payment' => $payment,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {

            Log::error('Payment submission failed.', [
                'order_reg'=>$reg,
                'user_id'=>$user->id,
                'amount'=>$validated['amount'] ?? null,
                'payment_method'=>$validated['payment_method'] ?? null,
                'transaction_id'=>$validated['transaction_id'] ?? null,
                'message'=>$e->getMessage(),
                'file'=>$e->getFile(),
                'line'=>$e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->isProduction()
                    ? 'Unable to process payment.'
                    : $e->getMessage(),
            ], 500);
        }
    }

    public function deliveryStatusUpdate(Request $request, $id)
    {
        $request->validate([
            'payment_status' => 'required|in:pending,success,return,failed',
        ]);

        try {
            $deliveryCharge = DeliveryChargePayment::find($id);

            if (! $deliveryCharge) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery charge payment not found.',
                    'data'    => null,
                ], 404);
            }

            $updateData = [
                'payment_status' => $request->payment_status,
            ];

            if ($request->payment_status === 'success' && $deliveryCharge->payment_status !== 'success') {
                $updateData['paid_by'] = auth()->id();
                $updateData['payment_date'] = now();
            }

            $deliveryCharge->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Delivery charge status updated successfully.',
                'data'    => $deliveryCharge->fresh('paidBy'),
            ], 200);
        } catch (\Throwable $e) {

            Log::error('Failed to update delivery charge status.', [
                'id'    => $id,
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while updating delivery charge status.',
            ], 500);
        }
    }
}
