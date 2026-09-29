<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index()
    {

        $products = Product::where('is_active', true)
            ->where('approval_status', 2)
            ->select('slug','updated_at')
            ->get();


        $categories = ProductCategory::where('is_active', true)
            ->where('indexable', true)
            ->select('slug','updated_at')
            ->get();



        return response()
        ->view('sitemap', compact(
            'products',
            'categories'
        ))
        ->header('Content-Type','text/xml');

    }
}
