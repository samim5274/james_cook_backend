<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

use App\Models\Product;
use App\Models\ProductRating;
use App\Models\ProductRatingImage;
use App\Models\Cart;
use App\Models\Order;

class RatingController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        try {

            $perPage = $validated['per_page'] ?? 10;

            $query = ProductRating::query()
                ->select([
                    'id',
                    'product_id',
                    'user_id',
                    'rating',
                    'title',
                    'review',
                    'created_at',
                ])
                ->with([
                    'user:id,name',
                    'images:id,product_rating_id,image',
                ])
                ->where('is_approved', true);

            if (!empty($validated['product_id'])) {
                $query->where('product_id', $validated['product_id']);
            }

            $ratings = $query
                ->latest('created_at')
                ->paginate($perPage)
                ->withQueryString();

            return response()->json([
                'success' => true,
                'message' => 'Ratings retrieved successfully.',
                'data'    => $ratings,
                'meta'    => [
                    'current_page' => $ratings->currentPage(),
                    'last_page'    => $ratings->lastPage(),
                    'per_page'     => $ratings->perPage(),
                    'from'         => $ratings->firstItem(),
                    'to'           => $ratings->lastItem(),
                    'total'        => $ratings->total(),
                    'has_more'     => $ratings->hasMorePages(),
                ]
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Product Rating Fetch Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'request' => $request->all(),
                'user_id' => auth()->id(),
                'trace'   => config('app.debug') ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ratings.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function getProductRating(Request $request, $product_id)
    {
        $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        try {

            $perPage = $validated['per_page'] ?? 10;

            $query = ProductRating::query()
                ->with([
                    'user:id,name',
                    'images:id,product_rating_id,image',
                ])
                ->where('is_approved', true);

            $query->where('product_id', $product_id);

            $ratings = $query
                ->latest('created_at')
                ->paginate($perPage)
                ->withQueryString();

            return response()->json([
                'success' => true,
                'message' => 'Ratings retrieved successfully.',
                'data'    => $ratings,
                'meta'    => [
                    'current_page' => $ratings->currentPage(),
                    'last_page'    => $ratings->lastPage(),
                    'per_page'     => $ratings->perPage(),
                    'from'         => $ratings->firstItem(),
                    'to'           => $ratings->lastItem(),
                    'total'        => $ratings->total(),
                    'has_more'     => $ratings->hasMorePages(),
                ]
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Product Rating Fetch Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'request' => $request->all(),
                'user_id' => auth()->id(),
                'trace'   => config('app.debug') ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ratings.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = Validator::make($request->all(), [
            'product_id' => ['required', 'exists:products,id'],
            'rating'     => ['required', 'integer', 'between:1,5'],
            'title'      => ['nullable', 'string', 'max:255'],
            'review'     => ['nullable', 'string', 'max:5000'],
            'images'     => ['nullable', 'array', 'max:4'],
            'images.*'   => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ])->validate();

        try {

            $cartItem = Cart::query()
                ->select('reg')
                ->where('user_id', Auth::id())
                ->where('product_id', $validated['product_id'])
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have not purchased this product.',
                ], 403);
            }

            $isDelivered = Order::query()
                ->where('reg', $cartItem->reg)
                ->where('status', Order::STATUS_DELIVERED)
                ->exists();

            if (!$isDelivered) {
                return response()->json([
                    'success' => false,
                    'message' => 'This product has not been delivered yet.',
                ], 403);
            }

            return DB::transaction(function () use ($validated, $request) {

               $alreadyReviewed = ProductRating::query()
                    ->where('user_id', Auth::id())
                    ->where('product_id', $validated['product_id'])
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyReviewed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already submitted a review for this product.',
                    ], 422);
                }

                $rating = ProductRating::create([
                    'product_id'        => $validated['product_id'],
                    'user_id'           => Auth::id(),
                    'rating'            => $validated['rating'],
                    'title'             => $validated['title'] ?? null,
                    'review'            => $validated['review'] ?? null,
                    'verified_purchase' => true,
                    'is_approved'       => true, // make it from admin
                    'is_featured'       => false,
                    'helpful_count'     => 0,
                    'unhelpful_count'   => 0,
                ]);

                if ($request->hasFile('images')) {
                    $this->storeRatingImages(
                        $request->file('images'),
                        $rating
                    );
                }

                $rating->load([
                    'user:id,name,photo',
                    'images',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Review submitted successfully.',
                    'data' => $rating,
                ],201);

            });

        } catch (QueryException $e) {
            Log::error('Product review database error.', [
                'user_id' => Auth::id(),
                'product_id' => $validated['product_id'] ?? null,
                'exception' => $e,
            ]);

            if (($e->errorInfo[0] ?? null) === '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already submitted a review for this product.',
                ], 422);
            }

            if (($e->errorInfo[1] ?? null) === 1062) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already submitted a review for this product.',
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to submit your review at this moment. Please try again later.',
            ], 500);
        } catch (\Throwable $e) {
            Log::error('Product review creation failed.', [
                'user_id' => Auth::id(),
                'product_id' => $validated['product_id'] ?? null,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Unable to submit your review at this moment. Please try again later.',
            ], 500);
        }
    }

    private function storeRatingImages(array $images, ProductRating $rating): void
    {
        $imageData = [];

        foreach ($images as $index => $image) {

            $path = $image->store('product-ratings', 'public');

            $imageData[] = [
                'product_rating_id' => $rating->id,
                'image'             => $path,
                'position'          => $index,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }

        if (!empty($imageData)) {
            ProductRatingImage::insert($imageData);
        }
    }
}
