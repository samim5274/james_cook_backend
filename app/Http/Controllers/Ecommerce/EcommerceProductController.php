<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

use App\Http\Requests\StoreProductRequest;
use App\Models\User;
use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Division;
use App\Models\District;
use App\Models\Upazila;
use App\Models\PoliceStation;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\ProductRating;
use App\Models\Order;
use App\Models\Cart;
use App\Models\OrderPayment;

class EcommerceProductController extends Controller
{
    public function index(Request $request)
    {
        try{
            // Cache::forget('home:all:v1'); // Product create/update/delete cache forget
            $page = $request->get('page', 1);

            $products = Cache::remember("home:all:v1:page:$page",now()->addMinutes(30), function () {
                return Product::query()
                    ->select([
                        'id',
                        'category_id',
                        'subcategory_id',
                        'brand_id',
                        'name',
                        'slug',
                        'price',
                        'discount',
                        'point',
                        'approval_status',
                        'is_active'
                    ])
                    ->with([
                        'category:id,name,slug',
                        'subcategory:id,name',
                        'brand:id,name',
                        'images:id,product_id,image_path,is_primary',
                    ])
                    ->withAvg('ratings', 'rating')
                    ->withCount('ratings')
                    ->where('is_active', true)
                    ->where('approval_status', true)
                    ->latest('id')
                    ->paginate(50)
                    ->through(function ($product) {

                        $product->images->transform(function ($image) {
                            $image->url = asset('storage/'.$image->image_path);
                            return $image;
                        });

                        return $product;
                    });

            });

            return response()->json([
                'success' => true,
                'message' => 'Products fetched successfully.',
                'data' => $products
            ], 200);

        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch products.',
            ], 500);
        }
    }

    public function categoryGroup()
    {
        try{
            $categories = ProductCategory::where('is_active', 1)
                ->select('id', 'name', 'slug')
                ->get();

            $data = $categories->map(function ($category) {
                $products = Product::with([
                    'category:id,name,slug',
                    'subcategory:id,name',
                    'brand:id,name',
                    'images:id,product_id,image_path,is_primary'
                ])
                ->withAvg('ratings', 'rating')
                ->withCount('ratings')
                ->where('category_id', $category->id)
                ->where('is_active', 1)
                ->where('approval_status', 1)
                ->latest('id')
                ->take(10)
                ->get();

                // Image URL
                $products->each(function ($product) {
                    $product->images->transform(function ($image) {
                        $image->url = asset('storage/' . $image->image_path);
                        return $image;
                    });
                });

                return [
                    'category' => $category,
                    'products' => $products,
                ];
            });

            // $products = Product::with([
            //         'category:id,name,slug',
            //         'subcategory:id,name',
            //         'brand:id,name',
            //         'images:id,product_id,image_path,is_primary'
            //     ])
            //     ->withAvg('ratings', 'rating')
            //     ->withCount('ratings')
            //     ->where('is_active', 1)
            //     ->where('approval_status', 1)
            //     ->inRandomOrder()
            //     ->get()
            //     ->groupBy('category_id')
            //     ->map(function ($items) {
            //         return $items->take(10);
            //     });

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Products fetched successfully.',
            //     'data' => $products
            // ], 200);

            return response()->json([
                'success' => true,
                'message' => 'Products fetched successfully.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Products can not fetched.',
            ], 500);
        }
    }

    public function getDivision(){
        try{
            $division = Cache::remember('public_divisions', now()->addDay(), function () {
                return Division::orderBy('name')->get();
            });
            return response()->json([
                'success' => true,
                'data' => $division
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Division can not fetched.',
            ], 500);
        }
    }

    public function getDistrict(Request $request){
        try{
            $district = Cache::remember(
                'public_districts_' . $request->division_id,
                now()->addDay(),
                function () use ($request) {
                    return District::where('division_id', $request->division_id)
                        ->orderBy('name')
                        ->get();
                }
            );

            return response()->json([
                'success' => true,
                'data' => $district,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'District can not fetched.',
            ], 500);
        }
    }

    public function getUpazila(Request $request){
        try{
            $upazila = Cache::remember(
                'public_upazilas_' . $request->district_id,
                now()->addDay(),
                function () use ($request) {
                    return Upazila::where('district_id', $request->district_id)
                        ->orderBy('name')
                        ->get();
                }
            );
            return response()->json([
                'success' => true,
                'data' => $upazila,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upazila can not fetched.',
            ], 500);
        }
    }

    public function getPoliceStation(Request $request){
        try{
            $policeStation = Cache::remember(
                'public_police_stations_' . $request->upazila_id,
                now()->addDay(),
                function () use ($request) {
                    return PoliceStation::where('upazila_id', $request->upazila_id)
                        ->orderBy('name')
                        ->get();
                }
            );
            return response()->json([
                'success' => true,
                'data' => $policeStation,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'PoliceStation can not fetched.',
            ], 500);
        }
    }

    public function getCategory(){
        try{
            $categories = Cache::remember('public_product_categories', now()->addDay(), function () {
                return ProductCategory::where('is_active', 1)
                    ->orderBy('name')
                    ->get();
            });
            return response()->json([
                'success' => true,
                'data' => $productCategories
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product categories can not fetched.',
            ], 500);
        }
    }

    public function getSubCategory(){
        try{
            $subCategories = Cache::remember('public_product_subcategories', now()->addDay(), function () {
                return ProductSubCategory::with('category:id,name')
                    ->orderBy('name')
                    ->get();
            });
            return response()->json([
                'success' => true,
                'data' => $productSubCategories
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product sub categories can not fetched.',
            ], 500);
        }
    }

    public function getBrand(){
        try{
            $brands = Cache::remember('public_brands', now()->addDay(), function () {
                return Brand::where('is_active', 1)
                    ->orderBy('name')
                    ->get();
            });
            return response()->json([
                'success' => true,
                'data' => $productBrands
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brands can not fetched.',
            ], 500);
        }
    }

    public function show($slug){
        try {
            $product = Product::with([
                'category:id,name',
                'subcategory:id,name',
                'brand:id,name',
                'variants',
                'images'
            ])->where('slug', $slug)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Product fetched successfully.',
                'data' => $product
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Product can not fetched. Error: " . $e->getMessage(),
            ], 500);
        }
    }

    public function getCategoryProducts($id) {
        try {

            $category = ProductCategory::select(
                'id',
                'name',
                'slug',
                'image',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'og_title',
                'og_description',
                'og_image',
                'canonical_url',
                'robots',
                'indexable'
            )->findOrFail($id);

            $products = Product::with([
                'category:id,name',
                'subcategory:id,name',
                'brand:id,name',
                'variants',
                'images'
            ])
            ->withAvg('ratings', 'rating')
            ->withCount('ratings')
            ->where([
                'category_id' => $id,
                'is_active' => 1,
                'approval_status' => 1,
            ])
            ->latest('id')
            ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Category products fetched successfully.',
                'category' => $category,
                'products' => $products,
            ], 200);

        } catch (\Throwable $e) {
             Log::error('Category product fetch failed.', [
                'category_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching category products.',
            ], 500);
        }
    }

    public function otherDetails($user_id)
    {
        try
        {
            $totalOrder = Order::where('user_id', $user_id)->count();

            $totalPoint = Order::where('user_id', $user_id)
                ->where('status', Order::STATUS_DELIVERED)
                ->sum('point');

            $totalRating = ProductRating::where('user_id', $user_id)->count();

            return response()->json([
                'success' => true,
                'message' => 'User details fetched successfully.',
                'data' => [
                    'total_order'  => $totalOrder,
                    'total_point'  => $totalPoint,
                    'total_rating' => $totalRating,
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Failed to fetch user details.', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function userOrderDetails()
    {
        try
        {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user',
                ], 401);
            }

            $orders = Order::where('user_id', $user->id)
                        ->with([
                                'user',
                                'coupon',
                                'division',
                                'district',
                                'upazila',
                                'policeStation',
                                'payment',
                                'items.product'
                            ])
                        ->orderBy('id', 'desc')
                        ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'User details fetched successfully.',
                'data' => $orders
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Failed to fetch user details.', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function orderItemsDetails($reg)
    {
        try
        {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user',
                ], 401);
            }

            $cartItems = Cart::with(['product'])->where('reg', $reg)->get();
            if ($cartItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No order items found.',
                ], 404);
            }

            $orderPayment = OrderPayment::where('order_id', $cartItems[0]->reg)->first();

            return response()->json([
                'success' => true,
                'message' => 'User details fetched successfully.',
                'data' => [
                    'cartItems' => $cartItems,
                    'orderPayment' => $orderPayment,
                ]
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Failed to retrieve order details.', [
                'user_id' => $user->id,
                'reg'     => $reg,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage().' Something went wrong while retrieving order details.',
            ], 500);
        }
    }

}
