<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@skymanager.local',
        ]);

        $this->seedDefaultSettings();
    }

    private function seedDefaultSettings(): void
    {
        $defaults = [
            ['key' => 'company_name',          'value' => 'SKYmanager',                   'group' => 'general',  'label' => 'Company Name'],
            ['key' => 'company_email',         'value' => '',                             'group' => 'general',  'label' => 'Support Email'],
            ['key' => 'company_phone',         'value' => '',                             'group' => 'general',  'label' => 'Support Phone'],
            ['key' => 'default_ssid',          'value' => 'SKYMANAGER-WIFI',              'group' => 'network',  'label' => 'Default SSID'],
            ['key' => 'timezone',              'value' => 'Africa/Dar_es_Salaam',         'group' => 'general',  'label' => 'Timezone'],
            ['key' => 'sms_gateway',           'value' => 'none',                        'group' => 'sms',      'label' => 'SMS Gateway'],
            ['key' => 'sms_api_key',           'value' => '',                             'group' => 'sms',      'label' => 'SMS API Key'],
            ['key' => 'sms_sender_id',         'value' => 'SKYmanager',                   'group' => 'sms',      'label' => 'SMS Sender ID'],
            ['key' => 'referral_reward_days',  'value' => 1,                              'group' => 'referral', 'label' => 'Referral Reward Days'],
            ['key' => 'portal_welcome_message', 'value' => '',                             'group' => 'portal',   'label' => 'Portal Welcome Message'],
            ['key' => 'maintenance_mode',      'value' => false,                         'group' => 'general',  'label' => 'Maintenance Mode'],
        ];

        foreach ($defaults as $row) {
            Setting::firstOrCreate(
                ['key' => $row['key']],
                ['value' => $row['value'], 'group' => $row['group'], 'label' => $row['label']]
            );
        }
    }
}
