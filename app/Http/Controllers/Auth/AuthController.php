<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Jobs\UpdateLastLoginJob;
use Exception;

use App\Models\User;
use App\Mail\OTPMail;
use Mail;
use App\Models\Product;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\Order;
use App\Services\PointService;
use App\Services\RegGenerator;

class AuthController extends Controller
{

    public function register(Request $request, PointService $pointService)
    {
        $validated = $request->validate([
            'name'              => ['required','string','max:255'],
            'email'             => ['required','email','max:255','unique:users,email'],
            'phone'             => ['required','string','max:30','unique:users,phone'],
            'dob'               => ['nullable','date'],
            'gender'            => ['nullable','in:male,female,other'],
            'blood_group'       => ['nullable','string','max:10'],
            'present_address'   => ['nullable','string','max:500'],
            'permanent_address' => ['nullable','string','max:500'],
            'photo'             => ['nullable','image','max:2048'],

            // Password Validation
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()     // Character
                    ->numbers()     // Number
                    ->symbols()     // Special char
                    ->mixedCase(),  // Upper + Lower case
            ],
        ]);

        $photoPath = null;

        try {
            return DB::transaction(function () use ($request, $validated, $pointService, &$photoPath) {

                // Upload photo
                if ($request->hasFile('photo')) {
                    $photoPath = $request->file('photo')->store('users', 'public');
                }

                // user create
                $user = User::create(array_merge($validated, [
                    'password' => Hash::make($validated['password']),
                    'photo'    => $photoPath,
                    'is_profile_completed' => !empty($validated['phone']),
                ]));

                $user->update([
                    'user_id' => 'OGVA' . str_pad($user->id, 4, '0', STR_PAD_LEFT)
                ]);

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

    // Old Login
    // public function login(Request $request)
    // {

    //     // 1) Validate (stronger)

    //     $credentials = $request->validate([

    //         'email' => ['required', 'email:rfc,dns', 'max:255'],

    //         'password' => ['required', 'string', 'min:8', 'max:72'],

    //         'device_name' => ['nullable', 'string', 'max:64'], // token name

    //         'remember' => ['nullable', 'boolean'],

    //     ]);


    //     // Normalize email

    //     $email = Str::lower(trim($credentials['email']));


    //     // 2) Rate limit keys (email + ip)

    //     $emailKey = 'login:email:' . $email;

    //     $ipKey    = 'login:ip:' . $request->ip();


    //     // 3) Check limits

    //     if (

    //         RateLimiter::tooManyAttempts($emailKey, 3) ||

    //         RateLimiter::tooManyAttempts($ipKey, 20)

    //     ) {

    //         return response()->json([

    //             'message' => "Too many login attempts. Please try again later.",

    //         ], 429);

    //     }


    //     // 4) Find user (no info leak)

    //     $user = User::where('email', $email)->first();


    //     // If user invalid OR password invalid → hit limit + generic error

    //     if (! $user || ! Hash::check($credentials['password'], $user->password)) {

    //         RateLimiter::hit($emailKey, 60);

    //         RateLimiter::hit($ipKey, 60);


    //         return response()->json([

    //             'message' => "Invalid login credentials.",

    //         ], 401);

    //     }


    //     // Optional: block/inactive check (if you have this column)

    //     if ($user->is_active === 0) {

    //         return response()->json([

    //             'message' => "Your account is disabled.",

    //         ], 403);

    //     }


    //     // 5) Successful login → clear limits

    //     RateLimiter::clear($emailKey);

    //     RateLimiter::clear($ipKey);


    //     // Optional: update last login data (add columns if needed)

    //     // $user->forceFill([

    //     //     'last_login_at' => now(),

    //     //     'last_login_ip' => $request->ip(),

    //     // ])->save();


    //     $remember = (bool)($credentials['remember'] ?? false);

    //     $user->setRememberToken($remember ? Str::random(60) : null);

    //     $user->saveQuietly();


    //     UpdateLastLoginJob::dispatch($user->id, $request->ip());


    //     $remember = (bool)($credentials['remember'] ?? false);

    //     $user->setRememberToken($remember ? Str::random(60) : null);

    //     $user->saveQuietly(); // avoid events & slow triggers


    //     // Optional: revoke old tokens (single-device login)

    //     // $user->tokens()->delete();



    //     // 6) Create token with abilities

    //     // $deviceName = $credentials['device_name'] ?? 'api-token';

    //     $deviceName = $request->userAgent() ?? 'unknown-device';

    //     $token = $user->createToken($deviceName, ['*'])->plainTextToken;


    //     return response()->json([

    //         'message' => 'Login successful.',

    //         'user' => $user,

    //         'token' => $token,

    //     ], 200);

    // }

    public function login(Request $request)
    {
        // 1) Validate (stronger)
        $validated = $request->validate([
            'email'       => ['required', 'string', 'email:rfc,dns', 'max:255'],
            'password'    => ['required', 'string', 'min:8'],
            'remember'    => ['nullable', 'boolean'],
        ]);

        // Normalize email
        $email = Str::lower(trim($validated['email']));

        // =======================
        $value = $request->input('website_field_9xk2');

        if (!empty($value)) {
            Log::channel('security')->warning('Bot detected (honeypot)', [
                'ip' => $request->ip(),
                'value' => $value,
                'user_agent' => $request->userAgent(),
            ]);

            abort(403);
        }
        // =======================

        // 2) Rate limit keys (email + ip)
        $emailKey = 'login:email:' . sha1($email);
        $ipKey    = 'login:ip:' . $request->ip();

        $emailMaxAttempts = 5;
        $ipMaxAttempts    = 30;

        // 3) Check limits
        if (
            RateLimiter::tooManyAttempts($emailKey, $emailMaxAttempts) ||
            RateLimiter::tooManyAttempts($ipKey, $ipMaxAttempts)
        ) {
            $seconds = max(
                RateLimiter::availableIn($emailKey),
                RateLimiter::availableIn($ipKey)
            );
            // =======================
            if (RateLimiter::availableIn($emailKey) >= 59) {
                Log::critical('Possible brute force attack', [
                    'email' => $email,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
            // =======================

            return response()->json([
                'success' => false,
                'message' => 'Too many login attempts. Please try again later.',
                'retry_after_seconds' => $seconds,
            ], 429);
        }

        // 4) Find user (no info leak)
        $user = User::where('email', $email)->first();

        // If user invalid OR password invalid → hit limit + generic error
        if (
            ! $user ||
            ! Hash::check($validated['password'], $user->password)
        ) {

            RateLimiter::hit($emailKey, 60); // 1 min
            RateLimiter::hit($ipKey, 300);
            // =======================
            Log::warning('Failed login attempt', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            // =======================

            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials.',
            ], 401);
        }

        // Optional: block/inactive check (if you have this column)
        if (! $user->is_active) {
            // =======================
            Log::warning('Disabled account login attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);
            // =======================

            return response()->json([
                'success' => false,
                'message' => 'Your account is disabled.',
            ], 403);
        }

        // Optional: Email Verification Check
        // if (is_null($user->email_verified_at)) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Please verify your email first.',
        //     ], 403);
        // }

        // 5) Successful login → clear limits
        RateLimiter::clear($emailKey);
        RateLimiter::clear($ipKey);

        $user->setRememberToken(($validated['remember'] ?? false) ? Str::random(60) : null);
        $user->saveQuietly();

        // last login at and login ip
        UpdateLastLoginJob::dispatch($user->id, $request->ip());

        // Optional: revoke old tokens (single-device login)
        // $user->tokens()->delete();

        // =======================
        $abilities = [$user->role];
        // =======================


        // 6) Create token with abilities
        $deviceName = Str::limit($request->userAgent() ?? 'unknown-device', 100);
        // ======================= $abilities
        $token = $user->createToken($deviceName,$abilities,now()->addDays(30))->plainTextToken;

        // =======================
        Log::info('User login successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        // =======================

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'username' => $user->username,
                'phone'    => $user->phone,
                'image'    => $user->image,
                'role'     => $user->role,
            ],

        ], 200);
    }

    public function findAccount(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        $otp = rand(100000, 999999);
        $user->otp = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        Mail::to($user->email)->send(new OTPMail($otp, $user));

        session(['reset_email' => $user->email]);

        return response()->json([
            'message' => 'Account found.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 200);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->otp !== $request->otp) {
            return response()->json(['message' => 'Invalid OTP.'], 400);
        }

        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP has expired.'], 400);
        }

        return response()->json([
            'message' => 'OTP verified. You can now reset your password.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        // Validate request
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Find user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->password = Hash::make($request->password);

        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json([
            'message' => 'Password reset successful. You can now log in with your new password.'
        ], 200);
    }

    public function getAdminUser(){
        try {
            $users = User::all();

            return response()->json([
                'success' => true,
                'message' => 'Fetched all admin users',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // Logout Section
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Logged out successfully (current device).'
        ], 200);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json([
            'message' => 'Logged out from all devices.'
        ], 200);
    }

    public function logoutDevice(Request $request)
    {
        $request->validate([
            'token_id' => 'required|exists:personal_access_tokens,id'
        ]);
        $request->user()->tokens()
            ->where('id', $request->token_id)
            ->delete();

        return response()->json([
            'message' => 'Device logged out successfully.'
        ]);
    }

    public function devices(Request $request)
    {
        $tokens = $request->user()->tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'device' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ];
        });

        return response()->json([
            'devices' => $tokens
        ]);
    }
}
