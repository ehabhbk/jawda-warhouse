<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'app_name', 'value' => 'نظام إدارة المخازن', 'group' => 'general'],
            ['key' => 'currency', 'value' => 'ر.س', 'group' => 'general'],
            ['key' => 'default_tax', 'value' => '0', 'group' => 'general'],
            ['key' => 'low_stock_threshold', 'value' => '10', 'group' => 'general'],
            ['key' => 'expiry_alert_days', 'value' => '30', 'group' => 'general'],

            // WhatsApp API
            ['key' => 'whatsapp_enabled', 'value' => 'false', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_api_url', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_api_key', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_instance_id', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_sender_number', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_send_purchase_requests', 'value' => 'true', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_send_low_stock_alerts', 'value' => 'true', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_send_expiry_alerts', 'value' => 'true', 'group' => 'whatsapp'],

            // Purchase defaults
            ['key' => 'purchase_prefix', 'value' => 'PUR-', 'group' => 'purchases'],
            ['key' => 'purchase_auto_number', 'value' => 'true', 'group' => 'purchases'],

            // Order defaults
            ['key' => 'order_prefix', 'value' => 'ORD-', 'group' => 'orders'],
            ['key' => 'order_auto_approve', 'value' => 'false', 'group' => 'orders'],

            // POS defaults
            ['key' => 'pos_default_discount', 'value' => '0', 'group' => 'pos'],
            ['key' => 'pos_receipt_footer', 'value' => 'شكراً لتسوقكم معنا', 'group' => 'pos'],

            // Reports
            ['key' => 'report_company_name', 'value' => 'شركتي', 'group' => 'reports'],
            ['key' => 'report_company_phone', 'value' => '', 'group' => 'reports'],
            ['key' => 'report_company_address', 'value' => '', 'group' => 'reports'],
        ];

        foreach ($settings as $s) {
            Setting::create($s);
        }
    }
}
