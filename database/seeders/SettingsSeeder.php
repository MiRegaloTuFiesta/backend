<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'flow_fee_percent' => '3.19',
            'flow_fee_fixed' => '0',
            'mp_fee_percent' => '3.49',
            'mp_fee_fixed' => '0',
            'platform_fee_percent' => '5.0',
            'iva_percent' => '19.0',
            'min_contribution_amount' => '1000',
            'payout_days' => '3',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
