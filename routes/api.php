<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ========================================
// Controllers
// ========================================
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ProfileController;

use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\StockController;

use App\Http\Controllers\Customer\CustomerController;

use App\Http\Controllers\Dashboard\DashboardController;

use App\Http\Controllers\Ecommerce\SearchController;
use App\Http\Controllers\Ecommerce\CartController;
use App\Http\Controllers\Ecommerce\AdminCartController;
use App\Http\Controllers\Ecommerce\AdminCartReturnController;
use App\Http\Controllers\Ecommerce\RatingController;
use App\Http\Controllers\Ecommerce\SliderController;

use App\Http\Controllers\Purchase\PurchaseController;
use App\Http\Controllers\Finance\DueController;

use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Order\CouponController;

use App\Http\Controllers\Notice\NoticeController;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Expenses\ExpensesController;
use App\Http\Controllers\Report\ReportController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ======================
// Auth Routes
// ======================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/find-account', [AuthController::class, 'findAccount']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/logout-device', [AuthController::class, 'logoutDevice']);
        Route::get('/devices', [AuthController::class, 'devices']);
    });
});

Route::prefix('register')->group(function () {
    Route::post('/create-user', [AuthController::class, 'register']);
});




















// ======================
// Profile Routes
// ======================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('profile')->group(function () {
        Route::put('/customer', [ProfileController::class, 'update']);
        Route::patch('/password', [ProfileController::class, 'changePassword']);
    });
});
















// ======================
// Product Routes
// ======================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('products')->group(function () {
        Route::post('/create', [ProductController::class, 'store']);
        Route::get('/', [ProductController::class, 'index']);

        Route::get('/get-categories', [ProductController::class, 'getCategory']);
        Route::get('/get-subcategories', [ProductController::class, 'getSubCategory']);
        Route::get('/get-brands', [ProductController::class, 'getBrand']);

        Route::post('/create-brand', [ProductController::class, 'storeBrand']);
        Route::delete('/delete-brand/{id}', [ProductController::class, 'deleteBrand']);
        Route::put('/edit-brand/{id}', [ProductController::class, 'editBrand']);

        Route::post('/create-category', [ProductController::class, 'storeCategory']);
        Route::delete('/delete-category/{id}', [ProductController::class, 'deleteCategory']);
        Route::put('/edit-category/{id}', [ProductController::class, 'editCategory']);

        Route::post('/create-sub-category', [ProductController::class, 'storeSubCategory']);
        Route::delete('/delete-sub-category/{id}', [ProductController::class, 'deleteSubCategory']);
        Route::put('/edit-sub-category/{id}', [ProductController::class, 'editSubCategory']);

        // Product sale report Route
        Route::get('/report', [ProductController::class, 'reportSale']);

        // LAST: dynamic route for product details, must be at the end of all product routes
        Route::put('/update/{id}', [ProductController::class, 'edit'])->where('id', '[0-9]+');
        Route::delete('/delete/{id}', [ProductController::class, 'delete'])->where('id', '[0-9]+');
        Route::get('/{slug}', [ProductController::class, 'show'])->where('slug', '[a-zA-Z0-9\-]+');
    });
});




// ======================
// Product Stock Routes
// ======================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('stock')->group(function () {
        Route::get('/', [StockController::class, 'index']);
        Route::get('/report', [StockController::class, 'stockReport']);
        Route::post('/{id}', [StockController::class, 'store']);
    });

    Route::prefix('purchase')->group(function () {
        Route::get('/', [PurchaseController::class, 'index']);
        Route::get('/orders', [PurchaseController::class, 'purchaseOrder']);
        Route::get('/add-to-cart', [PurchaseController::class, 'addToCard']);
        Route::post('/add-to-cart-search', [PurchaseController::class, 'adminAddToCartSearch']);
        Route::post('/add-to-cart', [PurchaseController::class, 'adminAddToCart']);
        Route::get('/order/details/{reg}', [PurchaseController::class, 'getPurchaseDetails']);
        Route::post('/due/collection', [PurchaseController::class, 'dueCollection']);

        Route::prefix('reports')->group(function () {
            Route::get('/', [PurchaseController::class, 'purchaseReport']);
            Route::get('/filter', [PurchaseController::class, 'reportSaleFilter']);
        });

        Route::get('/{reg}', [PurchaseController::class, 'getCartItem']);
        Route::post('/qty-update/{reg}/{product_id}', [PurchaseController::class, 'updateQty']);
        Route::put('/cart/{id}', [PurchaseController::class, 'updateCartItem']);
        Route::post('/remove-to-cart/{cart_id}/{reg}/{product_id}', [PurchaseController::class, 'removeToCart']);
        Route::post('/checkout/{reg}', [PurchaseController::class, 'confirmOrder']);
    });
});














// ======================
// Customer Routes
// ======================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('customer')->group(function (){

        Route::prefix('addresses')->group(function(){
            Route::get('/get', [CustomerController::class, 'getAddress']);
            Route::post('/create', [CustomerController::class, 'createAddress']);
            Route::delete('/delete/{id}', [CustomerController::class, 'deleteAddress']);
        });

        Route::prefix('profile')->group(function () {
            Route::put('/', [CustomerController::class, 'update']);
            Route::put('/password', [CustomerController::class, 'changePassword']);
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', [CustomerController::class, 'getOrders']);
            Route::post('/store', [CustomerController::class, 'storeOrder']);
        });

    });

    Route::prefix('dashboard')->group(function() {
        Route::get('/', [DashboardController::class, 'dashboard']);
    });
});

















// ======================
// dashboard Routes
// ======================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('dashboard')->group(function() {
        Route::get('/', [DashboardController::class, 'dashboard']);
    });
});










Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('admin/cart')->group(function () {
        Route::get('/', [AdminCartController::class, 'index']);
        Route::get('/{reg}', [AdminCartController::class, 'getCartItem']);
        Route::post('/add-to-cart', [AdminCartController::class, 'adminAddToCart']);
        Route::post('/add-to-cart-search', [AdminCartController::class, 'adminAddToCartSearch']);
        Route::post('/qty-update/{reg}/{product_id}', [AdminCartController::class, 'updateQty']);
        Route::post('/remove-to-cart/{cart_id}/{reg}/{product_id}', [AdminCartController::class, 'removeToCart']);
        Route::post('/checkout/{reg}', [AdminCartController::class, 'checkOut']);
        Route::post('/checkout/return/{reg}', [AdminCartController::class, 'checkOutReturn']);
    });

    Route::prefix('admin/return/cart')->group(function () {
        Route::get('/{reg}/{slug}', [AdminCartReturnController::class, 'index']);
        Route::post('/qty-update/{reg}/{product_id}', [AdminCartReturnController::class, 'updateQty']);
        Route::post('/checkout/{reg}', [AdminCartReturnController::class, 'checkOutReturn']);
    });
});








// =============================
// E-commerce Admin order Routes
// =============================
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/return/{reg}/{slug}/{id}', [OrderController::class, 'orderReturn']);
        Route::get('/status', [OrderController::class, 'statusFilter']);

        Route::prefix('reports')->group(function(){
            Route::get('/sale', [OrderController::class, 'reportSale']);
            Route::get('/sale/filter', [OrderController::class, 'reportSaleFilter']);

            Route::get('/payment', [OrderController::class, 'reportPayment']);
            Route::get('/payment/filter', [OrderController::class, 'reportPaymentFilter']);
        });

        Route::get('/{reg}', [OrderController::class, 'getOrderDetails']);
        Route::get('/payment/{paymentNumber}/{orderId}', [OrderController::class, 'getOrderPaymentDetails']);
    });
});







// =============================
// Due Controller
// =============================
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('due')->group(function () {
        Route::get('/', [DueController::class, 'index']);
        Route::post('/collection', [DueController::class, 'dueCollection']);

        Route::get('/{reg}', [DueController::class, 'getOrderDetails']);
    });
});






// ======================
// Notice Routes
// ======================
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('notice')->group(function () {
        Route::get('/', [NoticeController::class, 'index']);
        Route::get('/user', [NoticeController::class, 'userNotice']);
        Route::post('/create', [NoticeController::class, 'create']);
        // Route::get('/view/{file}', [NoticeController::class, 'attachView']);
        Route::delete('/delete/{id}', [NoticeController::class, 'delete']);
        Route::get('/view/{id}', [NoticeController::class, 'viewNotice']);
        Route::put('/update/{id}', [NoticeController::class, 'updateNotice']);
        // Route::get('/show-all-notices', [NoticeController::class, 'show']);
    });
});









// ======================
// Expenses Routes
// ======================
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('expenses')->group(function () {

        Route::get('/', [ExpensesController::class, 'index']);
        Route::post('/', [ExpensesController::class, 'store']);
        Route::get('/print/{id}', [ExpensesController::class, 'printExpenses']);
        Route::get('/details/{id}', [ExpensesController::class, 'details']);
        Route::delete('/{id}', [ExpensesController::class, 'delete']);
        Route::put('/{id}', [ExpensesController::class, 'update']);

        // Categories + Sub Categories
        Route::get('/category-sub-category', [ExpensesController::class,'getCategoryAndSubCategory']);

        Route::prefix('category')->group(function () {
            Route::get('/', [ExpensesController::class, 'getExCategory']);
            Route::post('/', [ExpensesController::class, 'categoryCreate']);
            Route::delete('/{id}', [ExpensesController::class, 'categoryDelete']);
            Route::put('/{id}', [ExpensesController::class, 'categoryEdit']);
        });

        Route::prefix('sub-category')->group(function () {
            Route::get('/', [ExpensesController::class, 'getExSubCategory']);
            Route::post('/', [ExpensesController::class, 'categorySubCreate']);
            Route::delete('/{id}', [ExpensesController::class, 'subCategoryDelete']);
            Route::put('/{id}', [ExpensesController::class, 'subCategoryEdit']);
        });
    });
});




















Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('expense')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::get('/get-subcategory/{id}', [ExpenseController::class, 'getSubCategory']);
        Route::post('/create', [ExpenseController::class, 'store']);
        Route::get('/details/{id}', [ExpenseController::class, 'detailsShow']);
        Route::get('/print/{id}', [ExpenseController::class, 'print']);
        Route::delete('/delete/{id}', [ExpenseController::class, 'delete']);
        // Expense setting routes
        Route::get('/setting', [ExpenseController::class, 'setting']);
        Route::post('/category', [ExpenseController::class, 'storeCategory']);
        Route::post('/subcategory', [ExpenseController::class, 'storeSubCategory']);
        Route::delete('/category/{id}', [ExpenseController::class, 'deleteCategory']);
        Route::put('/edit/category/{id}', [ExpenseController::class, 'editCategory']);
        Route::delete('/subcategory/{id}', [ExpenseController::class, 'deleteSubCategory']);
        Route::put('/edit/subcategory/{id}', [ExpenseController::class, 'editSubCategory']);
    });
});


Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('reports')->group(function () {
        Route::get('/products', [ReportController::class, 'index']);
        Route::get('/customer/due', [ReportController::class, 'customerDue']);
        Route::get('/supplyer/due', [ReportController::class, 'supplyerDue']);
        Route::get('/expense', [ReportController::class, 'expense']);
        Route::get('/profit-loss', [ReportController::class, 'profitAndLoss']);
        Route::get('/day-by-day', [ReportController::class, 'dayByDaySaleReport']);
        Route::get('/product-by-product', [ReportController::class, 'productByProductSaleReport']);
    });
});
