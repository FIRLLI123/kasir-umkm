<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $company = Company::where('company_code', 'DEMO')->firstOrFail();

        $methods = [
            ['method_code' => 'CASH', 'method_name' => 'CASH'],
            ['method_code' => 'TRANSFER', 'method_name' => 'TRANSFER'],
            ['method_code' => 'QRIS', 'method_name' => 'QRIS'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'method_code' => $method['method_code'],
                ],
                [
                    'company_id' => $company->id,
                    'method_name' => $method['method_name'],
                    'status' => '00',
                ]
            );
        }
    }
}
