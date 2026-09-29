<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Library\SslCommerz\SslCommerzNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\Cart;
use App\Models\User;
use App\Models\Order;

class PaymentController extends Controller
{
    public function index(Request $request, $reg)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user',
            ], 401);
        }

        $cartItems = Cart::where('reg', $reg)->get();
        if (!$cartItems->count()) {
            return response()->json([
                'success' => false,
                'message' => "Cart items is empty.",
            ]);
        }

        $order = Order::where('reg', $reg)->first();
        if ($order) {
            return response()->json([
                'success' => false,
                'message' => "Order already created.",
            ]);
        }

        $amount = $cartItems->sum(fn($item) => $item->price * $item->quantity);
        $point = $cartItems->sum(fn($item) => $item->point * $item->quantity);

        $tran_id = uniqid('SSLCZ_');

        $order = Order::create([
            'reg' => $reg,
            'date' => now()->toDateString(),
            'user_id' => $user->id,
            'transaction_id' => $tran_id,
            'currency' => 'BDT',
            'status' => 'Pending',
            'amount' => $amount,
            'point' => (int) $point,
            'paid_at' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => "Your order is confirmed. Thanks You.",
        ]);



        // Payment get way working in progress
        // Here are after confirm order then get payment get way setting
        // SSL commerz ar all setup done. Just route, controller & view setup under construction

        // $post_data = [
        //     'total_amount' => $amount,
        //     'currency' => "BDT",
        //     'tran_id' => $tran_id,

        //     // CALLBACK URLs
        //     'success_url' => route('ssl.success'),
        //     'fail_url' => route('ssl.fail'),
        //     'cancel_url' => route('ssl.cancel'),

        //     // CUSTOMER INFO
        //     'cus_name' => $user->name,
        //     'cus_email' => $user->email,
        //     'cus_add1' => $user->present_address ?? 'N/A',
        //     'cus_add2' => "",
        //     'cus_city' => "",
        //     'cus_state' => "",
        //     'cus_postcode' => "",
        //     'cus_country' => "Bangladesh",
        //     'cus_phone' => $user->phone,
        //     'cus_fax' => "",

        //     // SHIPPING INFO
        //     'ship_name' => $user->name,
        //     'ship_add1' => $user->present_address ?? 'N/A',
        //     'ship_city' => "",
        //     'ship_state' => "",
        //     'ship_postcode' => "",
        //     'ship_country' => "Bangladesh",

        //     'shipping_method' => "NO",
        //     'product_name' => "Cart Items",
        //     'product_category' => "Ecommerce",
        //     'product_profile' => "physical-goods",
        // ];

        // $sslc = new SslCommerzNotification();

        // $payment_options = $sslc->makePayment($post_data, 'hosted');

        // if (!is_array($payment_options)) {
        //     \Log::error('SSLCommerz Error:', (array) $payment_options);
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Payment initialization failed',
        //         'debug' => $payment_options
        //     ], 500);
        // }

        // return $payment_options;
    }
}
