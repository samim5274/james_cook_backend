<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
use App\Mail\FinanceOtpMail;
use App\Models\OtpVerification;

class WalletController extends Controller
{
    public function index()
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

            // Credit calculation
            $credit = PointTransaction::where('user_id', $userId)
                ->where('bonus_status', 'credit')
                ->sum('bonus_amount');

            // Debit calculation
            $debit = PointTransaction::where('user_id', $userId)
                ->where('bonus_status', 'debit')
                ->sum('bonus_amount');

            // Final balance
            $balance = $credit - $debit;

            $pending = Transaction::where('user_id', $userId)
                ->where('status', 'pending')
                ->sum('amount');

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal data fetched successfully.',
                'data' => [
                    'balance' => (float) $balance,
                    'pending' => (float) $pending,
                ]
            ], 200);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching withdrawal data.',
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

            $data = Transaction::with('user')
                ->where('user_id', $userId)->latest()
                ->get();

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

    public function processingTransaction()
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

            $data = Transaction::with('user')
                ->where('status', '!=', 'cancelled')
                ->where('status', '!=', 'pending')
                // ->where('user_id', $userId)
                ->latest()
                ->get();

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

    public function store(Request $request)
    {
        // 1. Validate Request
        $validated = $request->validate([
            'amount'                => ['required', 'numeric', 'min:500'],
            'payment_method'        => ['required', 'in:bank,mobile'],

            // Bank fields (conditional validation better below)
            'bank_name'             => ['required_if:payment_method,bank', 'string'],
            'account_holder_name'   => ['required_if:payment_method,bank', 'string'],
            'account_number'        => ['required', 'string', 'max:50'],
            'routing_number'        => ['nullable', 'string', 'max:20'],
            'branch_name'           => ['nullable', 'string', 'max:100'],
            'swift_code'            => ['nullable', 'string', 'max:20'],
        ]);

        $user = auth()->user();

        if (!now()->between(
            now()->startOfMonth(),
            now()->startOfMonth()->copy()->addDays(2)
        )) {
            return response()->json([
                'status'    => false,
                'message'   => 'Withdraw allowed only on 1st–3rd of each month.'
            ], 403);
        }

        try {
            return DB::transaction(function () use ($validated, $user, $request) {

                $user = User::lockForUpdate()->find($user->id);

                //  LOCK SAFE BALANCE CHECK (prevent double withdraw)
                $balanceData = PointTransaction::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->selectRaw("
                        SUM(CASE WHEN bonus_status = 'credit' THEN bonus_amount ELSE 0 END) -
                        SUM(CASE WHEN bonus_status = 'debit' THEN bonus_amount ELSE 0 END) AS balance
                    ")
                    ->first();

                $currentBalance = $balanceData->balance ?? 0;

                if ($currentBalance < $validated['amount']) {
                    return response()->json(['status' => false, 'message' => 'Insufficient balance in your wallet.'], 422);
                }

                $amount = (float) $validated['amount'];
                $charge = round($amount * 0.10, 2);
                $netAmount = $amount - $charge;

                // CREATE WITHDRAW REQUEST (PENDING)
                $transaction = Transaction::create([
                    'transaction_id'        => 'TRX-' . strtoupper(Str::random(12)),
                    'user_id'               => $user->id,
                    'amount'                => $amount,
                    'charge'                => $charge,
                    'net_amount'            => $netAmount,
                    'payment_method'        => $validated['payment_method'],

                    'bank_name'             => $validated['bank_name'] ?? null,
                    'account_holder_name'   => $validated['account_holder_name'] ?? null,
                    'account_number'        => $validated['account_number'] ?? null,
                    'routing_number'        => $validated['routing_number'] ?? null,
                    'branch_name'           => $validated['branch_name'] ?? null,
                    'swift_code'            => $validated['swift_code'] ?? null,

                    'status'                => 'pending',
                    'requested_at'          => now(),
                ]);

                // DEDUCT POINT (ledger style)
                PointTransaction::create([
                    'user_id'       => $user->id,
                    'type'          => 'withdraw',
                    'points'        => 0,
                    'bonus_amount'  => $amount,
                    'bonus_status'  => 'debit',
                    'source'        => 'cash_out',
                    'reference_id'  => $transaction->transaction_id,
                    'note'          => 'Withdrawal request initiated',
                ]);

                // ইউজার টেবিল ব্যালেন্স আপডেট (যদি কলাম থাকে)
                $user->wallet_balance = round($user->wallet_balance - $amount, 2);
                $user->save();

                // OTP generate
                $otp = random_int(100000, 999999);

                OtpVerification::create([
                    'transaction_id' => $transaction->transaction_id,
                    'user_id'        => $user->id,
                    'otp'            => bcrypt($otp),
                    'type'           => 'withdraw',
                    'expired_at'     => now()->addMinutes(5),
                    'is_used'        => false,
                    'ip_address'     => $request->ip(),
                ]);

                Mail::to($user->email)->send(new FinanceOtpMail($otp));

                return response()->json([
                    'status' => true,
                    'message' => 'OTP sent to your email',
                    'transaction_id' => $transaction->transaction_id,
                ], 201);
            });

        } catch (\Exception $e) {

            Log::error('Withdrawal Store Error', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string', 'exists:transactions,transaction_id'],
            'otp'            => ['required', 'digits:6'],
        ]);

        $user = auth()->user();

        try {
            return DB::transaction(function () use ($validated, $user) {
                // ১. ট্রানজেকশন লক করা (Race condition রোধ করতে)
                $transaction = Transaction::where('transaction_id', $validated['transaction_id'])
                    ->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                $otpRecord = OtpVerification::where('transaction_id', $validated['transaction_id'])
                    ->where('user_id', $user->id)
                    ->where('is_used', false)
                    ->lockForUpdate()
                    ->first();

                if (!$transaction || !$otpRecord) {
                    return response()->json(['status' => false, 'message' => 'Invalid or expired session.'], 404);
                }

                // এক্সপায়ার চেক
                if (now()->greaterThan($otpRecord->expired_at)) {
                    return $this->handleFailedOtp($transaction, $user, $otpRecord, 'OTP Expired');
                }

                // ৩. OTP ভেরিফিকেশন
                if (!password_verify($validated['otp'], $otpRecord->otp)) {
                    // ঐচ্ছিক: এখানে আপনি attempts++ করতে পারেন।
                    // আপাতত আপনার রিকোয়ারমেন্ট অনুযায়ী সরাসরি রোলব্যাক করা হচ্ছে।
                    return $this->handleFailedOtp($transaction, $user, $otpRecord, 'Invalid OTP provided');
                }

                // ৪. সাকসেস: ট্রানজেকশন প্রসেসিং মোডে নেওয়া
                $otpRecord->update(['is_used' => true]);
                $transaction->update([
                    'status'       => 'processing',
                    'is_confirm'   => true,
                    'processed_at' => now(),
                ]);

                return response()->json([
                    'status'  => true,
                    'message' => 'Withdrawal verified successfully and is now under review.'
                ]);
            });

        } catch (\Exception $e) {
            Log::error("OTP Verification Error: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Verification failed.'], 500);
        }
    }

    private function handleFailedOtp($transaction, $user, $otpRecord, $reason)
    {
        // ১. ট্রানজেকশন ক্যানসেল
        $transaction->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'note'         => $reason
        ]);

        // ২. রিফান্ড লেজার এন্ট্রি
        PointTransaction::create([
            'user_id'       => $user->id,
            'type'          => 'refund',
            'bonus_amount'  => $transaction->amount,
            'bonus_status'  => 'credit',
            'source'        => 'withdraw_rollback',
            'reference_id'  => $transaction->transaction_id,
            'note'          => 'Automatic refund: ' . $reason,
        ]);

        // ৩. ইউজার মেইন ব্যালেন্স ফেরত দেওয়া
        $user->increment('wallet_balance', $transaction->amount);

        // ৪. OTP ডিজেবল করা
        if ($otpRecord) {
            $otpRecord->update(['is_used' => true]);
        }

        return response()->json([
            'status'  => false,
            'message' => "Verification failed: $reason. Your amount has been refunded to your wallet."
        ], 422);
    }

    public function transactionDelete($id)
    {
        try {

            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            // find transaction
            $transaction = Transaction::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found.'
                ], 404);
            }

            // block delete if confirmed
            if ($transaction->is_confirm) {
                return response()->json([
                    'success' => false,
                    'message' => 'Confirmed transaction cannot be deleted.'
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | REFUND LOGIC (POINT CREDIT BACK)
            |--------------------------------------------------------------------------
            */

            PointTransaction::create([
                'user_id' => $userId,
                'type' => 'refund',
                'points' => 0,
                'bonus_amount' => $transaction->amount,
                'bonus_status' => 'credit',
                'source' => 'transaction_delete',
                'reference_id' => $transaction->transaction_id ?? Str::uuid(),
                'note' => 'Refund from deleted withdrawal transaction ID: ' . $transaction->id,
            ]);

            // delete
            // $transaction->delete();
            $transaction->update([
                'status' => 'cancelled'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully.'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while deleting transaction.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getTransaction(string $transaction_id, int $user_id)
    {
        try {

            $transaction = Transaction::with('user:id,name,email')
                ->where('transaction_id', $transaction_id)
                ->where('user_id', $user_id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaction fetched successfully.',
                'data' => $transaction,
            ]);

        } catch (\Throwable $e) {

            \Log::error('Transaction fetch failed', [
                'transaction_id' => $transaction_id,
                'user_id' => $user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            // Validation
            $validated = $request->validate([
                'status' => 'required|in:pending,processing,paid,rejected,cancelled',
                'admin_note' => 'nullable|string|max:1000',
            ]);

            // Find transaction
            $transaction = Transaction::findOrFail($id);

            // Optional: check if already same status
            if (
                $transaction->status === $validated['status'] &&
                $transaction->admin_note === ($validated['admin_note'] ?? null)
            ) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes detected.',
                    'data' => $transaction,
                ], 200);
            }

            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user.'
                ], 401);
            }

            // Update safely
            $transaction->update([
                'status' => $validated['status'],
                'admin_note' => $validated['admin_note'] ?? null,
                'processed_by' => $userId,
                'processed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction status updated successfully.',
                'data' => $transaction->fresh(),
            ], 200);

        } catch (Throwable $e) {

            // Log error for debugging
            Log::error('Transaction status update failed', [
                'transaction_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        }
    }
}
