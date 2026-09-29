<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Illuminate\Pagination\LengthAwarePaginator;


use App\Models\Product;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Supplyer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderPayment;
use App\Models\Expense;
use App\Models\Stock;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Cart::query()
            ->with(['product:id,name,sku'])
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('COUNT(*) as sale_count'),
                DB::raw('SUM(price * quantity) as total_price'),
                DB::raw('SUM(discount * quantity) as total_discount'),
                DB::raw('SUM((price - discount) * quantity) as total_amount')
            )
            ->groupBy('product_id')
            ->orderByDesc('total_qty');

        // Date filter
        if ($request->filled('from_date')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->from_date
            );
        }

        if ($request->filled('to_date')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->to_date
            );
        }

        $reports = $query->paginate(
            $request->integer('per_page', 20)
        );

        return response()->json([
            'status' => true,
            'message' => 'Product report fetched successfully.',

            'data' => $reports->items(),

            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'from' => $reports->firstItem(),
                'to' => $reports->lastItem(),
            ],
        ]);
    }


    public function expense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date'      => 'nullable|date|date_format:Y-m-d',
            'end_date'        => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'category_id'     => 'nullable|integer|exists:ex_categories,id',
            'sub_category_id' => 'nullable|integer|exists:ex_sub_categories,id',
            'user_id'         => 'nullable|integer|exists:users,id',
            'search'          => 'nullable|string|max:100',
            'per_page'        => 'nullable|integer|min:10|max:100',
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $query = Expense::with([
            'category:id,name',
            'subcategory:id,name',
            'user:id,name',
        ]);

        if (!empty($validated['start_date'])) {
            $query->whereDate('date', '>=', $validated['start_date']);
        }

        if (!empty($validated['end_date'])) {
            $query->whereDate('date', '<=', $validated['end_date']);
        }

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (!empty($validated['sub_category_id'])) {
            $query->where('sub_category_id', $validated['sub_category_id']);
        }

        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('remark', 'like', "%{$search}%");
            });
        }

        $summaryQuery = clone $query;

        $totalExpense = (clone $summaryQuery)->sum('amount');
        $totalTransactions = (clone $summaryQuery)->count();
        $averageExpense = $totalTransactions ? $totalExpense / $totalTransactions : 0;

        $categoryWise = (clone $summaryQuery)
            ->selectRaw('category_id, COUNT(*) as transaction_count, SUM(amount) as total_amount')
            ->with('category:id,name')
            ->groupBy('category_id')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn($item) => [
                'category_id'       => $item->category_id,
                'category'          => $item->category?->name ?? 'Uncategorized',
                'transaction_count' => (int) $item->transaction_count,
                'total_amount'      => number_format($item->total_amount, 2, '.', ''),
            ])
            ->values();

        $dailyWise = (clone $summaryQuery)
            ->selectRaw('DATE(date) as expense_date, COUNT(*) as transaction_count, SUM(amount) as total_amount')
            ->groupByRaw('DATE(date)')
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn($item) => [
                'date'              => $item->expense_date,
                'transaction_count' => (int) $item->transaction_count,
                'total_amount'      => number_format($item->total_amount, 2, '.', ''),
            ])
            ->values();

        $expenses = $query
            ->select([
                'id',
                'category_id',
                'sub_category_id',
                'user_id',
                'title',
                'date',
                'amount',
                'remark',
                'created_at',
            ])
            ->latest('date')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'status' => true,
            'message' => 'Expense report retrieved successfully.',
            'data' => [
                'summary' => [
                    'total_expense'     => number_format($totalExpense, 2, '.', ''),
                    'total_transactions'=> $totalTransactions,
                    'average_expense'   => number_format($averageExpense, 2, '.', ''),
                ],
                'category_wise' => $categoryWise,
                'daily_wise'    => $dailyWise,
                'expenses'      => $expenses,
            ],
        ]);
    }

    public function customerDue(Request $request): JsonResponse
    {
        try {

            $perPage = min(
                max((int) $request->input('per_page', 20), 1),
                100
            );

            $search = trim((string) $request->input('search', ''));

            $query = Order::query()
                ->join('customers', 'customers.id', '=', 'orders.customer_id')
                ->select([
                    'customers.id',
                    'customers.customer_name AS name',
                    'customers.phone',
                ])
                ->selectRaw('SUM(orders.payable_amount) AS total_payable')
                ->selectRaw('SUM(orders.due_amount) AS total_due')
                ->whereNotNull('orders.customer_id')
                ->whereIn('orders.status', [
                    Order::STATUS_UNPAID,
                    Order::STATUS_PARTIALLY_PAID,
                ])
                ->where('orders.due_amount', '>', 0)
                ->when($search !== '', function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('customers.customer_name', 'like', "%{$search}%")
                            ->orWhere('customers.phone', 'like', "%{$search}%");
                    });
                })
                ->groupBy('customers.id', 'customers.customer_name', 'customers.phone')
                ->orderByDesc('total_due');

            $customers = $query->paginate($perPage)->withQueryString();

            $grandTotalDue = Order::query()
                ->whereNotNull('customer_id')
                ->whereIn('status', [
                    Order::STATUS_UNPAID,
                    Order::STATUS_PARTIALLY_PAID,
                ])
                ->where('due_amount', '>', 0)
                ->sum('due_amount');

            return response()->json([
                'success' => true,
                'message' => 'Customer due report retrieved successfully.',
                'data' => $customers,
                'total_due' => round((float) $grandTotalDue, 2),
                'total_customers' => $customers->total(),
            ]);

        } catch (\Throwable $e) {

            Log::error('Customer Due Report Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer due report.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function supplyerDue(Request $request): JsonResponse
    {
        try {

            $perPage = min(
                max((int) $request->input('per_page', 20), 1),
                100
            );

            $search = trim(
                (string) $request->input('search', '')
            );

            $query = Supplyer::query()
                ->leftJoin(
                    'purchase_orders',
                    function ($join) {

                        $join->on(
                            'purchase_orders.supplier_id',
                            '=',
                            'supplyers.id'
                        );

                        $join->whereIn(
                            'purchase_orders.status',
                            [
                                PurchaseOrder::STATUS_UNPAID,
                                PurchaseOrder::STATUS_PARTIALLY_PAID,
                            ]
                        );

                        $join->where(
                            'purchase_orders.due_amount',
                            '>',
                            0
                        );

                    }
                )
                ->select([
                    'supplyers.id',
                    'supplyers.name',
                    'supplyers.phone',
                ])
                ->selectRaw('COALESCE(SUM(purchase_orders.payable_amount), 0) AS total_payable')
                ->selectRaw('COALESCE(SUM(purchase_orders.due_amount), 0) AS total_due')
                ->when($search !== '', function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('supplyers.name', 'like', "%{$search}%")
                            ->orWhere('supplyers.phone', 'like', "%{$search}%");
                    });
                })
                ->groupBy(
                    'supplyers.id',
                    'supplyers.name',
                    'supplyers.phone'
                )
                ->having('total_due', '>', 0)
                ->orderByDesc('total_due');

            $suppliers = $query
                ->paginate($perPage)
                ->withQueryString();


            $grandTotalDue = PurchaseOrder::query()
                ->whereNotNull('supplier_id')
                ->whereIn(
                    'status',
                    [
                        PurchaseOrder::STATUS_UNPAID,
                        PurchaseOrder::STATUS_PARTIALLY_PAID,
                    ]
                )
                ->where('due_amount', '>', 0)
                ->sum('due_amount');



            return response()->json([
                'success' => true,
                'message' => 'Supplier due report retrieved successfully.',
                'data' => $suppliers,
                'total_due' => round(
                    (float) $grandTotalDue,
                    2
                ),
                'total_suppliers' =>
                    (int) $suppliers->total(),

            ]);

        } catch (\Throwable $e) {

            Log::error(
                'Supplier Due Report Error',
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve supplier due report.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Profit & Loss report based on sold stock, with per-product breakdown.
     *
     * GET /api/reports/profit-loss
     */
    public function profitAndLoss(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateRequest($request);

            $perPage = min((int) ($validated['per_page'] ?? 20), 100);
            $page    = (int) ($validated['page'] ?? 1);

            $baseQuery = $this->buildBaseQuery($validated);

            // ---- Aggregate summary (over the FULL filtered set) ----
            $summary = $this->buildSummary(clone $baseQuery);

            // ---- Product-wise profit/loss breakdown ----
            $productWise = $this->buildProductWiseSummary(clone $baseQuery);

            // ---- Paginated batch-level rows ----
            $stocks = (clone $baseQuery)
                ->select([
                    'id',
                    'product_id',
                    'batch_no',
                    'date',
                    'purchase_price',
                    'sale_price',
                    'stockOut',
                ])
                ->latest('date')
                ->latest('id')
                ->paginate($perPage, ['*'], 'page', $page)
                ->withQueryString();

            $stocks->getCollection()->transform(
                fn (Stock $stock) => $this->transformRow($stock)
            );

            return response()->json([
                'status'  => true,
                'message' => 'Profit & loss report retrieved successfully.',
                'data'    => [
                    'summary'      => $summary,
                    'product_wise' => $productWise,
                    'stocks'       => $stocks,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status'  => false,
                'message' => 'Unable to generate the profit & loss report.',
            ], 500);
        }
    }

    private function validateRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'start_date'  => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date'    => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'search'      => ['nullable', 'string', 'max:100'],
            'per_page'    => ['nullable', 'integer', 'min:5', 'max:100'],
            'page'        => ['nullable', 'integer', 'min:1'],
        ]);

        return $validator->validate();
    }

    /**
     * Shared base query — filters applied once, reused everywhere.
     */
    private function buildBaseQuery(array $filters)
    {
        return Stock::query()
            ->with('product:id,name,sku')
            ->when(
                $filters['product_id'] ?? null,
                fn ($q, $id) => $q->where('product_id', $id)
            )
            ->when(
                $filters['category_id'] ?? null,
                fn ($q, $id) => $q->whereHas(
                    'product',
                    fn ($p) => $p->where('category_id', $id)
                )
            )
            ->when(
                $filters['search'] ?? null,
                fn ($q, $term) => $q->where(function ($q) use ($term) {
                    $q->where('batch_no', 'like', "%{$term}%")
                        ->orWhereHas(
                            'product',
                            fn ($p) => $p->where('name', 'like', "%{$term}%")
                                ->orWhere('sku', 'like', "%{$term}%")
                        );
                })
            )
            ->when(
                $filters['start_date'] ?? null,
                fn ($q, $date) => $q->whereDate('date', '>=', $date)
            )
            ->when(
                $filters['end_date'] ?? null,
                fn ($q, $date) => $q->whereDate('date', '<=', $date)
            )
            ->where('stockOut', '>', 0);
    }

    /**
     * Overall aggregate totals (SQL-side).
     */
    private function buildSummary($query): array
    {
        $row = $query
            ->selectRaw('
                COALESCE(SUM(stockOut), 0)                                 as total_quantity,
                COALESCE(SUM(sale_price * stockOut), 0)                    as total_sales,
                COALESCE(SUM(purchase_price * stockOut), 0)                as total_cost,
                COALESCE(SUM((sale_price - purchase_price) * stockOut), 0) as total_profit,
                COUNT(*)                                                   as total_records
            ')
            ->first();

        $totalSales  = (float) $row->total_sales;
        $totalProfit = (float) $row->total_profit;

        return [
            'total_records'  => (int) $row->total_records,
            'total_quantity' => (int) $row->total_quantity,
            'total_sales'    => round($totalSales, 2),
            'total_cost'     => round((float) $row->total_cost, 2),
            'total_profit'   => round($totalProfit, 2),
            'profit_margin'  => $totalSales > 0
                ? round(($totalProfit / $totalSales) * 100, 2)
                : 0.0,
        ];
    }

    /**
     * Product-wise profit & loss — grouped by product_id,
     * using each batch's own purchase_price / sale_price.
     */
    private function buildProductWiseSummary($query)
    {
        return $query
            ->selectRaw('
                product_id,
                COALESCE(SUM(stockOut), 0)                                 as sold_quantity,
                COALESCE(SUM(sale_price * stockOut), 0)                    as total_sales,
                COALESCE(SUM(purchase_price * stockOut), 0)                as total_cost,
                COALESCE(SUM((sale_price - purchase_price) * stockOut), 0) as total_profit,
                COALESCE(AVG(purchase_price), 0)                           as avg_purchase_price,
                COALESCE(AVG(sale_price), 0)                               as avg_sale_price
            ')
            ->with('product:id,name,sku')
            ->groupBy('product_id')
            ->orderByDesc('total_profit')
            ->get()
            ->map(function ($row) {
                $sales  = round((float) $row->total_sales, 2);
                $cost   = round((float) $row->total_cost, 2);
                $profit = round((float) $row->total_profit, 2);

                return [
                    'product_id'         => $row->product_id,
                    'product'            => $row->product,
                    'sold_quantity'      => (int) $row->sold_quantity,
                    'avg_purchase_price' => round((float) $row->avg_purchase_price, 2),
                    'avg_sale_price'     => round((float) $row->avg_sale_price, 2),
                    'total_sales'        => $sales,
                    'total_cost'         => $cost,
                    'total_profit'       => $profit,
                    'profit_margin'      => $sales > 0 ? round(($profit / $sales) * 100, 2) : 0.0,
                    'status'             => $profit >= 0 ? 'profit' : 'loss',
                ];
            })
            ->values();
    }

    /**
     * Shape a single batch-level stock row for the response.
     */
    private function transformRow(Stock $stock): array
    {
        $purchasePrice = (float) $stock->purchase_price;
        $salePrice     = (float) $stock->sale_price;
        $qty           = (int) $stock->stockOut;

        $sales  = round($salePrice * $qty, 2);
        $cost   = round($purchasePrice * $qty, 2);
        $profit = round(($salePrice - $purchasePrice) * $qty, 2);

        return [
            'id'             => $stock->id,
            'product'        => $stock->product,
            'batch_no'       => $stock->batch_no,
            'date'           => optional($stock->date)->format('Y-m-d') ?? $stock->date,
            'purchase_price' => $purchasePrice,
            'sale_price'     => $salePrice,
            'sold_quantity'  => $qty,
            'sales'          => $sales,
            'cost'           => $cost,
            'profit'         => $profit,
            'profit_margin'  => $sales > 0 ? round(($profit / $sales) * 100, 2) : 0.0,
            'status'         => $profit >= 0 ? 'profit' : 'loss',
        ];
    }

    /**
     * Day-by-day sales/payment report.
     */
    public function dayByDaySaleReport(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateDayByDayRequest($request);

            $perPage = min((int) ($validated['per_page'] ?? 20), 100);
            $page    = (int) ($validated['page'] ?? 1);

            $baseQuery = $this->buildDayByDaySalesQuery($validated);

            // ---- Overall summary (over the FULL filtered range) ----
            $summary = $this->buildDayByDaySummary(clone $baseQuery);

            // ---- Day-wise paginated rows ----
            $days = $this->paginateGroupedDays(clone $baseQuery, $perPage, $page);

            return response()->json([
                'status'  => true,
                'message' => 'Day-by-day sales report retrieved successfully.',
                'data'    => [
                    'summary' => $summary,
                    'days'    => $days,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            Log::error('Day By Day Sale Report Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Unable to generate the day-by-day sales report.',
            ], 500);
        }
    }

    private function validateDayByDayRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date'   => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'per_page'   => ['nullable', 'integer', 'min:5', 'max:100'],
            'page'       => ['nullable', 'integer', 'min:1'],
        ]);

        return $validator->validate();
    }

    /**
     * Base query with date filters applied (no select/group yet).
     */
    private function buildDayByDaySalesQuery(array $filters)
    {
        return OrderPayment::query()
            ->when(
                $filters['start_date'] ?? null,
                fn ($q, $date) => $q->whereDate('paid_at', '>=', $date)
            )
            ->when(
                $filters['end_date'] ?? null,
                fn ($q, $date) => $q->whereDate('paid_at', '<=', $date)
            );
    }

    /**
     * Overall totals across the full filtered range (not just current page).
     */
    private function buildDayByDaySummary($query): array
    {
        $row = $query
            ->selectRaw('
                COALESCE(SUM(amount), 0)      as total_amount,
                COUNT(*)                       as total_payments,
                COUNT(DISTINCT DATE(paid_at))  as total_days
            ')
            ->first();

        $totalAmount = (float) $row->total_amount;
        $totalDays   = (int) $row->total_days;

        return [
            'total_amount'    => round($totalAmount, 2),
            'total_payments'  => (int) $row->total_payments,
            'total_days'      => $totalDays,
            'average_per_day' => $totalDays > 0
                ? round($totalAmount / $totalDays, 2)
                : 0.0,
        ];
    }

    /**
     * Paginate a GROUP BY query correctly.
     *
     * Laravel's ->paginate() on a grouped query miscounts the total
     * (it counts raw rows, not distinct groups), so we get the true
     * group count separately, then apply LIMIT/OFFSET manually.
     */
    private function paginateGroupedDays($query, int $perPage, int $page): LengthAwarePaginator
    {
        // True total = number of distinct days matching the filters.
        $total = (clone $query)
            ->select(DB::raw('DATE(paid_at) as date'))
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->get()
            ->count();

        $items = $query
            ->selectRaw('
                DATE(paid_at) as date,
                COALESCE(SUM(amount), 0) as total_amount,
                COUNT(*) as total_payments
            ')
            ->groupByRaw('DATE(paid_at)')
            ->orderByDesc('date')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn ($row) => [
                'date'           => $row->date,
                'total_amount'   => round((float) $row->total_amount, 2),
                'total_payments' => (int) $row->total_payments,
            ]);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path'     => request()->url(),
                'query'    => request()->query(),
                'pageName' => 'page',
            ]
        );
    }

    public function productByProductSaleReport(Request $request): JsonResponse
    {
        try {
            $validated = Validator::make($request->all(), [
                'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
                'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
                'product_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ])->validate();

            $perPage = (int) ($validated['per_page'] ?? 20);
            $page = (int) ($validated['page'] ?? 1);

            $query = Cart::query()
                ->whereHas('product')
                ->when(
                    $validated['start_date'] ?? null,
                    fn($q, $date) => $q->where('created_at', '>=', $date . ' 00:00:00')
                )
                ->when(
                    $validated['end_date'] ?? null,
                    fn($q, $date) => $q->where('created_at', '<', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00')
                )
                ->when(
                    $validated['product_id'] ?? null,
                    fn($q, $id) => $q->where('product_id', $id)
                )
                ->where(function ($q) {
                    $q->whereNull('returned_quantity')
                    ->orWhere('returned_quantity', '<', DB::raw('quantity'));
                });

            $total = (clone $query)
                ->selectRaw('COUNT(DISTINCT CONCAT(DATE(created_at), "-", product_id)) AS aggregate')
                ->value('aggregate');

            $offset = ($page - 1) * $perPage;

            $sales = $query
                ->with('product:id,name,sku')
                ->select('product_id')
                ->selectRaw('DATE(created_at) AS date')
                ->selectRaw('COUNT(*) AS sale_count')
                ->selectRaw('SUM(quantity - COALESCE(returned_quantity, 0)) AS total_quantity')
                ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_amount')
                ->selectRaw('COALESCE(SUM(discount), 0) AS total_discount')
                ->groupBy(DB::raw('DATE(created_at)'), 'product_id')
                ->orderByDesc('date')
                ->orderBy('product_id')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(fn($row) => [
                    'date' => $row->date,
                    'product_id' => (int) $row->product_id,
                    'product_name' => $row->product?->name ?? 'Unknown Product',
                    'sku' => $row->product?->sku ?? '-',
                    'sale_count' => (int) $row->sale_count,
                    'total_quantity' => (float) $row->total_quantity,
                    'total_amount' => round((float) $row->total_amount, 2),
                    'total_discount' => round((float) $row->total_discount, 2),
                ])
                ->values();

            $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

            return response()->json([
                'status' => true,
                'message' => 'Date-wise product sales report retrieved successfully.',
                'data' => [
                    'data' => $sales,
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => (int) $total,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $sales->count(), $total),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to generate product sales report.',
            ], 500);
        }
    }
}
