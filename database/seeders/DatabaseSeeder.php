<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        // php artisan db:seed --class=SeederName
        $this->call([
            UserSeeder::class,

            ProductCategorySeeder::class,
            ProductSubCategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,

            // DivisionSeeder::class,
            // DistrictSeeder::class,
            // UpazilaSeeder::class,
            // PoliceStationSeeder::class,

            // CustomerSeeder::class,

            // ExCategorySeeder::class,
            // ExSubCategorySeeder::class,

            SupplyerSeeder::class,
        ]);
    }
}
