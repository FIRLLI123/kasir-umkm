<?php

return [
    'trial_days' => (int) env('TRIAL_DAYS', 14),
    'ai_trial_daily_limit' => (int) env('AI_TRIAL_DAILY_LIMIT', 3),

    'plans' => [
        // [
        //     'code' => 'promo_30_hari',
        //     'name' => 'Promo 30 Hari',
        //     'price' => 30000,
        //     'duration_days' => 30,
        //     'is_lifetime' => false,
        //     'description' => 'Paket promo 30 hari seharga Rp30.000.',
        // ],
        // [
        //     'code' => 'promo_6_bulan',
        //     'name' => 'Promo 6 Bulan',
        //     'price' => 150000,
        //     'duration_days' => 180,
        //     'is_lifetime' => false,
        //     'description' => 'Paket promo 6 bulan seharga Rp150.000.',
        // ],
        [
            'code' => 'lifetime',
            'name' => 'Lifetime',
            'price' => 299999,
            'duration_days' => null,
            'is_lifetime' => true,
            'description' => 'Paket lifetime seharga Rp299.999.',
        ],
    ],
];
