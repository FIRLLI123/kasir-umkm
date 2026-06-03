<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $company = Company::where('company_code', 'DEMO')->firstOrFail();

        $settings = [
            'store_name' => 'Kasir UMKM',
            'store_address' => '',
            'store_phone' => '',
            'receipt_footer' => 'Terima kasih sudah berbelanja',
        ];

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'setting_key' => $key,
                ],
                [
                    'company_id' => $company->id,
                    'setting_value' => $value,
                    'status' => '00',
                ]
            );
        }
    }
}
