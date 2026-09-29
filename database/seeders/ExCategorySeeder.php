<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\ExCategory;

class ExCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Office',
            'Transport',
            'Food',
            'Utilities',
            'Maintenance',
            'Marketing',
        ];

        foreach ($categories as $name) {
            ExCategory::create([
                'name' => $name
            ]);
        }
    }
}
