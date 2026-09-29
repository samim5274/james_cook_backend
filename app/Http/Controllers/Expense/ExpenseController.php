<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Support\Facades\Auth;
use App\Models\Excategory;
use App\Models\Exsubcategory;
use App\Models\Expense;
use App\Models\Company;

class ExpenseController extends Controller
{
    public function index(){
        
        $categories = Excategory::all();
        $expenseDetails = Expense::with(['category','subcategory','user'])->whereDate('date', today())->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'message' => "Get all expense category & sub-category.",
            'data' => [
                'categories' => $categories,
                'expenseDetails' => $expenseDetails,
            ]
        ]);
    }

    public function getSubCategory($id){
        try {
            $subcategories = Exsubcategory::where('category_id', $id)->get();
            return response()->json([
                'success' => true,
                'data' => $subcategories
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    public function store(Request $request){
        $request->validate([
            'category_id'      => ['required', 'integer', 'exists:excategories,id'],
            'sub_category_id'  => ['required', 'integer', 'exists:exsubcategories,id'],
            'title'            => ['required', 'string', 'max:255'],
            'amount'           => ['required', 'numeric', 'min:0'],
            'remark'           => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = Auth::id();

        $expense = Expense::create([
            'category_id'     => $request->category_id,
            'sub_category_id' => $request->sub_category_id,
            'title'           => $request->title,
            'date'            => now()->toDateString(),
            'amount'          => $request->amount,
            'remark'          => $request->remark ?? "",
            'user_id'         => $userId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully.',
            'data'    => $expense,
        ], 201);
    }

    public function detailsShow($id){
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
                'data' => $expense
            ], 200);
            
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    public function print($id){
        try{
            $userId = auth()->id();
            $expense = Expense::with(['category','subcategory','user'])->where('user_id', $userId)->findOrFail($id);
            if(!$expense){
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found. Please try again.',
                ], 404);
            }

            $company = Company::first();

            return response()->json([
                'success' => true,
                'data' => $expense,
                'company' => $company,
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

    public function delete($id){
        try{
            $userId = auth()->id();
            $expense = Expense::with(['category','subcategory','user'])->where('user_id', $userId)->findOrFail($id);
            if(!$expense){
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found. Please try again.',
                ], 404);
            }

            $expense->delete();

            return response()->json([
                'success' => true,
                'message' => "Expense delete successfully.",
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

    public function setting(){
        try{

            $categories = Excategory::paginate(5, ['*'], 'category_page');
            $subcategories = Exsubcategory::with('category')->paginate(5, ['*'], 'subcategory_page');
            
            if($categories->isEmpty() && $subcategories->isEmpty()){
                return response()->json([
                    'success' => false,
                    'message' => 'No Category or Sub-Category found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => "Get category and Sub-category feteched successfully.",
                'data' => [
                    'categories' => $categories,
                    'subcategories' => $subcategories,
                ],
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

    public function storeCategory(Request $request){
        try{
            $request->validate([
                'name' => 'required|string|max:100|unique:excategories,name'
            ]);

            $category = Excategory::create([
                'name' => $request->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully.',
                'data' => $category
            ], 201);
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

    public function storeSubCategory(Request $request){
        try{
            $request->validate([
                'category_id' => 'required|integer|exists:excategories,id',
                'name'        => 'required|string|max:100',
            ]);

            $exists = Exsubcategory::where('category_id', $request->category_id)
                ->where('name', $request->name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This sub-category already exists in this category.',
                ], 409);
            }

            $sub = Exsubcategory::create([
                'category_id' => $request->category_id,
                'name'        => $request->name,
            ]);

            $sub->load('category');

            return response()->json([
                'success' => true,
                'message' => 'Sub-category created successfully.',
                'data'    => $sub,
            ], 201);
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

    public function deleteCategory($id){
        try {
            $category = Excategory::withCount('subcategories')->findOrFail($id);
            if ($category->subcategories_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category because it has sub-categories.',
                ], 409);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);

        } catch (\Throwable $e) {

            \Log::error("Category delete error: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                // 'message' => 'Failed to delete category.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteSubCategory($id){
        try{
            $sub = Exsubcategory::withCount('expenses')->findOrFail($id);
            if ($sub->expenses_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete sub-category because it has expenses.',
                ], 409);
            }           

            $sub->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sub-category deleted successfully.',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-category not found.',
            ], 404);

        } catch (\Throwable $e) {
            \Log::error("Sub-category delete error: ".$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                // 'message' => 'Failed to delete sub-category.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function editSubCategory(Request $request, $id){
        try{
            $request->validate([
                'category_id' => 'required|exists:excategories,id',
                'name' => 'required|string|max:100',
            ]);

            $sub = Exsubcategory::findOrFail($id);

            $sub->update([
                'category_id' => $request->category_id,
                'name' => $request->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sub-category updated successfully.'
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

    public function editCategory(Request $request, $id){
        try{
            $request->validate([
                'name' => 'required|string|max:100|unique:excategories,name,' . $id,
            ]);

            $cat = Excategory::findOrFail($id);

            $cat->update([
                'name' => $request->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'data' => $cat
            ], 200);
        } catch (\Throwable $e) {
            \Log::error("Category update error: ".$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update category.',
            ], 500);
        }
    }
}