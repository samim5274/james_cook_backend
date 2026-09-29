<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use Session;
use App\Models\Expense;
use App\Models\ExCategory;
use App\Models\ExSubCategory;


class ExpensesController extends Controller
{
    public function index()
    {
        try {
            $today = now()->toDateString();

            $expenses = Expense::query()
                ->with([
                    'category:id,name',
                    'subcategory:id,name',
                    'user:id,name',
                ])
                ->whereDate('date', $today)
                ->orderByDesc('id')
                ->paginate(20);

            $categories = ExCategory::query()
                ->orderByDesc('id')
                ->get();

            $subcategories = ExSubCategory::query()
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Today's expenses fetched successfully.",
                'data' => [
                    'expenses' => $expenses,
                    'categories' => $categories,
                    'subcategories' => $subcategories,
                ],
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Failed to fetch today expenses.', [
                'user_id' => auth()->id(),
                'date'    => now()->toDateString(),
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch today\'s expenses.',
            ], 500);
        }
    }

    public function getCategoryAndSubCategory()
    {
        try {
            $data = Cache::remember(
                'expense.categories_subcategories.v1',
                now()->addHours(6),
                function () {
                    return [
                        'categories' => ExCategory::query()
                            ->orderByDesc('id')
                            ->get(),

                        'subcategories' => ExSubCategory::query()
                            ->orderByDesc('id')
                            ->get(),
                    ];
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Expense categories and sub-categories fetched successfully.',
                'data' => $data,
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Failed to fetch expense categories and sub-categories.', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch expense categories and sub-categories.',
            ], 500);
        }
    }

    public function getExCategory()
    {
        try {
            $categories = ExCategory::query()
                ->orderBy('id', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Expense categories fetched successfully.',
                'data'    => $categories,
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Failed to fetch expense categories.', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch expense categories.',
            ], 500);
        }
    }

    public function getExSubCategory()
    {
        try {
            $subcategories = ExSubCategory::query()->with('category')
                ->orderByDesc('id')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Expense sub-categories fetched successfully.',
                'data' => $subcategories,
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Failed to fetch expense sub-categories.', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch expense sub-categories.',
                'data'    => null,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id'     => ['required', 'integer', 'exists:ex_categories,id'],
            'sub_category_id' => ['nullable', 'integer', 'exists:ex_sub_categories,id'],
            'title'           => ['required', 'string', 'max:255'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'remark'          => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $expense = Expense::create([
                'category_id'     => $validated['category_id'],
                'sub_category_id' => $validated['sub_category_id'] ?? null,
                'user_id'         => auth()->id(),
                'title'           => $validated['title'],
                'date'            => now()->toDateString(),
                'amount'          => $validated['amount'],
                'remark'          => $validated['remark'] ?? "N/A",
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expense created successfully.',
                'data'    => $expense,
            ], 201);

        } catch (\Throwable $e) {

            Log::error('Failed to create expense.', [
                'user_id' => auth()->id(),
                'request' => $request->except(['password', 'token']),
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create expense at this time. Please try again later.',
            ], 500);
        }
    }

    public function printExpenses($id)
    {
        try{
            $userId = auth()->id();
            $expense = Expense::with(['category','subcategory','user'])->where('user_id', $userId)->findOrFail($id);
            if(!$expense){
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found. Please try again.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $expense,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error("Expense print error: ".$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function details($id)
    {
        try {
            $userId = auth()->id();

            $expense = Expense::with([
                'category',
                'subcategory',
                'user'
            ])
            ->where('user_id', $userId)
            ->where('id', $id)
            ->first();

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found or you do not have permission to view this expense.',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Expense details retrieved successfully.',
                'data' => $expense,
            ], 200);

        } catch (\Throwable $e) {

            \Log::error("Expense details error: " . $e->getMessage(), [
                'expense_id' => $id,
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching expense details.',
                'data' => null,
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $userId = auth()->id();

            $expense = Expense::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found or you do not have permission to delete this expense.',
                    'data' => null,
                ], 404);
            }

            $expense->delete();

            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully.',
                'data' => null,
            ], 200);

        } catch (\Throwable $e) {

            \Log::error("Expense delete error: " . $e->getMessage(), [
                'expense_id' => $id,
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while deleting the expense.',
                'data' => null,
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id'     => ['required', 'exists:ex_categories,id'],
            'sub_category_id' => ['nullable', 'exists:ex_sub_categories,id'],
            'title'           => ['required', 'string', 'max:255'],
            'amount'          => ['required', 'numeric', 'min:0'],
            'remark'          => ['nullable', 'string'],
        ]);

        try {
            $userId = auth()->id();

            $expense = Expense::where('user_id', $userId)
                ->where('id', $id)
                ->first();

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found or you do not have permission to update this expense.',
                    'data' => null,
                ], 404);
            }

            $expense->update([
                'category_id'     => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'title'           => $request->title,
                'amount'          => $request->amount,
                'remark'          => $request->remark . ' - Edited : ' . $expense->amount,
            ]);

            $expense->load([
                'category',
                'subcategory',
                'user'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully.',
                'data' => $expense,
            ], 200);

        } catch (\Throwable $e) {

            \Log::error("Expense update error: " . $e->getMessage(), [
                'expense_id' => $id,
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while updating the expense.',
                'data' => null,
            ], 500);
        }
    }

    public function categoryCreate(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $expenseCategory = ExCategory::create([
                'name' => $validated['name'],
            ]);

            Cache::forget('expense.categories_subcategories.v1');

            return response()->json([
                'success' => true,
                'message' => 'Expense category created successfully.',
                'data'    => $expenseCategory,
            ], 201);

        } catch (\Throwable $e) {

            Log::error('Failed to create expense.', [
                'user_id' => auth()->id(),
                'request' => $request->except(['password', 'token']),
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create expense category at this time. Please try again later.',
            ], 500);
        }
    }

    public function categoryDelete($id)
    {
        try {
            $expenseCategory = ExCategory::where('id', $id)->first();

            if (!$expenseCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found or you do not have permission to delete this expense category.',
                    'data' => null,
                ], 404);
            }

            $expenseCategory->delete();

            Cache::forget('expense.categories_subcategories.v1');

            return response()->json([
                'success' => true,
                'message' => 'Expense Category deleted successfully.',
                'data' => null,
            ], 200);

        } catch (\Throwable $e) {

            \Log::error("Expense delete error: " . $e->getMessage(), [
                'expense_id' => $id,
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while deleting the expense category.',
                'data' => null,
            ], 500);
        }
    }

    public function categoryEdit(Request $request, $id)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {

            $expenseCategory = ExCategory::where('id', $id)->first();

            if (!$expenseCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found or you do not have permission to update this expense category.',
                    'data' => null,
                ], 404);
            }

            $expenseCategory->update([
                'name' => $request->name,
            ]);

            Cache::forget('expense.categories_subcategories.v1');

            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully.',
                'data' => $expenseCategory,
            ], 200);

        } catch (\Throwable $e) {

            \Log::error("Expense update error: " . $e->getMessage(), [
                'expense_id' => $id,
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while updating the expense category.',
                'data' => null,
            ], 500);
        }
    }

    public function categorySubCreate(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id'   => ['required','integer','exists:ex_categories,id',],
                'name'          => ['required','string','min:2','max:255',],
            ]);

            // Prevent duplicate sub-category under the same category
            $exists = ExSubCategory::query()
                ->where('category_id', $validated['category_id'])
                ->where('name', $validated['name'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This sub-category already exists under the selected category.',
                    'errors' => [
                        'name' => [
                            'This sub-category already exists under the selected category.'
                        ],
                    ],
                ], 422);
            }

            $subCategory = ExSubCategory::create([
                'category_id' => $validated['category_id'],
                'name'        => trim($validated['name']),
            ]);

            // Clear cached categories and sub-categories
            Cache::forget('expense.categories_subcategories.v1');

            return response()->json([
                'success' => true,
                'message' => 'Expense sub-category created successfully.',
                'data' => $subCategory,
            ], 201);

        } catch (\Throwable $e) {

            Log::error('Failed to create expense sub-category.', [
                'user_id' => auth()->id(),
                'category_id' => $request->input('category_id'),
                'name' => $request->input('name'),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create expense sub-category.',
            ], 500);
        }
    }

    public function subCategoryDelete(int $id)
    {
        try {
            $subCategory = ExSubCategory::query()
                ->find($id);

            if (!$subCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense sub-category not found.',
                ], 404);
            }

            $subCategory->delete();

            // Clear cached category/sub-category data
            Cache::forget('expense.categories_subcategories.v1');

            return response()->json([
                'success' => true,
                'message' => 'Expense sub-category deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Failed to delete expense sub-category.', [
                'user_id' => auth()->id(),
                'sub_category_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete expense sub-category.',
            ], 500);
        }
    }

    public function subCategoryEdit(Request $request, int $id)
    {
        try {
            $validated = $request->validate([
                'category_id' => ['required','integer','exists:ex_categories,id',],
                'name' => ['required','string','min:2','max:255',],
            ]);

            $subCategory = ExSubCategory::query()->find($id);

            if (!$subCategory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense sub-category not found.',
                ], 404);
            }

            $categoryId = (int) $validated['category_id'];
            $name = trim($validated['name']);

            $duplicate = ExSubCategory::query()
                ->where('category_id', $categoryId)
                ->where('name', $name)
                ->where('id', '!=', $subCategory->id)
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'This sub-category already exists under the selected category.',
                    'errors' => [
                        'name' => [
                            'This sub-category already exists under the selected category.'
                        ],
                    ],
                ], 422);
            }

            $subCategory->update([
                'category_id' => $categoryId,
                'name' => $name,
            ]);

            Cache::forget('expense.categories_subcategories.v1');

            return response()->json([
                'success' => true,
                'message' => 'Expense sub-category updated successfully.',
                'data' => $subCategory->fresh(),
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Failed to update expense sub-category.', [
                'user_id' => auth()->id(),
                'sub_category_id' => $id,
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update expense sub-category.',
            ], 500);
        }
    }
}
