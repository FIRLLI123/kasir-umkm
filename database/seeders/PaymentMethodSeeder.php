<?php

namespace Database\Seeders;

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
        $methods = [
            ['method_code' => 'CASH', 'method_name' => 'CASH'],
            ['method_code' => 'TRANSFER', 'method_name' => 'TRANSFER'],
            ['method_code' => 'QRIS', 'method_name' => 'QRIS'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['method_code' => $method['method_code']],
                [
                    'method_name' => $method['method_name'],
                    'status' => '00',
                ]
            );
        }
    }
}
