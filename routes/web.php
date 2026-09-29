<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\SitemapController;

Route::get('/', function () {
    return redirect('https://pos.mercuviax.com/');
});

Route::get('/clear', function () {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('optimize:clear');
        Artisan::call('optimize');

        return redirect('https://pos.mercuviax.com/');
    });


// SSLCOMMERZ Start
// use App\Http\Controllers\SslCommerzPaymentController;
// Route::get('/example1', [SslCommerzPaymentController::class, 'exampleEasyCheckout']);
// Route::get('/example2', [SslCommerzPaymentController::class, 'exampleHostedCheckout']);

// Route::post('/pay', [SslCommerzPaymentController::class, 'index']);
// Route::post('/pay-via-ajax', [SslCommerzPaymentController::class, 'payViaAjax']);

// Route::post('/success', [SslCommerzPaymentController::class, 'success']);
// Route::post('/fail', [SslCommerzPaymentController::class, 'fail']);
// Route::post('/cancel', [SslCommerzPaymentController::class, 'cancel']);

// Route::post('/ipn', [SslCommerzPaymentController::class, 'ipn']);
//SSLCOMMERZ END


Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class,'callback']);

// Route::get('auth/facebook/callback', [SocialAuthController::class, 'loginWithFacebook']);
// Route::get('auth/google/callback', [SocialAuthController::class, 'loginWithGoogle']);
// Route::get('auth/github/callback', [SocialAuthController::class, 'loginWithGithub']);


// site map
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
