<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Models\User;
use App\Models\PointTransaction;


class AccountController extends Controller
{
    public function index(){
        try {
            $userId = Auth::id();

            $pointTransactions = PointTransaction::with(['user', 'referenceUser'])
                ->where('user_id', $userId)
                ->latest()
                ->get();

            if ($pointTransactions->isEmpty()) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'No transactions found.',
                    'data'    => [],
                ], 200);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Transactions retrieved successfully.',
                'data'    => $pointTransactions,
            ], 200);

        } catch (Exception $e) {
            Log::error("Point Fetch Error: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while fetching transactions.',
            ], 500);
        }
    }

    public function adminStatement()
    {
        try {
            $pointTransactions = PointTransaction::with(['user', 'referenceUser'])
                ->latest()
                ->paginate(50);

            $totalCredit = PointTransaction::where('bonus_status', 'credit')->sum('bonus_amount');
            $totalDebit  = PointTransaction::where('bonus_status', 'debit')->sum('bonus_amount');
            // $netTotal    = $totalCredit - $totalDebit;

            if ($pointTransactions->total() === 0) {
                return response()->json([
                    'status'       => 'success',
                    'message'      => 'No transactions found.',
                    'data'         => [],
                    'total_credit' => 0,
                    'total_debit'  => 0,
                    // 'net_total'    => 0,
                ], 200);
            }

            return response()->json([
                'status'        => 'success',
                'message'       => 'Transactions retrieved successfully.',
                'data'          => $pointTransactions->items(),
                'current_page'  => $pointTransactions->currentPage(),
                'last_page'     => $pointTransactions->lastPage(),
                'total'         => $pointTransactions->total(),
                'per_page'      => $pointTransactions->perPage(),
                'from'          => $pointTransactions->firstItem(),
                'to'            => $pointTransactions->lastItem(),

                'total_credit'  => (float) $totalCredit,
                'total_debit'   => (float) $totalDebit,
                // 'net_total'     => (float) $netTotal,
            ], 200);

        } catch (Exception $e) {
            Log::error("Point Fetch Error: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while fetching transactions.',
            ], 500);
        }
    }

    public function StarClubStatement(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date'   => 'required|date|after_or_equal:start_date|before_or_equal:today',
        ], [
            'end_date.before_or_equal' => 'End date cannot be greater than today.',
            'end_date.after_or_equal'  => 'End date must be after or equal to start date.',
            'start_date.before_or_equal' => 'Start date must be before or equal to end date.',
        ]);

        try {
            $startDate = $request->start_date;
            $endDate   = $request->end_date;

            $baseQuery = PointTransaction::query()
                ->where('source', 'star_club')
                ->whereBetween('created_at', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ]);

            $pointTransactions = (clone $baseQuery)
                ->with(['user', 'referenceUser'])
                ->latest()
                ->paginate(50);

            $totalCredit = (clone $baseQuery)
                ->where('bonus_status', 'credit')
                ->sum('bonus_amount');

            $totalDebit = (clone $baseQuery)
                ->where('bonus_status', 'debit')
                ->sum('bonus_amount');

            if ($pointTransactions->total() === 0) {
                return response()->json([
                    'status'       => 'success',
                    'message'      => 'No transactions found.',
                    'data'         => [],
                    'total_credit' => 0,
                    'total_debit'  => 0,
                ], 200);
            }

            return response()->json([
                'status'        => 'success',
                'message'       => 'Transactions retrieved successfully.',
                'data'          => $pointTransactions->items(),
                'current_page'  => $pointTransactions->currentPage(),
                'last_page'     => $pointTransactions->lastPage(),
                'total'         => $pointTransactions->total(),
                'per_page'      => $pointTransactions->perPage(),
                'from'          => $pointTransactions->firstItem(),
                'to'            => $pointTransactions->lastItem(),

                'total_credit'  => (float) $totalCredit,
                'total_debit'   => (float) $totalDebit,
            ], 200);

        } catch (Exception $e) {
            Log::error("Point Fetch Error: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while fetching transactions.',
            ], 500);
        }
    }

    public function DynamicClubStatement(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date'   => 'required|date|after_or_equal:start_date|before_or_equal:today',
        ], [
            'end_date.before_or_equal' => 'End date cannot be greater than today.',
            'end_date.after_or_equal'  => 'End date must be after or equal to start date.',
            'start_date.before_or_equal' => 'Start date must be before or equal to end date.',
        ]);

        try {
            $startDate = $request->start_date;
            $endDate   = $request->end_date;

            $baseQuery = PointTransaction::query()
                ->where('source', 'dynamic_club')
                ->whereBetween('created_at', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59',
                ]);

            $pointTransactions = (clone $baseQuery)
                ->with(['user', 'referenceUser'])
                ->latest()
                ->paginate(50);

            $totalCredit = (clone $baseQuery)
                ->where('bonus_status', 'credit')
                ->sum('bonus_amount');

            $totalDebit = (clone $baseQuery)
                ->where('bonus_status', 'debit')
                ->sum('bonus_amount');

            if ($pointTransactions->total() === 0) {
                return response()->json([
                    'status'       => 'success',
                    'message'      => 'No transactions found.',
                    'data'         => [],
                    'total_credit' => 0,
                    'total_debit'  => 0,
                ], 200);
            }

            return response()->json([
                'status'        => 'success',
                'message'       => 'Transactions retrieved successfully.',
                'data'          => $pointTransactions->items(),
                'current_page'  => $pointTransactions->currentPage(),
                'last_page'     => $pointTransactions->lastPage(),
                'total'         => $pointTransactions->total(),
                'per_page'      => $pointTransactions->perPage(),
                'from'          => $pointTransactions->firstItem(),
                'to'            => $pointTransactions->lastItem(),

                'total_credit'  => (float) $totalCredit,
                'total_debit'   => (float) $totalDebit,
            ], 200);

        } catch (Exception $e) {
            Log::error("Point Fetch Error: " . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Something went wrong while fetching transactions.',
            ], 500);
        }
    }
}
