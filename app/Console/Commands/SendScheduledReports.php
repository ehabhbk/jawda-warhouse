<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled';
    protected $description = 'Send scheduled reports via WhatsApp';

    public function handle()
    {
        $enabled = Setting::getValue('whatsapp_enabled', 'false');
        if ($enabled !== 'true') {
            $this->info('WhatsApp disabled');
            return;
        }

        $apiUrl = Setting::getValue('whatsapp_api_url', '');
        $apiKey = Setting::getValue('whatsapp_api_key', '');
        $instanceId = Setting::getValue('whatsapp_instance_id', '');

        if (!$apiUrl || !$apiKey) {
            $this->error('WhatsApp config incomplete');
            return;
        }

        $now = now();
        $time = $now->format('H:i');
        $dayOfWeek = $now->dayOfWeek;
        $dayOfMonth = $now->day;

        $schedules = ReportSchedule::where('is_active', true)->where('send_time', $time)->get();

        foreach ($schedules as $schedule) {
            $shouldSend = false;

            switch ($schedule->type) {
                case 'daily':
                    $shouldSend = true;
                    break;
                case 'weekly':
                    $shouldSend = in_array($dayOfWeek, $schedule->days ?? []);
                    break;
                case 'monthly':
                    $shouldSend = $schedule->day_of_month == $dayOfMonth;
                    break;
                case 'yearly':
                    $shouldSend = $schedule->day_of_month == $dayOfMonth && $now->format('m-d') === date('m-d', strtotime($schedule->created_at));
                    break;
            }

            if (!$shouldSend) continue;

            $phones = explode(',', $schedule->phone_numbers);
            foreach ($schedule->report_types ?? [] as $reportType) {
                $reportText = $this->generateReportText($reportType);
                foreach ($phones as $phone) {
                    $phone = trim($phone);
                    if (!$phone) continue;
                    try {
                        Http::withHeaders([
                            'apikey' => $apiKey,
                            'Content-Type' => 'application/json',
                        ])->post(rtrim($apiUrl, '/') . '/message/send', [
                            'instance_id' => $instanceId,
                            'phone' => $phone,
                            'message' => $reportText,
                        ]);
                        $this->info("Sent {$reportType} to {$phone}");
                    } catch (\Exception $e) {
                        $this->error("Failed to send to {$phone}: {$e->getMessage()}");
                    }
                }
            }

            $schedule->update(['last_sent_at' => $now]);
        }

        $this->info('Done');
    }

    private function generateReportText(string $type): string
    {
        $company = Setting::getValue('report_company_name', 'شركتي');
        $date = now()->format('Y-m-d');
        $text = "📊 تقرير {$company}\nتاريخ: {$date}\n\n";

        switch ($type) {
            case 'summary':
                $totalItems = \App\Models\Item::count();
                $lowStock = \App\Models\Item::where('quantity', '>', 0)->whereColumn('quantity', '<=', 'min_quantity')->count();
                $totalPurchases = \App\Models\Purchase::whereDate('created_at', today())->sum('grand_total');
                $totalSales = \App\Models\PosSale::whereDate('created_at', today())->sum('grand_total');
                $text .= "📈 ملخص عام:\n- إجمالي الأصناف: {$totalItems}\n- أصناف منخفضة: {$lowStock}\n- مشتريات اليوم: {$totalPurchases} ر.س\n- مبيعات اليوم: {$totalSales} ر.س";
                break;
            case 'inventory':
                $warehouses = \App\Models\Warehouse::withCount('items')->get();
                $text .= "📦 حركة المخازن:\n";
                foreach ($warehouses as $w) {
                    $text .= "- {$w->name}: {$w->items_count} صنف\n";
                }
                break;
            case 'purchases':
                $purchases = \App\Models\Purchase::whereDate('created_at', today())->with('supplier')->get();
                $text .= "🛒 مشتريات اليوم:\n";
                if ($purchases->isEmpty()) { $text .= "لا توجد مشتريات\n"; break; }
                foreach ($purchases as $p) {
                    $text .= "- فاتورة {$p->invoice_number}: {$p->grand_total} ر.س ({$p->supplier?->name})\n";
                }
                break;
            case 'sales':
                $sales = \App\Models\PosSale::whereDate('created_at', today())->get();
                $text .= "💰 مبيعات اليوم:\n";
                if ($sales->isEmpty()) { $text .= "لا توجد مبيعات\n"; break; }
                foreach ($sales as $s) {
                    $text .= "- فاتورة #{$s->id}: {$s->grand_total} ر.س\n";
                }
                break;
            case 'orders':
                $orders = \App\Models\Order::whereDate('created_at', today())->get();
                $text .= "📋 طلبات اليوم:\n";
                if ($orders->isEmpty()) { $text .= "لا توجد طلبات\n"; break; }
                foreach ($orders as $o) {
                    $text .= "- طلب #{$o->id}: {$o->status}\n";
                }
                break;
        }

        return $text;
    }
}
