<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Exception;
use Throwable;

use App\Models\User;
use App\Models\PointTransaction;
use App\Models\Transaction;
use App\Services\PointService;
use App\Services\RegGenerator;
use App\Models\Order;
use App\Models\Product;
use App\Models\Cart;
use App\Models\ProductVariant;

class AdminController extends Controller
{

    public function index()
    {
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

    public function transaction()
    {
        try {
            // Get logged in user
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            $data = Transaction::with('user')->latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal data fetched successfully.',
                'data' => $data,
            ], 200);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching withdrawal data.',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function starClubUsers()
    {
        try {
            $currentMonth = now()->format('Y-m');

            $starUsers = Cache::remember("star_club_users_global_" . now()->format('Y-m'), 60, function () use ($currentMonth) {
                return User::query()
                    ->withCount('referrals')
                    ->has('referrals', '>=', 10) // ডাটাবেস লেভলেই ১০ বা তার বেশি রেফারাল ফিল্টার করবে
                    ->whereDoesntHave('pointTransactions', function ($q) use ($currentMonth) {
                        $q->where('source', 'star_club')
                        ->where('month', $currentMonth);
                    })
                    ->orderByDesc('referrals_count') // ডাটাবেস থেকেই সর্ট হয়ে আসবে
                    ->get();
            });

            return response()->json([
                'success' => true,
                'message' => 'Star club users fetched successfully.',
                'data' => $starUsers,
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function addMoneyStarClub(Request $request, $user_id)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $adminId = Auth::id();

        if (!$adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $amount = (float) $request->amount;

        try {
            $result = DB::transaction(function () use ($user_id, $amount) {

                $user = User::where('id', $user_id)->lockForUpdate()->firstOrFail();

                $currentMonth = now()->format('Y-m');

                PointTransaction::create([
                    'user_id'      => $user->id,
                    'type'         => 'bonus',
                    'points'       => 0,
                    'bonus_amount' => $amount,
                    'bonus_status' => 'credit',
                    'source'       => 'star_club',
                    'month'        => $currentMonth,
                    'note'         => 'Star Club bonus amount added',
                ]);

                $user->designation = 'star_club';
                $user->save();

                return [
                    'user_id'     => $user->id,
                    'amount'      => $amount,
                    'designation' => $user->designation,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Star Club bonus added successfully.',
                'data'    => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Star Club Add Money Failed', [
                'admin_id' => $adminId,
                'user_id'  => $user_id,
                'amount'   => $amount,
                'message'  => $e->getMessage(),
                'line'     => $e->getLine(),
                'file'     => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    public function dynamicClubUsers()
    {
        try {
            $currentMonth = now()->format('Y-m');

            $starUsers = Cache::remember("dynamic_star_users_" . $currentMonth, 60, function () use ($currentMonth) {
                return User::query()
                    ->select('users.*')

                    ->where('designation', 'star_club')


                    ->withCount(['referrals as star_referrals_count' => function ($q) {
                        $q->where('designation', 'star_club');
                    }])

                    ->whereHas('referrals', function ($q) {
                        $q->where('designation', 'star_club');
                    }, '>=', 10)

                    ->whereDoesntHave('pointTransactions', function ($q) use ($currentMonth) {
                        $q->where('source', 'star_club')
                        ->where('month', $currentMonth);
                    })

                    ->orderByDesc('star_referrals_count')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'message' => 'Dynamic users fetched successfully.',
                'data' => $starUsers,
                'count_refer' => $starUsers->count(),
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function addMoneyDynamicClub(Request $request, $user_id)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $adminId = Auth::id();

        if (!$adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        $amount = (float) $request->amount;

        try {
            $result = DB::transaction(function () use ($user_id, $amount) {

                $user = User::where('id', $user_id)->lockForUpdate()->firstOrFail();

                $currentMonth = now()->format('Y-m');

                PointTransaction::create([
                    'user_id'      => $user->id,
                    'type'         => 'bonus',
                    'points'       => 0,
                    'bonus_amount' => $amount,
                    'bonus_status' => 'credit',
                    'source'       => 'dynamic_club',
                    'month'        => $currentMonth,
                    'note'         => 'Dynamic Club bonus amount added',
                ]);

                $user->designation = 'dynamic_club';
                $user->save();

                return [
                    'user_id'     => $user->id,
                    'amount'      => $amount,
                    'designation' => $user->designation,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Dynamic Club bonus added successfully.',
                'data'    => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Dynamic Club Add Money Failed', [
                'admin_id' => $adminId,
                'user_id'  => $user_id,
                'amount'   => $amount,
                'message'  => $e->getMessage(),
                'line'     => $e->getLine(),
                'file'     => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    public function addMoney(Request $request, $user_id)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'source' => ['required', Rule::in([ 'bank_transfer', 'rank_bonus', 'gift', 'add_money', ]),],
            'note'   => ['required', 'string', 'max:255'],
        ]);

        $adminId = Auth::id();

        if (!$adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        if ((int)$user_id === (int)$adminId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot add money to your own account.'
            ], 422);
        }

        $amount = (float) $request->amount;

        try {
            $result = DB::transaction(function () use ($user_id, $amount, $request) {

                $user = User::where('id', $user_id)->lockForUpdate()->firstOrFail();

                PointTransaction::create([
                    'user_id'      => $user->id,
                    'type'         => 'earn',
                    'points'       => 0,
                    'bonus_amount' => $amount,
                    'bonus_status' => 'credit',
                    'source'       => $request->source,
                    'note'         => $request->note,
                ]);

                return [
                    'user_id'     => $user->id,
                    'amount'      => $amount,
                    'designation' => $user->designation,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Money added successfully.',
                'data'    => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Add Money Failed', [
                'admin_id' => $adminId,
                'user_id'  => $user_id,
                'amount'   => $amount,
                'message'  => $e->getMessage(),
                'line'     => $e->getLine(),
                'file'     => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    public function deductMoney(Request $request, $user_id)
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note'   => ['required', 'string', 'max:255'],
        ]);

        $adminId = Auth::id();

        if (!$adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 401);
        }

        if ((int)$user_id === (int)$adminId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deduct money from your own account.'
            ], 422);
        }

        $amount = (float) $request->amount;

        try {
            $result = DB::transaction(function () use ($user_id, $adminId, $amount, $request) {

                $targetUser = User::where('id', $user_id)->lockForUpdate()->firstOrFail();
                $adminUser  = User::where('id', $adminId)->lockForUpdate()->firstOrFail();

                // Ledger-based balance
                $currentBalance = (float) PointTransaction::where('user_id', $targetUser->id)
                    ->selectRaw("
                        COALESCE(SUM(CASE WHEN bonus_status = 'credit' THEN bonus_amount ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN bonus_status = 'debit'  THEN bonus_amount ELSE 0 END), 0) as balance
                    ")
                    ->value('balance') ?? 0;

                if ($currentBalance < $amount) {
                    throw new \InvalidArgumentException('Insufficient wallet balance.');
                }

                PointTransaction::create([
                    'user_id'      => $targetUser->id,
                    'type'         => 'spend',
                    'points'       => 0,
                    'bonus_amount' => $amount,
                    'bonus_status' => 'debit',
                    'source'       => 'deducted_by_admin',
                    'note'         => $request->note,
                ]);

                PointTransaction::create([
                    'user_id'      => $adminUser->id,
                    'type'         => 'earn',
                    'points'       => 0,
                    'bonus_amount' => $amount,
                    'bonus_status' => 'credit',
                    'source'       => 'deducted_from_user',
                    'note'         => "Received {$amount} from '{$targetUser->name}' (ID: {$targetUser->id})",
                ]);

                return [
                    'target_user_id'      => $targetUser->id,
                    'target_user_balance' => round($currentBalance - $amount, 2),
                    'admin_user_id'       => $adminUser->id,
                    'amount'              => $amount,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Money deducted successfully.',
                'data'    => $result
            ], 200);

        } catch (\InvalidArgumentException $e) {
            // Business logic error → 422
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            // Unexpected error → 500
            Log::error('Deduct Money Failed', [
                'admin_id' => $adminId,
                'user_id'  => $user_id,
                'amount'   => $amount,
                'message'  => $e->getMessage(),
                'line'     => $e->getLine(),
                'file'     => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    public function getUserDetails($user_id){
        try{
            $customer = User::where('user_id', $user_id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Customer Details fetched successfully.',
                'data' => $customer,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer details. Please try again later.',
            ], 500);
        }
    }

    public function changeUserRole(Request $request)
    {
        try {

            $request->validate([
                'user_id'   => ['required', 'integer', 'exists:users,id'],
                'role'      => ['required', 'string', 'in:customer,admin,super_admin'],
            ]);

            $user = User::where('id', $request->user_id)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User profile not found.',
                ], 404);
            }

            $user->update([
                'role' => $request->role,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully.',
                'data' => [
                    'user_id' => $user->user_id,
                    'role'    => $user->role,
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('User role update failed', [
                'user_id' => $request->user_id ?? null,
                'role'    => $request->role ?? null,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while updating user role.',
            ], 500);
        }
    }
}
