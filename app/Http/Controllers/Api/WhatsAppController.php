<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $enabled = Setting::getValue('whatsapp_enabled', 'false');
        if ($enabled !== 'true') {
            return response()->json(['message' => 'الواتساب غير مفعل'], 400);
        }

        $apiUrl = Setting::getValue('whatsapp_api_url', '');
        $apiKey = Setting::getValue('whatsapp_api_key', '');
        $instanceId = Setting::getValue('whatsapp_instance_id', '');

        if (!$apiUrl || !$apiKey) {
            return response()->json(['message' => 'بيانات الواتساب غير مكتملة'], 400);
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post(rtrim($apiUrl, '/') . '/message/send', [
                'instance_id' => $instanceId,
                'phone' => $request->phone,
                'message' => $request->message,
            ]);

            if ($response->successful()) {
                return response()->json(['message' => 'تم إرسال الرسالة', 'response' => $response->json()]);
            }

            return response()->json(['message' => 'فشل إرسال الرسالة', 'error' => $response->body()], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => 'خطأ في الاتصال: ' . $e->getMessage()], 500);
        }
    }

    public function sendPurchaseRequest(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'supplier' => 'required|string',
            'items' => 'required|array',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $message = "طلب شراء جديد من {$request->supplier}\n\n";
        $message .= "المواد المطلوبة:\n";
        foreach ($request->items as $item) {
            $message .= "- {$item['name']}: {$item['quantity']}\n";
        }
        $message .= "\nيرجى تأكيد الطلب";

        $whatsappReq = new Request(['phone' => $request->phone, 'message' => $message]);
        return $this->send($whatsappReq);
    }

    public function sendReport(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'type' => 'required|in:summary,inventory,purchases,sales,orders',
        ]);

        $report = $this->generateReportText($request->type);

        $whatsappReq = new Request(['phone' => $request->phone, 'message' => $report]);
        return $this->send($whatsappReq);
    }

    private function generateReportText(string $type): string
    {
        $company = Setting::getValue('report_company_name', 'شركتي');
        $date = now()->format('Y-m-d');
        $text = "📊 تقرير {$company}\nتاريخ: {$date}\n\n";

        switch ($type) {
            case 'summary':
                $text .= $this->getSummaryText();
                break;
            case 'inventory':
                $text .= $this->getInventoryText();
                break;
            case 'purchases':
                $text .= $this->getPurchasesText();
                break;
            case 'sales':
                $text .= $this->getSalesText();
                break;
            case 'orders':
                $text .= $this->getOrdersText();
                break;
        }

        return $text;
    }

    private function getSummaryText(): string
    {
        $totalItems = \App\Models\Item::count();
        $lowStock = \App\Models\Item::where('quantity', '>', 0)->whereColumn('quantity', '<=', 'min_quantity')->count();
        $totalPurchases = \App\Models\Purchase::whereDate('created_at', today())->sum('grand_total');
        $totalSales = \App\Models\PosSale::whereDate('created_at', today())->sum('grand_total');

        return "📈 ملخص عام:\n- إجمالي الأصناف: {$totalItems}\n- أصناف منخفضة: {$lowStock}\n- مشتريات اليوم: {$totalPurchases} ر.س\n- مبيعات اليوم: {$totalSales} ر.س";
    }

    private function getInventoryText(): string
    {
        $warehouses = \App\Models\Warehouse::withCount('items')->get();
        $text = "📦 حركة المخازن:\n";
        foreach ($warehouses as $w) {
            $text .= "- {$w->name}: {$w->items_count} صنف\n";
        }
        return $text;
    }

    private function getPurchasesText(): string
    {
        $purchases = \App\Models\Purchase::whereDate('created_at', today())->with('supplier')->get();
        $text = "🛒 مشتريات اليوم:\n";
        if ($purchases->isEmpty()) return $text . "لا توجد مشتريات اليوم\n";
        foreach ($purchases as $p) {
            $text .= "- فاتورة {$p->invoice_number}: {$p->grand_total} ر.س ({$p->supplier?->name})\n";
        }
        return $text;
    }

    private function getSalesText(): string
    {
        $sales = \App\Models\PosSale::whereDate('created_at', today())->get();
        $text = "💰 مبيعات اليوم:\n";
        if ($sales->isEmpty()) return $text . "لا توجد مبيعات اليوم\n";
        foreach ($sales as $s) {
            $text .= "- فاتورة #{$s->id}: {$s->grand_total} ر.س\n";
        }
        return $text;
    }

    private function getOrdersText(): string
    {
        $orders = \App\Models\Order::whereDate('created_at', today())->get();
        $text = "📋 طلبات اليوم:\n";
        if ($orders->isEmpty()) return $text . "لا توجد طلبات اليوم\n";
        foreach ($orders as $o) {
            $text .= "- طلب #{$o->id}: {$o->status}\n";
        }
        return $text;
    }
}
