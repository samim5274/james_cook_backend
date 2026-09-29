<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Customer;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [

            /*
            |--------------------------------------------------------------------------
            | Customer 1
            |--------------------------------------------------------------------------
            */
            [
                'label' => 'Home',
                'customer_name' => 'Samim Hossain',
                'phone' => '01712345678',

                'division_id' => 1,
                'district_id' => 1,
                'upazila_id' => 1,
                'police_station_id' => 1,

                'address' => 'House #12, Road #5, Dhanmondi, Dhaka',
                'postal_code' => '1209',

                'is_default' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | Customer 2
            |--------------------------------------------------------------------------
            */
            [
                'label' => 'Home',
                'customer_name' => 'Rahim Ahmed',
                'phone' => '01812345678',

                'division_id' => 1,
                'district_id' => 1,
                'upazila_id' => 2,
                'police_station_id' => 2,

                'address' => 'House #24, Road #7, Banani, Dhaka',
                'postal_code' => '1213',

                'is_default' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | Customer 3
            |--------------------------------------------------------------------------
            */
            [
                'label' => 'Office',
                'customer_name' => 'Karim Hasan',
                'phone' => '01912345678',

                'division_id' => 1,
                'district_id' => 1,
                'upazila_id' => 3,
                'police_station_id' => 3,

                'address' => 'Level 4, Trade Center, Motijheel, Dhaka',
                'postal_code' => '1000',

                'is_default' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | Customer 4
            |--------------------------------------------------------------------------
            */
            [
                'label' => 'Home',
                'customer_name' => 'Nusrat Jahan',
                'phone' => '01612345678',

                'division_id' => 1,
                'district_id' => 1,
                'upazila_id' => 4,
                'police_station_id' => 4,

                'address' => 'House #8, Road #2, Uttara, Dhaka',
                'postal_code' => '1230',

                'is_default' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | Customer 5
            |--------------------------------------------------------------------------
            */
            [
                'label' => 'Home',
                'customer_name' => 'Tanvir Rahman',
                'phone' => '01512345678',

                'division_id' => 1,
                'district_id' => 1,
                'upazila_id' => 5,
                'police_station_id' => 5,

                'address' => 'House #15, College Road, Mirpur, Dhaka',
                'postal_code' => '1216',

                'is_default' => true,
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Insert Customers
        |--------------------------------------------------------------------------
        */

        foreach ($customers as $customer) {

            Customer::updateOrCreate(
                [
                    'phone' => $customer['phone'],
                ],
                $customer
            );
        }
    }
}
