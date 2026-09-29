<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Vendor;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Default Password
        |--------------------------------------------------------------------------
        */

        $password = Hash::make('password');


        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'email' => 'super-admin@gmail.com',
            ],
            [
                'name' => 'Super Admin',
                'user_id' => 'SA-0001',
                'phone' => '01711111111',
                'password' => $password,
                'role' => 'super_admin',
                'designation' => 'Super Administrator',
                'is_active' => true,
                'is_profile_completed' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'email' => 'admin@gmail.com',
            ],
            [
                'name' => 'Admin One',
                'user_id' => 'AD-0001',
                'phone' => '01711111112',
                'password' => $password,
                'role' => 'admin',
                'designation' => 'Administrator',
                'is_active' => true,
                'is_profile_completed' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );


        User::updateOrCreate(
            [
                'email' => 'admin2@gmail.com',
            ],
            [
                'name' => 'Admin Two',
                'user_id' => 'AD-0002',
                'phone' => '01711111113',
                'password' => $password,
                'role' => 'admin',
                'designation' => 'Administrator',
                'is_active' => true,
                'is_profile_completed' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'email' => 'staff1@gmail.com',
            ],
            [
                'name' => 'Staff One',
                'user_id' => 'ST-0001',
                'phone' => '01711111114',
                'password' => $password,
                'role' => 'staff',
                'designation' => 'POS Staff',
                'is_active' => true,
                'is_profile_completed' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );


        User::updateOrCreate(
            [
                'email' => 'staff2@gmail.com',
            ],
            [
                'name' => 'Staff Two',
                'user_id' => 'ST-0002',
                'phone' => '01711111115',
                'password' => $password,
                'role' => 'staff',
                'designation' => 'POS Staff',
                'is_active' => true,
                'is_profile_completed' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

    }
}
