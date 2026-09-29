<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Exception;
use Carbon\Carbon;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderPayment;

class DashboardController extends Controller
{
    public function dashboard()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            $today = today();

            // ==================== ORDER SUMMARY (TODAY ONLY) ====================
            $totalOrders          = Order::whereDate('order_date', $today)->count();
            $totalPayableAmount   = (float) Order::whereDate('order_date', $today)->sum('payable_amount');
            $totalDueAmount       = (float) Order::whereDate('order_date', $today)->sum('due_amount');
            $totalSubtotal        = (float) Order::whereDate('order_date', $today)->sum('subtotal');
            $totalDiscount        = (float) Order::whereDate('order_date', $today)->sum('discount');
            $totalVat             = (float) Order::whereDate('order_date', $today)->sum('vat');
            $totalPoints          = (int) Order::whereDate('order_date', $today)->sum('point');

            $pendingOrders        = Order::whereDate('order_date', $today)->where('status', Order::STATUS_PENDING)->count();
            $unpaidOrders         = Order::whereDate('order_date', $today)->where('status', Order::STATUS_UNPAID)->count();
            $partiallyPaidOrders  = Order::whereDate('order_date', $today)->where('status', Order::STATUS_PARTIALLY_PAID)->count();
            $completedOrders      = Order::whereDate('order_date', $today)->where('status', Order::STATUS_COMPLETED)->count();
            $returnedOrders       = Order::whereDate('order_date', $today)->where('status', Order::STATUS_RETURNED)->count();

            $orderSummary = [
                // Order Summary
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'unpaid_orders' => $unpaidOrders,
                'partially_paid_orders' => $partiallyPaidOrders,
                'completed_orders' => $completedOrders,
                'returned_orders' => $returnedOrders,

                // Status Percentage
                'pending_percentage' => $totalOrders ? round($pendingOrders / $totalOrders * 100, 2) : 0,
                'unpaid_percentage' => $totalOrders ? round($unpaidOrders / $totalOrders * 100, 2) : 0,
                'partially_paid_percentage' => $totalOrders ? round($partiallyPaidOrders / $totalOrders * 100, 2) : 0,
                'completed_percentage' => $totalOrders ? round($completedOrders / $totalOrders * 100, 2) : 0,
                'returned_percentage' => $totalOrders ? round($returnedOrders / $totalOrders * 100, 2) : 0,

                // Financial
                'total_subtotal' => $totalSubtotal,
                'total_discount' => $totalDiscount,
                'total_vat' => $totalVat,
                'total_payable_amount' => $totalPayableAmount,
                'total_due_amount' => $totalDueAmount,
                'total_paid_amount' => $totalPayableAmount - $totalDueAmount,
                'total_collection' => $totalPayableAmount - $totalDueAmount,
                'total_outstanding' => $totalDueAmount,

                // Payment
                'fully_paid_orders' => Order::whereDate('order_date', $today)->where('due_amount', '<=', 0)->where('payable_amount', '>', 0)->count(),
                'paid_orders' => Order::whereDate('order_date', $today)->where('due_amount', '<=', 0)->where('payable_amount', '>', 0)->count(),
                'due_orders' => Order::whereDate('order_date', $today)->where('due_amount', '>', 0)->count(),
                'zero_value_orders' => Order::whereDate('order_date', $today)->where('payable_amount', '<=', 0)->count(),

                // Payment Method
                'cash_orders' => Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_CASH)->count(),
                'card_orders' => Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_CARD)->count(),
                'bank_transfer_orders' => Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_BANK_TRANSFER)->count(),
                'bkash_orders' => Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_BKASH)->count(),
                'nagad_orders' => Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_NAGAD)->count(),
                'rocket_orders' => Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_ROCKET)->count(),
                'wallet_orders' => Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_WALLET)->count(),

                // Payment Amount
                'cash_amount' => (float) Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_CASH)->sum('payable_amount'),
                'card_amount' => (float) Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_CARD)->sum('payable_amount'),
                'bank_transfer_amount' => (float) Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_BANK_TRANSFER)->sum('payable_amount'),
                'bkash_amount' => (float) Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_BKASH)->sum('payable_amount'),
                'nagad_amount' => (float) Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_NAGAD)->sum('payable_amount'),
                'rocket_amount' => (float) Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_ROCKET)->sum('payable_amount'),
                'wallet_amount' => (float) Order::whereDate('order_date', $today)->where('payment_method', Order::PAYMENT_METHOD_WALLET)->sum('payable_amount'),

                // Customer
                'total_customers' => Order::whereDate('order_date', $today)->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id'),
                'guest_orders' => Order::whereDate('order_date', $today)->whereNull('customer_id')->count(),
                'registered_customer_orders' => Order::whereDate('order_date', $today)->whereNotNull('customer_id')->count(),
                'customer_sales' => (float) Order::whereDate('order_date', $today)->whereNotNull('customer_id')->sum('payable_amount'),
                'guest_sales' => (float) Order::whereDate('order_date', $today)->whereNull('customer_id')->sum('payable_amount'),
                'customer_due' => (float) Order::whereDate('order_date', $today)->whereNotNull('customer_id')->sum('due_amount'),
                'guest_due' => (float) Order::whereDate('order_date', $today)->whereNull('customer_id')->sum('due_amount'),

                // Discount
                'orders_with_discount' => Order::whereDate('order_date', $today)->where('discount', '>', 0)->count(),
                'orders_without_discount' => Order::whereDate('order_date', $today)->where('discount', 0)->count(),
                'average_discount' => $totalOrders ? $totalDiscount / $totalOrders : 0,

                // VAT
                'orders_with_vat' => Order::whereDate('order_date', $today)->where('vat', '>', 0)->count(),
                'orders_without_vat' => Order::whereDate('order_date', $today)->where('vat', 0)->count(),
                'total_vat_amount' => $totalVat,
                'average_vat' => $totalOrders ? $totalVat / $totalOrders : 0,

                // Points
                'total_points' => $totalPoints,
                'orders_with_points' => Order::whereDate('order_date', $today)->where('point', '>', 0)->count(),
                'orders_without_points' => Order::whereDate('order_date', $today)->where('point', 0)->count(),
                'average_points_per_order' => $totalOrders ? $totalPoints / $totalOrders : 0,

                // Average
                'average_order_value' => $totalOrders ? $totalPayableAmount / $totalOrders : 0,
                'average_subtotal' => $totalOrders ? $totalSubtotal / $totalOrders : 0,
                'average_due_per_order' => $totalOrders ? $totalDueAmount / $totalOrders : 0,

                // Today (kept for compatibility — now same as the totals above since everything is scoped to today)
                'today_orders' => $totalOrders,
                'today_sales' => $totalPayableAmount,
                'today_due' => $totalDueAmount,

                // This Month (kept as-is for context, NOT restricted to today)
                'this_month_orders' => Order::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->count(),
                'this_month_sales' => (float) Order::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('payable_amount'),
                'this_month_discount' => (float) Order::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('discount'),
                'this_month_vat' => (float) Order::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('vat'),
                'this_month_due' => (float) Order::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('due_amount'),

                // Completion
                'completed_with_date' => Order::whereDate('order_date', $today)->where('status', Order::STATUS_COMPLETED)->whereNotNull('completed_at')->count(),
                'returned_with_date' => Order::whereDate('order_date', $today)->where('status', Order::STATUS_RETURNED)->whereNotNull('returned_at')->count(),

                // Currency
                'bdt_orders' => Order::whereDate('order_date', $today)->where('currency', Order::CURRENCY_BDT)->count(),

                // Users
                'total_sales_users' => Order::whereDate('order_date', $today)->distinct('user_id')->count('user_id'),

                // Returned
                'returned_orders_amount' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_RETURNED)->sum('payable_amount'),
                'returned_orders_due' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_RETURNED)->sum('due_amount'),

                // Completed
                'completed_sales' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_COMPLETED)->sum('payable_amount'),
                'completed_due' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_COMPLETED)->sum('due_amount'),

                // Pending
                'pending_sales' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_PENDING)->sum('payable_amount'),
                'pending_due' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_PENDING)->sum('due_amount'),

                // Unpaid
                'unpaid_sales' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_UNPAID)->sum('payable_amount'),
                'unpaid_due' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_UNPAID)->sum('due_amount'),

                // Partial
                'partial_paid_sales' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_PARTIALLY_PAID)->sum('payable_amount'),
                'partial_paid_due' => (float) Order::whereDate('order_date', $today)->where('status', Order::STATUS_PARTIALLY_PAID)->sum('due_amount'),

                // Net Sales
                'net_sales' => (float) (
                    $totalPayableAmount -
                    Order::whereDate('order_date', $today)->where('status', Order::STATUS_RETURNED)->sum('payable_amount')
                ),
            ];

            // ==================== ORDER PAYMENT SUMMARY (TODAY ONLY) ====================
            $totalPayments = OrderPayment::whereDate('paid_at', $today)->count();
            $totalPaymentAmount = (float) OrderPayment::whereDate('paid_at', $today)->sum('amount');
            $totalPaymentDiscount = (float) OrderPayment::whereDate('paid_at', $today)->sum('discount');

            $paymentSummary = [
                'total_transactions' => $totalPayments,

                'payment' => OrderPayment::whereDate('paid_at', $today)->where('payment_type', OrderPayment::TYPE_PAYMENT)->count(),
                'refund' => OrderPayment::whereDate('paid_at', $today)->where('payment_type', OrderPayment::TYPE_REFUND)->count(),
                'adjustment' => OrderPayment::whereDate('paid_at', $today)->where('payment_type', OrderPayment::TYPE_ADJUSTMENT)->count(),

                'cash' => OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_CASH)->count(),
                'card' => OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_CARD)->count(),
                'bank_transfer' => OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_BANK_TRANSFER)->count(),
                'bkash' => OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_BKASH)->count(),
                'nagad' => OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_NAGAD)->count(),
                'rocket' => OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_ROCKET)->count(),
                'wallet' => OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_WALLET)->count(),

                'total_amount' => $totalPaymentAmount,
                'total_discount' => $totalPaymentDiscount,
                'net_amount' => $totalPaymentAmount - $totalPaymentDiscount,

                'payment_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_type', OrderPayment::TYPE_PAYMENT)->sum('amount'),
                'refund_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_type', OrderPayment::TYPE_REFUND)->sum('amount'),
                'adjustment_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_type', OrderPayment::TYPE_ADJUSTMENT)->sum('amount'),

                'cash_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_CASH)->sum('amount'),
                'card_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_CARD)->sum('amount'),
                'bank_transfer_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_BANK_TRANSFER)->sum('amount'),
                'bkash_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_BKASH)->sum('amount'),
                'nagad_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_NAGAD)->sum('amount'),
                'rocket_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_ROCKET)->sum('amount'),
                'wallet_amount' => (float) OrderPayment::whereDate('paid_at', $today)->where('payment_method', OrderPayment::METHOD_WALLET)->sum('amount'),

                'total_customers' => OrderPayment::whereDate('paid_at', $today)->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id'),
                'customer_transactions' => OrderPayment::whereDate('paid_at', $today)->whereNotNull('customer_id')->count(),
                'guest_transactions' => OrderPayment::whereDate('paid_at', $today)->whereNull('customer_id')->count(),

                'verified_transactions' => OrderPayment::whereDate('paid_at', $today)->whereNotNull('verified_at')->count(),
                'unverified_transactions' => OrderPayment::whereDate('paid_at', $today)->whereNull('verified_at')->count(),
                'received_transactions' => OrderPayment::whereDate('paid_at', $today)->whereNotNull('received_by')->count(),

                'today_transactions' => $totalPayments,
                'today_amount' => $totalPaymentAmount,

                'this_month_transactions' => OrderPayment::whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->count(),

                'this_month_amount' => (float) OrderPayment::whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount'),

                'average_payment' => $totalPayments ? $totalPaymentAmount / $totalPayments : 0,

                'total_orders_paid' => OrderPayment::whereDate('paid_at', $today)->whereNotNull('order_id')
                    ->distinct('order_id')
                    ->count('order_id'),

                'total_payment_users' => OrderPayment::whereDate('paid_at', $today)->distinct('user_id')->count('user_id'),
            ];


            // ==================== PURCHASE ORDER SUMMARY (TODAY ONLY) ====================
            $totalPurchaseOrders        = PurchaseOrder::whereDate('order_date', $today)->count();
            $totalPurchasePayable       = (float) PurchaseOrder::whereDate('order_date', $today)->sum('payable_amount');
            $totalPurchaseDue           = (float) PurchaseOrder::whereDate('order_date', $today)->sum('due_amount');
            $totalPurchaseSubtotal      = (float) PurchaseOrder::whereDate('order_date', $today)->sum('subtotal');
            $totalPurchaseDiscount      = (float) PurchaseOrder::whereDate('order_date', $today)->sum('discount');
            $totalPurchaseVat           = (float) PurchaseOrder::whereDate('order_date', $today)->sum('vat');

            $pendingPurchaseOrders       = PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_PENDING)->count();
            $unpaidPurchaseOrders        = PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_UNPAID)->count();
            $paidPurchaseOrders          = PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_PAID)->count();
            $partiallyPaidPurchaseOrders = PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_PARTIALLY_PAID)->count();
            $completedPurchaseOrders     = PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_COMPLETED)->count();
            $returnedPurchaseOrders      = PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_RETURNED)->count();

            $purchaseOrderSummary = [
                // Purchase Order Summary
                'total_purchase_orders' => $totalPurchaseOrders,
                'pending_purchase_orders' => $pendingPurchaseOrders,
                'unpaid_purchase_orders' => $unpaidPurchaseOrders,
                'paid_purchase_orders' => $paidPurchaseOrders,
                'partially_paid_purchase_orders' => $partiallyPaidPurchaseOrders,
                'completed_purchase_orders' => $completedPurchaseOrders,
                'returned_purchase_orders' => $returnedPurchaseOrders,

                // Status Percentage
                'pending_percentage' => $totalPurchaseOrders ? round($pendingPurchaseOrders / $totalPurchaseOrders * 100, 2) : 0,
                'unpaid_percentage' => $totalPurchaseOrders ? round($unpaidPurchaseOrders / $totalPurchaseOrders * 100, 2) : 0,
                'paid_percentage' => $totalPurchaseOrders ? round($paidPurchaseOrders / $totalPurchaseOrders * 100, 2) : 0,
                'partially_paid_percentage' => $totalPurchaseOrders ? round($partiallyPaidPurchaseOrders / $totalPurchaseOrders * 100, 2) : 0,
                'completed_percentage' => $totalPurchaseOrders ? round($completedPurchaseOrders / $totalPurchaseOrders * 100, 2) : 0,
                'returned_percentage' => $totalPurchaseOrders ? round($returnedPurchaseOrders / $totalPurchaseOrders * 100, 2) : 0,

                // Financial
                'total_subtotal' => $totalPurchaseSubtotal,
                'total_discount' => $totalPurchaseDiscount,
                'total_vat' => $totalPurchaseVat,
                'total_payable_amount' => $totalPurchasePayable,
                'total_due_amount' => $totalPurchaseDue,
                'total_paid_amount' => $totalPurchasePayable - $totalPurchaseDue,
                'total_outstanding' => $totalPurchaseDue,

                // Payment status
                'fully_paid_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('due_amount', '<=', 0)->where('payable_amount', '>', 0)->count(),
                'due_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('due_amount', '>', 0)->count(),
                'zero_value_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('payable_amount', '<=', 0)->count(),

                // Payment Method
                'cash_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_CASH)->count(),
                'card_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_CARD)->count(),
                'bank_transfer_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_BANK_TRANSFER)->count(),
                'bkash_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_BKASH)->count(),
                'nagad_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_NAGAD)->count(),
                'rocket_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_ROCKET)->count(),
                'wallet_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_WALLET)->count(),

                // Payment Amount
                'cash_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_CASH)->sum('payable_amount'),
                'card_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_CARD)->sum('payable_amount'),
                'bank_transfer_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_BANK_TRANSFER)->sum('payable_amount'),
                'bkash_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_BKASH)->sum('payable_amount'),
                'nagad_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_NAGAD)->sum('payable_amount'),
                'rocket_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_ROCKET)->sum('payable_amount'),
                'wallet_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('payment_method', PurchaseOrderPayment::METHOD_WALLET)->sum('payable_amount'),

                // Supplier
                'total_suppliers' => PurchaseOrder::whereDate('order_date', $today)->whereNotNull('supplier_id')->distinct('supplier_id')->count('supplier_id'),
                'supplier_purchases' => (float) PurchaseOrder::whereDate('order_date', $today)->whereNotNull('supplier_id')->sum('payable_amount'),
                'supplier_due' => (float) PurchaseOrder::whereDate('order_date', $today)->whereNotNull('supplier_id')->sum('due_amount'),

                // Discount
                'orders_with_discount' => PurchaseOrder::whereDate('order_date', $today)->where('discount', '>', 0)->count(),
                'orders_without_discount' => PurchaseOrder::whereDate('order_date', $today)->where('discount', 0)->count(),
                'average_discount' => $totalPurchaseOrders ? $totalPurchaseDiscount / $totalPurchaseOrders : 0,

                // VAT
                'orders_with_vat' => PurchaseOrder::whereDate('order_date', $today)->where('vat', '>', 0)->count(),
                'orders_without_vat' => PurchaseOrder::whereDate('order_date', $today)->where('vat', 0)->count(),
                'average_vat' => $totalPurchaseOrders ? $totalPurchaseVat / $totalPurchaseOrders : 0,

                // Average
                'average_purchase_order_value' => $totalPurchaseOrders ? $totalPurchasePayable / $totalPurchaseOrders : 0,
                'average_subtotal' => $totalPurchaseOrders ? $totalPurchaseSubtotal / $totalPurchaseOrders : 0,
                'average_due_per_order' => $totalPurchaseOrders ? $totalPurchaseDue / $totalPurchaseOrders : 0,

                // Today (kept for compatibility — now same as totals above)
                'today_purchase_orders' => $totalPurchaseOrders,
                'today_purchase_amount' => $totalPurchasePayable,
                'today_purchase_due' => $totalPurchaseDue,

                // This Month (kept for context, NOT restricted to today)
                'this_month_purchase_orders' => PurchaseOrder::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->count(),
                'this_month_purchase_amount' => (float) PurchaseOrder::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('payable_amount'),
                'this_month_purchase_discount' => (float) PurchaseOrder::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('discount'),
                'this_month_purchase_vat' => (float) PurchaseOrder::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('vat'),
                'this_month_purchase_due' => (float) PurchaseOrder::whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('due_amount'),

                // Completion
                'completed_with_date' => PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_COMPLETED)->whereNotNull('completed_at')->count(),
                'returned_with_date' => PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_RETURNED)->whereNotNull('returned_at')->count(),

                // Currency
                'bdt_purchase_orders' => PurchaseOrder::whereDate('order_date', $today)->where('currency', PurchaseOrder::CURRENCY_BDT)->count(),

                // Users
                'total_purchase_users' => PurchaseOrder::whereDate('order_date', $today)->distinct('user_id')->count('user_id'),

                // Returned
                'returned_purchase_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_RETURNED)->sum('payable_amount'),
                'returned_purchase_due' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_RETURNED)->sum('due_amount'),

                // Completed
                'completed_purchase_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_COMPLETED)->sum('payable_amount'),
                'completed_purchase_due' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_COMPLETED)->sum('due_amount'),

                // Pending
                'pending_purchase_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_PENDING)->sum('payable_amount'),
                'pending_purchase_due' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_PENDING)->sum('due_amount'),

                // Unpaid
                'unpaid_purchase_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_UNPAID)->sum('payable_amount'),
                'unpaid_purchase_due' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_UNPAID)->sum('due_amount'),

                // Paid
                'paid_purchase_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_PAID)->sum('payable_amount'),

                // Partial
                'partial_paid_purchase_amount' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_PARTIALLY_PAID)->sum('payable_amount'),
                'partial_paid_purchase_due' => (float) PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_PARTIALLY_PAID)->sum('due_amount'),

                // Net Purchase
                'net_purchase' => (float) (
                    $totalPurchasePayable -
                    PurchaseOrder::whereDate('order_date', $today)->where('status', PurchaseOrder::STATUS_RETURNED)->sum('payable_amount')
                ),
            ];

            // ==================== PURCHASE ORDER PAYMENT SUMMARY (TODAY ONLY) ====================
            $totalPurchasePayments         = PurchaseOrderPayment::whereDate('paid_at', $today)->count();
            $totalPurchasePaymentAmount    = (float) PurchaseOrderPayment::whereDate('paid_at', $today)->sum('amount');
            $totalPurchasePaymentDiscount  = (float) PurchaseOrderPayment::whereDate('paid_at', $today)->sum('discount');

            $purchasePaymentSummary = [
                'total_transactions' => $totalPurchasePayments,

                'payment' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_type', PurchaseOrderPayment::TYPE_PAYMENT)->count(),
                'refund' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_type', PurchaseOrderPayment::TYPE_REFUND)->count(),
                'adjustment' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_type', PurchaseOrderPayment::TYPE_ADJUSTMENT)->count(),

                'cash' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_CASH)->count(),
                'card' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_CARD)->count(),
                'bank_transfer' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_BANK_TRANSFER)->count(),
                'bkash' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_BKASH)->count(),
                'nagad' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_NAGAD)->count(),
                'rocket' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_ROCKET)->count(),
                'wallet' => PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_WALLET)->count(),

                'total_amount' => $totalPurchasePaymentAmount,
                'total_discount' => $totalPurchasePaymentDiscount,
                'net_amount' => $totalPurchasePaymentAmount - $totalPurchasePaymentDiscount,

                'payment_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_type', PurchaseOrderPayment::TYPE_PAYMENT)->sum('amount'),
                'refund_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_type', PurchaseOrderPayment::TYPE_REFUND)->sum('amount'),
                'adjustment_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_type', PurchaseOrderPayment::TYPE_ADJUSTMENT)->sum('amount'),

                'cash_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_CASH)->sum('amount'),
                'card_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_CARD)->sum('amount'),
                'bank_transfer_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_BANK_TRANSFER)->sum('amount'),
                'bkash_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_BKASH)->sum('amount'),
                'nagad_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_NAGAD)->sum('amount'),
                'rocket_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_ROCKET)->sum('amount'),
                'wallet_amount' => (float) PurchaseOrderPayment::whereDate('paid_at', $today)->where('payment_method', PurchaseOrderPayment::METHOD_WALLET)->sum('amount'),

                // Supplier
                'total_suppliers' => PurchaseOrderPayment::whereDate('paid_at', $today)->whereNotNull('supplier_id')->distinct('supplier_id')->count('supplier_id'),
                'supplier_transactions' => PurchaseOrderPayment::whereDate('paid_at', $today)->whereNotNull('supplier_id')->count(),

                'verified_transactions' => PurchaseOrderPayment::whereDate('paid_at', $today)->whereNotNull('verified_at')->count(),
                'unverified_transactions' => PurchaseOrderPayment::whereDate('paid_at', $today)->whereNull('verified_at')->count(),
                'received_transactions' => PurchaseOrderPayment::whereDate('paid_at', $today)->whereNotNull('received_by')->count(),

                'today_transactions' => $totalPurchasePayments,
                'today_amount' => $totalPurchasePaymentAmount,

                'this_month_transactions' => PurchaseOrderPayment::whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->count(),

                'this_month_amount' => (float) PurchaseOrderPayment::whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('amount'),

                'average_payment' => $totalPurchasePayments ? $totalPurchasePaymentAmount / $totalPurchasePayments : 0,

                'first_payment_date' => PurchaseOrderPayment::whereDate('paid_at', $today)->min('paid_at'),
                'last_payment_date' => PurchaseOrderPayment::whereDate('paid_at', $today)->max('paid_at'),

                'total_orders_paid' => PurchaseOrderPayment::whereDate('paid_at', $today)->whereNotNull('order_id')
                    ->distinct('order_id')
                    ->count('order_id'),

                'total_payment_users' => PurchaseOrderPayment::whereDate('paid_at', $today)->distinct('user_id')->count('user_id'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data fetched successfully.',
                'data' => [
                    'user' => $user,
                    'summary' => $orderSummary,
                    'payment_summary' => $paymentSummary,
                    'purchase_order_summary' => $purchaseOrderSummary,
                    'purchase_order_payment_summary' => $purchasePaymentSummary,
                ]
            ], 200);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching dashboard data.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}