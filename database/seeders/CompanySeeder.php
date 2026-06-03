<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Company::updateOrCreate(
            ['company_code' => 'DEMO'],
            [
                'company_name' => 'Demo Company',
                'status' => 1,
            ]
        );
    }
}
