<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $company = Company::where('company_code', 'DEMO')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@mail.com'],
            [
                'company_id' => $company->id,
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'ADMIN',
                'status' => '00',
            ]
        );

        User::updateOrCreate(
            ['email' => 'superadmin@mail.com'],
            [
                'company_id' => $company->id,
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'role' => 'SUPER_ADMIN',
                'status' => '00',
            ]
        );

        User::updateOrCreate(
            ['email' => 'firlli@gmail.com'],
            [
                'company_id' => $company->id,
                'name' => 'Firlli Super Admin',
                'password' => Hash::make('123456789'),
                'role' => 'SUPER_ADMIN',
                'status' => '00',
            ]
        );

        User::updateOrCreate(
            ['email' => 'sinta@gmail.com'],
            [
                'company_id' => $company->id,
                'name' => 'Sinta Super Admin',
                'password' => Hash::make('123456789'),
                'role' => 'SUPER_ADMIN',
                'status' => '00',
            ]
        );
    }
}
