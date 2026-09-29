<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

use App\Models\User;
use App\Services\PointService;
use App\Services\RegGenerator;
use App\Models\Order;
use App\Models\Product;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\PointTransaction;
use App\Models\Transaction;
use App\Models\CustomerAddress;
use App\Models\ShippingZone;

class CustomerController extends Controller
{
    public function getAddress()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user',
                ], 401);
            }

            $addresses = CustomerAddress::with([
                'division:id,name,bn_name',
                'district:id,division_id,name,bn_name',
                'upazila:id,district_id,name,bn_name',
                'policeStation:id,upazila_id,name,bn_name',
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

            // Delivery Charge Attach
            $addresses->transform(function ($address) {

                $shippingZone = ShippingZone::where('district_id', $address->district_id)
                    ->where('is_active', true)
                    ->first();

                $address->delivery_charge = $shippingZone?->delivery_charge ?? 120;
                $address->cod_charge = $shippingZone?->cod_charge ?? 0;
                $address->estimated_days = $shippingZone?->max_delivery_days ?? 2;

                return $address;
            });

            return response()->json([
                'success' => true,
                'message' => $addresses->isEmpty()
                    ? 'No saved addresses found.'
                    : 'Customer addresses retrieved successfully.',
                'data' => $addresses,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Customer Address Fetch Failed', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while retrieving addresses.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function createAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|in:Home,Office,Other',
            'recipient_name' => 'required|string|max:100',
            'phone' => ['required', 'regex:/^(?:\+88)?01[3-9]\d{8}$/'],
            'division_id' => 'required|exists:divisions,id',
            'district_id' => 'required|exists:districts,id',
            'upazila_id' => 'required|exists:upazilas,id',
            'police_station_id' => 'nullable|exists:police_stations,id',
            'address' => 'required|string|max:500',
            'postal_code' => 'nullable|string|max:20',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {

            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $isDefault = $request->boolean('is_default');

            if (!CustomerAddress::where('user_id', $user->id)->exists()) {
                $isDefault = true;
            }

            if ($isDefault) {
                CustomerAddress::where('user_id', $user->id)
                    ->update([
                        'is_default' => false
                    ]);
            }

            $address = CustomerAddress::create([
                'user_id' => $user->id,
                'label' => $request->label,
                'recipient_name' => $request->recipient_name,
                'phone' => $request->phone,
                'division_id' => $request->division_id,
                'district_id' => $request->district_id,
                'upazila_id' => $request->upazila_id,
                'police_station_id' => $request->police_station_id,
                'address' => $request->address,
                'postal_code' => $request->postal_code,
                'is_default' => $isDefault,
            ]);

            $address->load([
                'division:id,name',
                'district:id,name',
                'upazila:id,name',
                'policeStation:id,name'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Address created successfully.',
                'data' => $address
            ], 201);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteAddress($id)
    {
        try {

             $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }

            $address = CustomerAddress::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found.'
                ], 404);
            }

            $address->delete();

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully.'
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'              => ['required','string','max:255'],
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

    public function treeUser(){
        $root = User::with(['leftChild', 'rightChild'])
                ->where('role', 'super_admin')
                ->first();

        if(!$root){
            return response()->json([
                'success' => false,
                'message' => 'No root user found',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tree fetched successfully',
            'data' => $root
        ]);
    }

    public function getUsers(Request $request){
        try {
            $authUser = $request->user();

            $underIds = $this->getAllUnderUserIds($authUser->id);

            // include my id also
            $allIds = array_merge([$authUser->id], $underIds);

            $users = User::with(['referrer', 'leftChild', 'rightChild'])
                    ->where('is_match', 0)
                    ->whereNotIn('role', ['admin', 'super_admin'])
                    // ->whereIn('id', $allIds) // ata current user ar under ar all faka user
                    ->latest()
                    ->get();

            return response()->json([
                'success' => true,
                'message' => 'Fetched all admin users',
                'data' => $users,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function getAllUnderUserIds($userId)
    {
        $ids = [];
        $queue = [$userId];

        while (!empty($queue)) {
            $currentIds = $queue;

            // get all children in one query
            $children = User::whereIn('refer_id', $currentIds)
                ->pluck('id')
                ->toArray();

            if (empty($children)) {
                break;
            }

            $ids = array_merge($ids, $children);

            // next loop
            $queue = $children;
        }

        return $ids;
    }

    public function createUser(Request $request, PointService $pointService)
    {
        $validated = $request->validate([
            'name'              => ['required','string','max:255'],

            'email'             => ['required','email','max:255','unique:users,email'],
            'phone'             => ['required','string','max:30','unique:users,phone'],
            'national_id'       => ['required', 'string', 'max:50', 'unique:users,national_id'], // Added unique

            'dob'               => ['nullable','date'],
            'gender'            => ['nullable','in:male,female,other'],
            'blood_group'       => ['nullable','string','max:10'],
            'present_address'   => ['nullable','string','max:500'],
            'permanent_address' => ['nullable','string','max:500'],
            'religion'          => ['nullable','string','max:50'],
            'photo'             => ['nullable','image','max:2048'],
            'refer_id'          => ['required','string'],
            'product_id'        => ['required', 'exists:products,id'],

            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->mixedCase(),
            ],

            'root_user_id'      => ['required','exists:users,id'],
            'position'          => ['required','in:left,right'],
        ], [
            // Custom Messages
            'name.required'             => 'The user name is required.',
            'email.required'            => 'Email address is required.',
            'email.unique'              => 'This email is already registered in the system.',

            'phone.required'            => 'Phone number is required.',
            'phone.unique'              => 'This phone number is already in use.',

            'national_id.required'      => 'National ID (NID) number is required.',
            'national_id.unique'        => 'This National ID (NID) has already been registered.',

            'product_id.required'       => 'Please select a package or product.',
            'product_id.exists'         => 'The selected product is invalid.',

            'password.required'         => 'Password is required.',
            'password.confirmed'        => 'Password and confirmation do not match.',
            'password.min'              => 'Password must be at least 8 characters long.',

            'root_user_id.required'     => 'Placement user ID is required.',
            'root_user_id.exists'       => 'The selected root user does not exist.',

            'position.required'         => 'Please select a placement position (Left/Right).',
            'position.in'               => 'Position must be either Left or Right.',

            'photo.image'               => 'The file must be an image.',
            'photo.max'                 => 'The image size must not exceed 2MB.',
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

    public function getAuthUser(Request $request)
    {
        try {
            $authUser = $request->user();

            return response()->json([
                'success' => true,
                'message' => 'Fetched logged in user',
                'data' => $authUser,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRootUsers(Request $request)
    {
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
        if (!$rootUser->parent_id) return false;

        if ($rootUser->parent_id == $user->id) return true;

        $parent = User::find($rootUser->parent_id);

        return $parent ? $this->isDescendant($parent, $user) : false;
    }

    public function getOrders(){
        try{
            $userId = Auth::id();
            $orders = Order::with('user')->where('user_id', $userId)->latest()->get();

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

    public function editUser($id)
    {
        try {
            // Validate ID (optional but good practice)
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid user ID',
                ], 400);
            }

            // Find user
            $user = User::find($id);

            // Not found
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $user->dob = $user->dob ? \Carbon\Carbon::parse($user->dob)->format('Y-m-d') : null;

            // Success
            return response()->json([
                'success' => true,
                'message' => 'User fetched successfully',
                'data' => $user,
            ], 200);

        } catch (\Exception $e) {
            // Server error handling
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        if (!is_numeric($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user ID',
            ], 400);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $validated = $request->validate([
            'name'        => ['required','string','max:255'],

            'email' => [
                'required','email','max:255',
                Rule::unique('users','email')->ignore($user->id)
            ],

            'phone' => [
                'required','string','max:30',
                Rule::unique('users','phone')->ignore($user->id)
            ],

            'national_id' => [
                'required','string','max:50',
                Rule::unique('users','national_id')->ignore($user->id)
            ],

            'dob'               => ['nullable','date'],
            'gender'            => ['nullable','in:male,female,other,Male,Female,Other'],
            'blood_group'       => ['nullable','string','max:10'],
            'present_address'   => ['nullable','string','max:500'],
            'permanent_address' => ['nullable','string','max:500'],
            'religion'          => ['nullable','string','max:50'],
            'is_active'         => ['nullable'],
            'photo'             => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],

            'password' => [
                'nullable',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->mixedCase(),
            ],
        ]);


        try {
            DB::beginTransaction();

            // prepare data
            $data = collect($validated)->except(['password','photo'])->toArray();

            // ensure boolean
            $data['is_active'] = $request->boolean('is_active');

            // password update (optional)
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            // photo update + old delete
            if ($request->hasFile('photo')) {

                // delete old
                if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                    Storage::disk('public')->delete($user->photo);
                }

                $file = $request->file('photo');
                $path = $file->store('users', 'public');

                $data['photo'] = $path;
            }

            // update user
            $user->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'data' => $user
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Update failed.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function storeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'user_id'    => 'required|string|min:4|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try{

            return DB::transaction(function () use ($request) {
                /*
                |--------------------------------------------------------------------------
                | Find User
                |--------------------------------------------------------------------------
                */

                $user = User::where('user_id', $request->user_id)->first();

                if (!$user) {
                    throw new \Exception('User not found');
                }

                /*
                |--------------------------------------------------------------------------
                | Generate Order Registration
                |--------------------------------------------------------------------------
                */

                $reg = RegGenerator::generateOrderReg($user->id);

                if (!$reg) {
                    throw new \Exception('Reg generation failed');
                }

                /*
                |--------------------------------------------------------------------------
                | Product Fetch
                |--------------------------------------------------------------------------
                */

                $product = Product::find($request->product_id);
                if (!$product) {
                    throw new \Exception('Product not found');
                }

                /*
                |--------------------------------------------------------------------------
                | Variant Handling
                |--------------------------------------------------------------------------
                */
                $variant = ProductVariant::where('product_id', $product->id)
                    ->orderBy('id', 'asc')
                    ->first();

                if (!$variant) {
                    throw new \Exception('No variant found for product');
                }

                /*
                |--------------------------------------------------------------------------
                | Price Calculation
                |--------------------------------------------------------------------------
                */
                $basePrice = $variant->price ?? $product->price;
                $discount  = $variant->discount ?? 0;

                $finalPrice = max($basePrice - $discount, 0);

                /*
                |--------------------------------------------------------------------------
                | Check Existing Cart Item
                |--------------------------------------------------------------------------
                */
                $cartItem = Cart::where('reg', $reg)
                    ->where('product_id', $product->id)
                    ->where('variant_id', $variant->id)
                    ->first();

                if ($cartItem) {
                    throw new \Exception('Item already exists in cart.');
                } else {
                    DB::transaction(function () use ($request, $user, $product, $variant, $reg, $finalPrice, $discount) {

                        Cart::create([
                            'reg'        => $reg,
                            'user_id'    => $user->id,
                            'product_id' => $product->id,
                            'variant_id' => $variant->id,
                            'quantity'   => 1,
                            'price'      => $finalPrice,
                            'discount'   => $discount,
                            'point'      => $product->point ?? 0,
                        ]);

                        $this->ensureOrderExists($user, $reg);
                    });
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Product added to order successfully.',
                    'data' => [
                        'reg' => $reg,
                        'order_id' => $order->id ?? null,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('ORDER STORE ERROR', [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

}
