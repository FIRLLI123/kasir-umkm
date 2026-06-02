<?php

namespace Database\Seeders;

use App\Models\AppSetting;
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
        $settings = [
            'store_name' => 'Kasir UMKM',
            'store_address' => '',
            'store_phone' => '',
            'receipt_footer' => 'Terima kasih sudah berbelanja',
        ];

        foreach ($settings as $key => $value) {
            AppSetting::updateOrCreate(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'status' => '00',
                ]
            );
        }
    }
}
