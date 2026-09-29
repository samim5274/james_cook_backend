<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductSubCategory;
use App\Models\ProductCategory;
use Illuminate\Support\Str;

class ProductSubCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [

            'Electronics' => [
                'Television',
                'Audio',
                'Camera',
                'Accessories',
            ],

            'Mobile Phones' => [
                'Smartphones',
                'Feature Phones',
                'Phone Cases',
                'Chargers',
                'Power Banks',
                'Screen Protectors',
            ],

            'Computers & Laptops' => [
                'Laptops',
                'Desktop Computers',
                'Monitors',
                'Printers',
                'Computer Accessories',
            ],

            'Home Appliances' => [
                'Refrigerators',
                'Air Conditioners',
                'Washing Machines',
                'Microwave Ovens',
                'Vacuum Cleaners',
            ],

            'Fashion' => [
                'Clothing',
                'Shoes',
                'Bags',
                'Accessories',
            ],

            "Men's Fashion" => [
                'T-Shirts',
                'Shirts',
                'Pants',
                'Shoes',
                'Watches',
            ],

            "Women's Fashion" => [
                'Dresses',
                'Sarees',
                'Salwar Kameez',
                'Handbags',
                'Jewellery',
            ],

            'Beauty & Personal Care' => [
                'Skin Care',
                'Hair Care',
                'Makeup',
                'Perfume',
                'Personal Hygiene',
            ],

            'Health & Wellness' => [
                'Medicines',
                'Vitamins',
                'Supplements',
                'Medical Devices',
                'First Aid',
            ],

            'Groceries' => [
                'Rice',
                'Cooking Oil',
                'Beverages',
                'Snacks',
                'Spices',
            ],

            'Baby & Kids' => [
                'Baby Food',
                'Diapers',
                'Baby Care',
                'Toys',
                'Kids Clothing',
            ],

            'Home & Living' => [
                'Home Decor',
                'Lighting',
                'Storage',
                'Cleaning Supplies',
            ],

            'Kitchen & Dining' => [
                'Cookware',
                'Dinner Sets',
                'Kitchen Tools',
                'Water Bottles',
            ],

            'Furniture' => [
                'Sofas',
                'Beds',
                'Dining Tables',
                'Office Furniture',
            ],

            'Sports & Outdoors' => [
                'Fitness Equipment',
                'Sports Wear',
                'Camping',
                'Cycling',
            ],

            'Automotive' => [
                'Car Accessories',
                'Motorbike Accessories',
                'Engine Oil',
                'Tyres',
            ],

            'Books & Stationery' => [
                'Books',
                'Notebooks',
                'Pens',
                'Office Supplies',
            ],

            'Toys & Games' => [
                'Educational Toys',
                'Board Games',
                'Remote Control Toys',
                'Puzzles',
            ],

            'Pet Supplies' => [
                'Pet Food',
                'Pet Toys',
                'Pet Grooming',
                'Pet Accessories',
            ],

            'Jewellery & Watches' => [
                'Necklaces',
                'Rings',
                'Bracelets',
                'Watches',
            ],
        ];

        foreach ($data as $categoryName => $subCategories) {

            $category = ProductCategory::where(
                'slug',
                Str::slug($categoryName)
            )->first();

            if (!$category) {
                $this->command->warn("Category not found: {$categoryName}");
                continue;
            }

            foreach ($subCategories as $index => $subCategory) {

                $slug = Str::slug($categoryName . '-' . $subCategory);

                ProductSubCategory::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'slug' => $slug,
                    ],
                    [
                        'name' => $subCategory,
                        'description' => "{$subCategory} under {$categoryName}",
                        'image' => null,
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
