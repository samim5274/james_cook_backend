<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplyerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supplyers = [
            [
                'name' => 'James Coke',
                'company_name' => 'James Coke',
                'code' => 'SUP-0001',
                'phone' => '01856668991',
                'phone_alt' => '01856668991',
                'email' => 'jamescoke@gmail.com',
                'address' => 'House # 15, Road # 03, Nikunja - 2, Dhaka - 1229',
                'city' => 'Dhaka',
                'postal_code' => '1229',
                'country' => 'Bangladesh',
                'trade_license' => '',
                'tax_number' => '',
                'opening_balance' => 0,
                'credit_limit' => 0,
                'credit_days' => 30,
                'status' => 'active',
                'notes' => '',
            ],
        ];

        DB::table('supplyers')->insert($supplyers);
    }
}
