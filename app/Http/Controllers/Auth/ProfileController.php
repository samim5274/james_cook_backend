<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Services\PointService;
use App\Services\RegGenerator;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'              => ['required','string','max:255'],
            'email'             => ['required','email','max:255'],
            'phone'             => ['nullable','string','max:30', Rule::unique('users','phone')->ignore($user->id)],
            'dob'               => ['nullable','date'],
            'gender'            => ['nullable','in:male,female,other'],
            'blood_group'       => ['nullable','string','max:10'],
            'present_address'   => ['nullable','string','max:500'],
            'permanent_address' => ['nullable','string','max:500'],
            'national_id'       => ['nullable','string','max:50'],
            'religion'          => ['nullable','string','max:50'],
            'photo'             => ['nullable','image','max:2048'],
        ]);

        if ($request->hasFile('photo')) {
            // old photo delete
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            $path = $request->file('photo')->store('users', 'public');
            $data['photo'] = $path;
        }

        // profile completed simple rule
        $data['is_profile_completed'] = !empty(trim($data['name'] ?? $user->name ?? '')) && !empty(trim($data['phone'] ?? $user->phone ?? ''));

        $user->update($data);

        $fresh = $user->fresh();
        $fresh->photo_url = $fresh->photo ? asset('storage/'.$fresh->photo) : null;

        return response()->json([
            'success' => true,
            'data' => $fresh,
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated.',
        ]);
    }

    public function treeUserLogRoot(Request $request)
    {
        $rootId = $request->query('root_id');

        $withRelations = [
            'leftChildRecursive',
            'rightChildRecursive',
            'pointTransactions' => function ($query) {
                $query->latest();
            }
        ];

        if ($rootId) {
            $root = User::with($withRelations)->find($rootId);
        } else {
            $root = User::with($withRelations)
                ->where('role', 'super_admin')
                ->first();
        }

        if (!$root) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $root
        ]);
    }

    public function treeUser(){
    }

    public function getUsers(){
        try {
            $users = User::with(['referrer', 'leftChild', 'rightChild'])
                    ->where('is_match', 0)
                    ->where('role', '!=', 'super_admin')
                    ->latest()
                    ->get();

            return response()->json([
                'success' => true,
                'message' => 'Fetched all admin users',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllUsers(){
        try {
            $users = User::with(['referrer', 'leftChild', 'rightChild'])
                    // ->where('is_match', 0)
                    ->where('role', '!=', 'super_admin')
                    ->latest()
                    ->get();

            return response()->json([
                'success' => true,
                'message' => 'Fetched all admin users',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getProducts()
    {
        try {
            $products = Product::where('point', '>=', 100)
                    ->latest()
                    ->get();

            return response()->json([
                'success' => true,
                'message' => 'Fetched all 100 points products',
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function createUser(Request $request, PointService $pointService)
    // {
    //     // Validate input
    //     $data = $request->validate([
    //         'name'              => ['required','string','max:255'],
    //         'email'             => ['required','email','max:255','unique:users,email'],
    //         'phone'             => ['nullable','string','max:30','unique:users,phone'],
    //         'dob'               => ['nullable','date'],
    //         'gender'            => ['nullable','in:male,female,other'],
    //         'blood_group'       => ['nullable','string','max:10'],
    //         'present_address'   => ['nullable','string','max:500'],
    //         'permanent_address' => ['nullable','string','max:500'],
    //         'national_id'       => ['nullable','string','max:50'],
    //         'religion'          => ['nullable','string','max:50'],
    //         'photo'             => ['nullable','image','max:2048'],
    //         'refer_id'          => ['required','string'],
    //         'product_id'        => ['required', 'exists:products,id'],

    //         // Password Validation
    //         'password' => [
    //             'required',
    //             'confirmed', // password_confirmation check করবে
    //             Password::min(8)
    //                 ->letters()     // Character
    //                 ->numbers()     // Number
    //                 ->symbols()     // Special char
    //                 ->mixedCase(),  // Upper + Lower case
    //         ],

    //         'root_user_id'      => ['required','exists:users,id'],
    //         'position'          => ['required','in:left,right'],
    //     ]);

    //     // Handle photo upload
    //     if ($request->hasFile('photo')) {
    //         $path = $request->file('photo')->store('users', 'public');
    //         $data['photo'] = $path;
    //     }

    //     // Check reference user
    //     if (!empty($data['refer_id'])) {
    //         $referUser = User::where('user_id', $data['refer_id'])->first();
    //         if (!$referUser) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Reference not found. Please try again.',
    //             ], 422);
    //         }

    //         $data['refer_id'] = $referUser->id;
    //     }

    //     // Profile completed simple rule
    //     $data['is_profile_completed'] = !empty(trim($data['name'])) && !empty(trim($data['phone'] ?? ''));

    //     // Passowr hash make
    //     $data['password'] = Hash::make($data['password']);

    //     // Create new user
    //     $user = User::create($data);

    //     $user->user_id = 'DBMBL' . str_pad($user->id, 3, '0', STR_PAD_LEFT);
    //     $user->save();

    //     // Add photo_url for frontend
    //     $user->photo_url = $user->photo ? asset('storage/'.$user->photo) : null;








    //     // After create user then assign in tree
    //     $userId = $user->id;
    //     $userRootId = $data['root_user_id'];

    //     // prevent self assignment
    //     if ($userId == $userRootId) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'User and Root User cannot be the same.',
    //         ], 422);
    //     }

    //     try{
    //         DB::transaction(function () use ($data, $userId, $userRootId, $pointService) {

    //             $position = $data['position'];

    //             $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();
    //             $rootUser = User::where('id', $userRootId)->lockForUpdate()->firstOrFail();

    //             // already has parent
    //             if ($user->parent_id) {
    //                 throw new \Exception('User already has a parent.');
    //             }

    //             if ($this->isDescendant($rootUser, $user)) {
    //                 throw new \Exception('Circular reference detected.');
    //             }

    //             // position check
    //             if ($data['position'] === 'left' && $rootUser->left_child_id) {
    //                 throw new \Exception('Left position already occupied.');
    //             }
    //             if ($data['position'] === 'right' && $rootUser->right_child_id) {
    //                 throw new \Exception('Right position already occupied.');
    //             }

    //             // assign parent
    //             $user->parent_id = $rootUser->id;

    //             // assign child to root user
    //             if ($data['position'] == 'left') {
    //                 $rootUser->left_child_id = $user->id;
    //             } else {
    //                 $rootUser->right_child_id = $user->id;
    //             }

    //             // save both
    //             $user->save();
    //             $rootUser->save();

    //             $user->refresh();

    //             // MLM logic
    //             $pointService->referralBonus($user);
    //             $pointService->updateCounts($user);

    //             // Order make for opeing account
    //             $reg = $this->addProductToCartForUser($user, $data['product_id']);

    //         });



    //         return response()->json([
    //             'success' => true,
    //             'message' => 'User assigned successfully.',
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage(),
    //         ], 422);
    //     }
    // }

    public function createUser(Request $request, PointService $pointService)
    {
        $validated = $request->validate([
            'name'              => ['required','string','max:255'],
            'email'             => ['required','email','max:255','unique:users,email'],
            'phone'             => ['nullable','string','max:30','unique:users,phone'],
            'dob'               => ['nullable','date'],
            'gender'            => ['nullable','in:male,female,other'],
            'blood_group'       => ['nullable','string','max:10'],
            'present_address'   => ['nullable','string','max:500'],
            'permanent_address' => ['nullable','string','max:500'],
            'national_id'       => ['nullable','string','max:50'],
            'religion'          => ['nullable','string','max:50'],
            'photo'             => ['nullable','image','max:2048'],
            'refer_id'          => ['required','string'],
            'product_id'        => ['required', 'exists:products,id'],

            // Password Validation
            'password' => [
                'required',
                'confirmed', // password_confirmation check করবে
                Password::min(8)
                    ->letters()     // Character
                    ->numbers()     // Number
                    ->symbols()     // Special char
                    ->mixedCase(),  // Upper + Lower case
            ],

            'root_user_id'      => ['required','exists:users,id'],
            'position'          => ['required','in:left,right'],
        ]);

        $photoPath = null;

        try {
            return DB::transaction(function () use ($request, $validated, $pointService, &$photoPath) {

                // Find refer user
                $referUser = User::where('user_id', $validated['refer_id'])->firstOrFail();

                // Upload photo
                if ($request->hasFile('photo')) {
                    $photoPath = $request->file('photo')->store('users', 'public');
                }

                // user create
                $user = User::create(array_merge($validated, [
                    'password' => Hash::make($validated['password']),
                    'refer_id' => $referUser->id,
                    'photo'    => $photoPath,
                    'is_profile_completed' => !empty($validated['phone']),
                ]));

                // uniqure user id
                $user->update([
                    'user_id' => 'DBMBL' . str_pad($user->id, 4, '0', STR_PAD_LEFT)
                ]);

                // assign tree
                $this->assignToTree($user, $validated['root_user_id'], $validated['position']);

                // MLM update
                // $pointService->referralBonus($user); // ata active korte hole add more value: order->reg
                $pointService->updateCounts($user, $validated['product_id']);

                // Product order
                $this->addProductToCartForUser($user, $validated['product_id']);

                return response()->json([
                    'success' => true,
                    'message' => 'User created and assigned to tree successfully.',
                    'user_id' => $user->user_id
                ], 201);
            });

        } catch (Exception $e) {
            if ($photoPath) Storage::disk('public')->delete($photoPath);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    private function assignToTree(User $user, $rootUserId, $position)
    {
        // self assign check
        if ($user->id == $rootUserId) {
            throw new Exception('A user cannot be their own root parent.');
        }

        // root user lock Race Condition
        $rootUser = User::where('id', $rootUserId)->lockForUpdate()->firstOrFail();

        // possition check
        if ($position === 'left' && $rootUser->left_child_id) {
            throw new Exception('The left position is already occupied.');
        }

        if ($position === 'right' && $rootUser->right_child_id) {
            throw new Exception('The right position is already occupied.');
        }

        // method
        if (method_exists($this, 'isDescendant') && $this->isDescendant($rootUser, $user)) {
            throw new Exception('Circular reference detected.');
        }

        // relation update
        $user->parent_id = $rootUser->id;
        $user->save();

        if ($position === 'left') {
            $rootUser->left_child_id = $user->id;
        } else {
            $rootUser->right_child_id = $user->id;
        }

        $rootUser->save();
    }

    public function getRootUsers(Request $request){
        try {
            $authUser = $request->user();

            // $users = User::whereKeyNot($authUser->id)
            //         ->where(function ($query) {
            //             $query->whereNull('left_child_id')
            //                 ->orWhereNull('right_child_id');
            //         })
            //         ->latest()
            //         ->get();

            $users = User::where('is_match', 0)->latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'Fetched all admin users',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignTree(Request $request)
    {
        $data = $request->validate([
            'user_id'      => ['required','exists:users,id'],
            'root_user_id' => ['required','exists:users,id'],
            'position'     => ['required','in:left,right'],
        ]);

        // prevent self assignment
        if ($data['user_id'] == $data['root_user_id']) {
            return response()->json([
                'success' => false,
                'message' => 'User and Root User cannot be the same.',
            ], 422);
        }

        try{
            DB::transaction(function () use ($data) {

                $user = User::where('id', $data['user_id'])->lockForUpdate()->firstOrFail();
                $rootUser = User::where('id', $data['root_user_id'])->lockForUpdate()->firstOrFail();

                // already has parent
                if ($user->parent_id) {
                    throw new \Exception('User already has a parent.');
                }

                if ($this->isDescendant($rootUser, $user)) {
                    throw new \Exception('Circular reference detected.');
                }

                // position check
                if ($data['position'] === 'left' && $rootUser->left_child_id) {
                    throw new \Exception('Left position already occupied.');
                }
                if ($data['position'] === 'right' && $rootUser->right_child_id) {
                    throw new \Exception('Right position already occupied.');
                }

                // assign parent
                $user->parent_id = $rootUser->id;

                // assign child to root user
                if ($data['position'] == 'left') {
                    $rootUser->left_child_id = $user->id;
                } else {
                    $rootUser->right_child_id = $user->id;
                }

                // save both
                $user->save();
                $rootUser->save();

            });

            return response()->json([
                'success' => true,
                'message' => 'User assigned successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function isDescendant($rootUser, $user)
    {
        $currentRoot = $rootUser;

        while ($currentRoot && $currentRoot->parent_id) {
            if ($currentRoot->parent_id == $user->id) {
                return true;
            }
            $currentRoot = User::find($currentRoot->parent_id);
        }

        return false;
    }

    private function addProductToCartForUser($user, $productId, $variantId = null)
    {
        $reg = RegGenerator::generateOrderReg($user->id);

        if (!$reg) {
            throw new \Exception('Failed to generate cart session.');
        }

        // Lock product
        $product = Product::lockForUpdate()->findOrFail($productId);

        // ======================
        // Variant Handling
        // ======================
        $variant = null;

        if (!empty($variantId)) {
            $variant = ProductVariant::lockForUpdate()
                ->where('id', $variantId)
                ->where('product_id', $product->id)
                ->firstOrFail();
        } else {
            $variant = ProductVariant::where('product_id', $product->id)
                ->orderBy('id', 'asc')
                ->first(); // auto first variant
        }

        // ======================
        // Price Logic
        // ======================
        if ($variant) {
            $basePrice = $variant->price ?? $product->price;
            $variantDiscount = $variant->discount ?? 0;

            $finalPrice = $variantDiscount > 0
                ? $basePrice - $variantDiscount
                : $basePrice;

            $discountAmount = $variantDiscount > 0
                ? $variantDiscount
                : 0;

        } else {
            $finalPrice = $product->price;
            $discountAmount = 0;
        }

        // ======================
        // Find Cart Item
        // ======================
        $query = Cart::where('reg', $reg)
            ->where('product_id', $product->id);

        if ($variant) {
            $query->where('variant_id', $variant->id);
        } else {
            $query->whereNull('variant_id');
        }

        $cartItem = $query->first();

        // ======================
        // Save Cart
        // ======================
        if ($cartItem) {
            $cartItem->increment('quantity', 1);

            // optional update price (if changed)
            $cartItem->update([
                'price' => $finalPrice,
                'discount' => $discountAmount,
            ]);

        } else {
            Cart::create([
                'reg'        => $reg,
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity'   => 1,
                'price'      => $finalPrice,
                'discount'   => $discountAmount,
                'point'      => $product->point ?? 0,
            ]);
        }

        $order = $this->ensureOrderExists($user, $reg);

        return $reg;
    }

    private function ensureOrderExists($user, $reg)
    {
        $order = Order::where('reg', $reg)->first();

        if (!$order) {

            $cartItems = Cart::where('reg', $reg)->get();

            if (!$cartItems->count()) {
                return null;
            }

            $amount = $cartItems->sum(fn($item) => $item->price * $item->quantity);
            $point = $cartItems->sum(fn($item) => $item->point * $item->quantity);
            $discount = $cartItems->sum(fn($item) => $item->discount * $item->quantity);

            $tran_id = uniqid('SSLCZ_');

            $order = Order::create([
                'reg' => $reg,
                'date' => now()->toDateString(),
                'user_id' => $user->id,

                'amount' => $amount,
                'discount' => $discount,
                'payable_amount' => $amount - $discount,
                'currency' => 'BDT',
                'point' => (int) $point,

                'payment_method' => "Cash",
                'transaction_id' => $tran_id,
                'payment_status' => 'Pending',
                'paid_at' => null,

                'status' => 'Pending',

                'contact_number' => $user->phone,
                'shipping_address' => $user->present_address,
            ]);
        }

        return $order;
    }

    public function getRankingUsers(){
        try {

            $data = User::query()
                ->where('role', '!=', 'super_admin')
                ->withSum([
                    'pointTransactions as total_credit' => function ($q) {
                        $q->where('bonus_status', 'credit');
                    }
                ], 'bonus_amount')
                ->withSum([
                    'pointTransactions as total_debit' => function ($q) {
                        $q->where('bonus_status', 'debit');
                    }
                ], 'bonus_amount')
                ->get()
                ->map(function ($user) {
                    $user->wallet_balance = round(($user->total_credit ?? 0) - ($user->total_debit ?? 0), 2);
                    unset($user->total_credit, $user->total_debit);
                    return $user;
                });

            return response()->json([
                'success' => true,
                'message' => 'Users data fetched successfully.',
                'data' => $data,
            ], 200);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching users data.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

}
