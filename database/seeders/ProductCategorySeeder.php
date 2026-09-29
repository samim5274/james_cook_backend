<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductCategory;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Electronics',
            'Mobile Phones',
            'Computers & Laptops',
            'Home Appliances',
            'Fashion',
            'Men\'s Fashion',
            'Women\'s Fashion',
            'Beauty & Personal Care',
            'Health & Wellness',
            'Groceries',
            'Baby & Kids',
            'Home & Living',
            'Kitchen & Dining',
            'Furniture',
            'Sports & Outdoors',
            'Automotive',
            'Books & Stationery',
            'Toys & Games',
            'Pet Supplies',
            'Jewellery & Watches',
        ];

        foreach ($categories as $key => $cat) {

            $slug = Str::slug($cat);

            ProductCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    // Basic
                    'name' => $cat,
                    'slug' => $slug,
                    'description' => "Browse {$cat} products at the best price in Bangladesh.",
                    'image' => null,

                    // SEO
                    'meta_title' => "{$cat} | Buy Online in Bangladesh | Ogrova",

                    'meta_description' => "Shop {$cat} online in Bangladesh from Ogrova. Get original products, best prices, fast delivery and Cash on Delivery nationwide.",

                    'meta_keywords' => implode(', ', [
                        $cat,
                        'Bangladesh',
                        'Online Shopping',
                        'Ogrova',
                        'Pharmacy',
                        'Healthcare',
                    ]),

                    // Open Graph
                    'og_title' => "{$cat} | Ogrova Bangladesh",

                    'og_description' => "Buy {$cat} online with fast delivery and secure shopping at Ogrova.",

                    'og_image' => null,

                    // Canonical
                    'canonical_url' => "https://ogrova.com/category/{$slug}",

                    // Search Engine
                    'indexable' => true,

                    // Status
                    'sort_order' => $key + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
