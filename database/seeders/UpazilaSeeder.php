<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Upazila;
use App\Models\District;

class UpazilaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = json_decode(
            file_get_contents(database_path('data/upazilas.json')),
            true
        );

        $rows = collect($json)
            ->firstWhere('type', 'table')['data'] ?? [];

        foreach ($rows as $row) {

            $districtId = (int) ($row['district_id'] ?? 0);

            $district = District::find($districtId);

            // Skip if district does not exist
            if (!$district) {
                continue;
            }

            Upazila::updateOrCreate(
                ['id' => (int) $row['id']],
                [
                    'division_id' => $district->division_id,
                    'district_id' => $districtId,
                    'name'        => $row['name'] ?? null,
                    'bn_name'     => $row['bn_name'] ?? null,
                    'url'         => $row['url'] ?? null,
                ]
            );
        }
    }
}
