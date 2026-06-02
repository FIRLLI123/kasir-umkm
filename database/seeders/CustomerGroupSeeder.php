<?php

namespace Database\Seeders;

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
        $groups = [
            ['group_code' => 'USER', 'group_name' => 'USER'],
            ['group_code' => 'FREELANCER', 'group_name' => 'FREELANCER'],
            ['group_code' => 'GROSIR', 'group_name' => 'GROSIR'],
        ];

        foreach ($groups as $group) {
            CustomerGroup::updateOrCreate(
                ['group_code' => $group['group_code']],
                [
                    'group_name' => $group['group_name'],
                    'status' => '00',
                ]
            );
        }
    }
}
