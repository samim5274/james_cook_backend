<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get one category, subcategory and brand
        $category = ProductCategory::first();

        $subcategory = ProductSubCategory::where(
            'category_id',
            $category?->id
        )->first();

        $brand = Brand::first();

        if (!$category || !$subcategory || !$brand) {
            $this->command->warn(
                'Make sure at least one category, subcategory and brand exist.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | James Cook Bakery & Cafe Products
        |--------------------------------------------------------------------------
        */

        $products = [
            ['Espresso', 140, 0],
            ['Espresso Doppio', 200, 0],
            ['Americano', 160, 0],
            ['Cappuccino', 170, 0],
            ['Latte', 180, 0],
            ['Caramel Latte', 220, 0],
            ['Mocha', 250, 0],
            ['Iced Espresso', 170, 0],
            ['Iced Americano', 190, 0],
            ['Iced Cappuccino', 200, 0],
            ['Iced Latte', 210, 0],
            ['Iced Caramel Latte', 250, 0],
            ['Iced Mocha', 280, 0],
            ['Chocolate Brownie', 120, 0],
            ['Large Cookies (Per Pcs)', 25, 0],
            ['Plain Slice Cake', 30, 0],
            ['Fruit Slice Cake', 35, 0],
            ['Muffin', 30, 0],
            ['Chocolate Muffin', 40, 0],
            ['Chicken Burger + French Fry 30gm', 180, 0],
            ['Chicken Nugget (6 Pcs) + French Fry 30gm', 180, 0],
            ['Chicken Finger (4 Pcs) + French Fry 30gm', 200, 0],
            ['Chicken Sandwich + French Fry 30gm', 120, 0],
            ['French Fry 100 gm', 100, 0],
            ['Coke/Sprite/Fanta', 25, 0],
            ['Mineral Water (250 ml)', 20, 0],
        ];

        foreach ($products as [$name, $price, $purchasePrice]) {

            Product::updateOrCreate(
                [
                    'slug' => Str::slug($name),
                ],
                [
                    'name'           => $name,
                    'slug'           => Str::slug($name),
                    'sku'             => 'SKU-' . Str::upper(Str::random(8)),

                    'category_id'     => $category->id,
                    'subcategory_id'  => $subcategory->id,
                    'brand_id'        => $brand->id,

                    'purchase_price'  => $purchasePrice,
                    'price'           => $price,
                    'discount'        => 0,

                    'stock_quantity'  => 0,
                    'min_stock'       => 5,

                    'is_active'       => 1,

                    'point'           => round($price / 10, 2),
                ]
            );
        }

        $this->command->info(
            count($products) . ' James Cook products inserted successfully!'
        );
    }
}
