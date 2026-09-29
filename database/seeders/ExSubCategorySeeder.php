<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\ExCategory;
use App\Models\ExSubCategory;

class ExSubCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Office'        => ['Stationery', 'Printer Ink', 'Furniture'],
            'Transport'     => ['Fuel', 'Bus Fare', 'Bike Repair'],
            'Food'          => ['Lunch', 'Snacks', 'Tea Coffee'],
            'Utilities'     => ['Electricity', 'Internet', 'Water'],
            'Maintenance'   => ['Cleaning', 'Repair', 'Service'],
            'Marketing'     => ['Facebook Ads', 'Google Ads', 'Banner'],
        ];

        foreach ($data as $categoryName => $subs) {

            $category = ExCategory::where('name', $categoryName)->first();

            foreach ($subs as $sub) {
                ExSubCategory::create([
                    'category_id' => $category->id,
                    'name' => $sub
                ]);
            }
        }
    }
}
