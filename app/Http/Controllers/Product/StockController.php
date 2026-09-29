<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\Product;
use App\Models\Stock;

class StockController extends Controller
{

    public function index()
    {
        try {
            $stocks = Stock::with([
                    'product:id,name,stock_quantity,min_stock,purchase_price,price,discount'
                ])->get();

            return response()->json([
                'success' => true,
                'message' => 'Stock report fetched successfully.',
                'data' => $stocks,
            ], 200);

        } catch (Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock report.',
            ], 500);
        }
    }

    public function stockReport(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'product_id' => 'nullable|integer|exists:products,id',
            'search'     => 'nullable|string|max:100',
            'per_page'   => 'nullable|integer|min:10|max:100',
        ]);

        try {
            $startDate  = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
            $endDate    = $validated['end_date'] ?? now()->toDateString();
            $perPage    = $validated['per_page'] ?? 20;
            $productId  = $validated['product_id'] ?? null;
            $search     = isset($validated['search']) ? trim($validated['search']) : null;

            // Base stock query
            $baseQuery = Stock::query()->whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate);

            // Product filter
            if ($productId) {
                $baseQuery->where('product_id', $productId);
            }

            // Product search
            if ($search) {
                $escapedSearch = str_replace(
                    ['\\', '%', '_'],
                    ['\\\\', '\%', '\_'],
                    $search
                );

                $baseQuery->where(function ($query) use ($escapedSearch) {
                    $query->where('reg', 'like', "%{$escapedSearch}%")
                        ->orWhereHas('product', function ($query) use ($escapedSearch) {
                            $query->where('name', 'like', "%{$escapedSearch}%");
                        });
                });
            }

            // Stock report
            $stocks = (clone $baseQuery)
                ->with([
                    'product:id,name,stock_quantity,min_stock,purchase_price,price,discount',
                ])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();

            // Stock summary
            $summary = (clone $baseQuery)
                ->selectRaw('
                    COALESCE(SUM(stockIn), 0) AS total_stock_in,
                    COALESCE(SUM(stockOut), 0) AS total_stock_out,
                    COALESCE(SUM(stockIn - stockOut), 0) AS net_stock
                ')
                ->first();

            // Financial summary
            $financialSummary = (clone $baseQuery)
                ->join('products', 'stocks.product_id', '=', 'products.id')
                ->selectRaw('
                    COALESCE(SUM(stocks.stockIn * COALESCE(products.purchase_price, 0)), 0) AS total_purchase_value,
                    COALESCE(SUM(stocks.stockOut * COALESCE(products.purchase_price, 0)), 0) AS total_stock_out_cost,
                    COALESCE(SUM(stocks.stockOut * COALESCE(products.price, 0)), 0) AS total_stock_out_sales_value
                ')
                ->first();

            // Response
            return response()->json([
                'success' => true,
                'message' => 'Stock report fetched successfully.',
                'filters' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'product_id' => $productId,
                    'search' => $search,
                    'per_page' => $perPage,
                ],
                'summary' => [
                    'total_stock_in' => (int) $summary->total_stock_in,
                    'total_stock_out' => (int) $summary->total_stock_out,
                    'net_stock' => (int) $summary->net_stock,
                    'total_purchase_value' => round((float) $financialSummary->total_purchase_value, 2),
                    'total_stock_out_cost' => round((float) $financialSummary->total_stock_out_cost, 2),
                    'total_stock_out_sales_value' => round((float) $financialSummary->total_stock_out_sales_value, 2),
                ],
                'data' => $stocks,
            ], 200);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stock report.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request, int $id)
    {
        $validated = $request->validate([
            'stock' => ['required', 'numeric', 'min:1'],
            'purchasePrice' => ['required', 'numeric', 'min:0'],
            'salePrice' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            return DB::transaction(function () use ($validated, $id) {
                $product = Product::whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found.',
                    ], 404);
                }

                $stockQty = (float) $validated['stock'];
                $purchasePrice = round((float) $validated['purchasePrice'], 2);
                $salePrice = round((float) $validated['salePrice'], 2);

                if ($salePrice < $purchasePrice) {
                    throw ValidationException::withMessages([
                        'salePrice' => [
                            'Sale price cannot be lower than purchase price.',
                        ],
                    ]);
                }

                do {
                    $reg = 'STK-' . strtoupper(Str::random(10));
                } while (Stock::where('reg', $reg)->exists());

                $product->increment('stock_quantity', $stockQty);
                $product->update([
                    'purchase_price' => $purchasePrice,
                    'price' => $salePrice,
                ]);

                $stock = Stock::create([
                    'product_id' => $product->id,
                    'batch_no' => null,
                    'reg' => $reg,
                    'date' => now()->toDateString(),
                    'purchase_price' => $purchasePrice,
                    'sale_price' => $salePrice,
                    'stockIn' => $stockQty,
                    'stockOut' => 0,
                    'expiry_date' => null,
                    'remark' => 'Manual stock addition',
                    'status' => 'active',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Product stock added successfully.',
                    'data' => [
                        'product' => $product->fresh(),
                        'stock' => $stock,
                    ],
                ], 201);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to add product stock.', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add product stock.',
            ], 500);
        }
    }
}
