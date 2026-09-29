<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\Models\Brand;
use App\Models\Product;

class SearchController extends Controller
{
    public function suggestions(Request $request)
    {
        $q = trim($request->q);

        if (strlen($q) < 2) {
            return response()->json([
                'products'   => [],
                'categories' => [],
                'brands'     => [],
            ]);
        }

        $products = Product::query()->with(['images:id,product_id,image_path'])
            ->select(
                'id',
                'name',
                'slug',
                'price',
                'discount'
            )
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('summary', 'like', "%{$q}%");
            })
            ->where('is_active', 1)
            ->where('approval_status', 1)
            ->limit(10)
            ->get();

        $categories = ProductCategory::query()
            ->select('id', 'name', 'slug')
            ->where('is_active', true)
            ->where('name', 'like', "%{$q}%")
            ->limit(10)
            ->get();

        $brands = Brand::query()
            ->select('id', 'name', 'slug')
            ->where('is_active', true)
            ->where('name', 'like', "%{$q}%")
            ->limit(10)
            ->get();

        return response()->json([
            'products'   => $products,
            'categories' => $categories,
            // 'brands'     => $brands,
        ]);
    }

    // public function search(Request $request)
    // {
    //     $q = trim($request->get('q', ''));

    //     if ($q === '') {
    //         return response()->json([
    //             'data' => [],
    //             'current_page' => 1,
    //             'last_page' => 1,
    //             'total' => 0,
    //             'per_page' => 50,
    //             'from' => 0,
    //             'to' => 0,
    //         ]);
    //     }

    //     $products = Product::query()
    //         ->where('is_active', true)
    //         ->where(function ($query) use ($q) {
    //             $query->where('name', 'like', "%{$q}%")
    //                 ->orWhere('sku', 'like', "%{$q}%")
    //                 ->orWhere('summary', 'like', "%{$q}%")
    //                 ->orWhere('description', 'like', "%{$q}%");
    //         })
    //         ->withAvg('ratings', 'rating')
    //         ->withCount('ratings')
    //         ->where('is_active', 1)
    //         ->where('approval_status', 1)
    //         ->paginate(50)
    //         ->appends($request->query());

    //     return response()->json($products);
    // }

    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
            ]);
        }

        $products = Product::with([
                'category:id,name,slug',
                'subcategory:id,name',
                'brand:id,name',
                'images:id,product_id,image_path,is_primary'
            ])
            ->withAvg('ratings', 'rating')
            ->withCount('ratings')
            ->where('is_active', 1)
            ->where('approval_status', 1)
            ->where(function ($query) use ($q) {

                $query->where('sku', $q)

                    ->orWhere('name', 'LIKE', "{$q}%")

                    ->orWhere('name', 'LIKE', "%{$q}%")

                    ->orWhere('summary', 'LIKE', "%{$q}%")

                    ->orWhere('meta_keywords', 'LIKE', "%{$q}%")

                    ->orWhereHas('category', function ($q2) use ($q) {
                        $q2->where('name', 'LIKE', "%{$q}%");
                    })

                    ->orWhereHas('subcategory', function ($q2) use ($q) {
                        $q2->where('name', 'LIKE', "%{$q}%");
                    })

                    ->orWhereHas('brand', function ($q2) use ($q) {
                        $q2->where('name', 'LIKE', "%{$q}%");
                    })

                    ->orWhere('description', 'LIKE', "%{$q}%");
            })

            ->orderByRaw("
                CASE
                    WHEN sku = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    WHEN name LIKE ? THEN 3
                    WHEN meta_keywords LIKE ? THEN 4
                    WHEN summary LIKE ? THEN 5
                    ELSE 6
                END
            ", [
                $q,
                "{$q}%",
                "%{$q}%",
                "%{$q}%",
                "%{$q}%"
            ])

            ->orderByDesc('total_click')
            ->paginate(50)
            ->appends($request->query());

        // Image URL
        $products->getCollection()->transform(function ($product) {

            $product->images->transform(function ($image) {
                $image->url = asset('storage/' . $image->image_path);
                return $image;
            });

            return $product;
        });

        return response()->json($products);
    }
}
