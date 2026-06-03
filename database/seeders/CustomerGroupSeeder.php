<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $company = Company::where('company_code', 'DEMO')->firstOrFail();

        $groups = [
            ['group_code' => 'USER', 'group_name' => 'USER'],
            ['group_code' => 'FREELANCER', 'group_name' => 'FREELANCER'],
            ['group_code' => 'GROSIR', 'group_name' => 'GROSIR'],
        ];

        foreach ($groups as $group) {
            CustomerGroup::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'group_code' => $group['group_code'],
                ],
                [
                    'company_id' => $company->id,
                    'group_name' => $group['group_name'],
                    'status' => '00',
                ]
            );
        }
    }
}
