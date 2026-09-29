<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\District;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = json_decode(
            file_get_contents(database_path('data/districts.json')),
            true
        );

        $rows = collect($json)
            ->firstWhere('type', 'table')['data'] ?? [];

        foreach ($rows as $row) {
            District::updateOrCreate(
                ['id' => (int) $row['id']],
                [
                    'division_id' => (int) $row['division_id'],
                    'name'        => $row['name'],
                    'bn_name'     => $row['bn_name'],
                    'url'         => $row['url'],
                ]
            );
        }
    }
}
