<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Division;
use App\Models\District;
use App\Models\PoliceStation;
use App\Models\Upazila;

class PoliceStationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Better to use SQL source because CSV format is not consistent for naive parsing
        $url = 'https://raw.githubusercontent.com/tanvirquader/bangladesh-divisions-districts-thana-post_offices-post_codes/master/districts.sql';

        $sql = Http::timeout(120)->get($url)->body();

        if (!$sql) {
            $this->command?->error('Failed to download districts.sql');
            return;
        }

        // Extract all tuples: (...),(...),(...)
        preg_match_all('/\((.*?)\)/s', $sql, $matches);

        if (empty($matches[1])) {
            $this->command?->error('No rows found in SQL file');
            return;
        }

        $inserted = [];
        $count = 0;
        $skipped = 0;

        foreach ($matches[1] as $tuple) {
            // Parse SQL tuple safely using CSV parser with single quote enclosure
            // Example expected row order in source:
            // id, division, district, thana, postoffice, postcode, created_at, updated_at
            $cols = str_getcsv($tuple, ',', "'");

            if (count($cols) < 4) {
                $skipped++;
                continue;
            }

            $divisionName = trim($cols[1] ?? '');
            $districtName = trim($cols[2] ?? '');
            $thanaName    = trim($cols[3] ?? '');

            if (!$divisionName || !$districtName || !$thanaName) {
                $skipped++;
                continue;
            }

            $division = Division::query()
                ->get()
                ->first(function ($d) use ($divisionName) {
                    $db = $this->normalize($d->name);
                    $src = $this->normalize($divisionName);

                    return $db === $src || in_array($db, $this->divisionFallbacks($divisionName), true);
                });

            if (!$division) {
                $skipped++;
                continue;
            }

            $district = District::query()
                ->where('division_id', $division->id)
                ->get()
                ->first(function ($d) use ($districtName) {
                    $db = $this->normalize($d->name);
                    $src = $this->normalize($districtName);

                    return $db === $src
                        || in_array($src, $this->districtFallbacks($d->name), true)
                        || in_array($db, $this->districtFallbacks($districtName), true);
                });

            if (!$district) {
                $skipped++;
                continue;
            }

            $normalizedThana = $this->normalize($thanaName);
            $uniqueKey = $district->id . '|' . $normalizedThana;

            if (isset($inserted[$uniqueKey])) {
                continue;
            }
            $inserted[$uniqueKey] = true;

            // Best effort upazila match
            $upazila = Upazila::query()
                ->where('district_id', $district->id)
                ->get()
                ->first(function ($u) use ($thanaName) {
                    $uName = $this->normalize($u->name);
                    $tName = $this->normalize($thanaName);

                    return $uName === $tName
                        || str_contains($uName, $tName)
                        || str_contains($tName, $uName)
                        || str_replace(' model', '', $uName) === str_replace(' model', '', $tName);
                });

            PoliceStation::updateOrCreate(
                [
                    'district_id' => $district->id,
                    'name'        => $thanaName,
                ],
                [
                    'division_id' => $division->id,
                    'upazila_id'  => $upazila?->id,
                    'bn_name'     => $upazila->bn_name ?? null,
                ]
            );

            $count++;
        }

        $this->command?->info("Police stations seeded: {$count}, skipped: {$skipped}");
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace(["\u{00A0}"], ' ')
            ->replace(['.', ',', '-', '_', '(', ')'], ' ')
            ->replace(["’", "`"], "'")
            ->replace(['sador'], 'sadar')
            ->replace(['chittagong'], 'chattogram')
            ->replace(['comilla'], 'cumilla')
            ->replace(['bogra'], 'bogura')
            ->replace(['jessore'], 'jashore')
            ->replace(['barisal'], 'barishal')
            ->replace(['coxsbazar'], "cox's bazar")
            ->replace(['coxs bazar'], "cox's bazar")
            ->replace(['thana'], '')
            ->replace(['police station'], '')
            ->squish()
            ->toString();
    }

    private function divisionFallbacks(string $divisionName): array
    {
        $n = $this->normalize($divisionName);

        return match ($n) {
            'chattogram' => ['chittagong', 'chattogram', 'chattagram'],
            'barishal'   => ['barisal', 'barishal'],
            default      => [$n],
        };
    }

    private function districtFallbacks(string $districtName): array
    {
        $n = $this->normalize($districtName);

        return match ($n) {
            'chattogram' => ['chittagong', 'chattogram'],
            'cumilla'    => ['comilla', 'cumilla'],
            'bogura'     => ['bogra', 'bogura'],
            'jashore'    => ['jessore', 'jashore'],
            'barishal'   => ['barisal', 'barishal'],
            default      => [$n],
        };
    }
}
