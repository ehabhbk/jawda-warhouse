<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use App\Models\Item;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\PosSale;
use Barryvdh\DomPDF\PDF;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function summary(): JsonResponse
    {
        $data = [
            'total_items' => Item::count(),
            'total_warehouses' => Warehouse::count(),
            'total_categories' => Category::count(),
            'total_suppliers' => Supplier::count(),
            'total_purchases' => Purchase::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'low_stock_items' => Item::whereRaw('quantity <= min_quantity')->count(),
            'out_of_stock_items' => Item::where('quantity', 0)->count(),
            'total_purchase_amount' => Purchase::where('status', 'completed')->sum('grand_total'),
        ];

        return response()->json($data);
    }

    public function inventoryByWarehouse(): JsonResponse
    {
        $warehouses = Warehouse::withCount('items')->get()->map(function ($w) {
            $items = Item::where('warehouse_id', $w->id)->get();
            return [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
                'items_count' => $w->items_count,
                'total_quantity' => $items->sum('quantity'),
                'total_value' => $items->sum(fn($i) => $i->quantity * $i->purchase_price),
                'low_stock_count' => $items->filter(fn($i) => $i->quantity <= $i->min_quantity)->count(),
            ];
        });

        return response()->json($warehouses);
    }

    public function lowStockItems(): JsonResponse
    {
        $items = Item::with(['category', 'warehouse'])
            ->whereRaw('quantity <= min_quantity')
            ->orderBy('quantity')
            ->get();

        return response()->json($items);
    }

    public function purchasesByPeriod(Request $request): JsonResponse
    {
        $period = $request->period ?? 'monthly';
        $year = $request->year ?? Carbon::now()->year;

        $query = Purchase::where('status', 'completed')
            ->whereYear('purchase_date', $year);

        $data = match ($period) {
            'monthly' => $query->selectRaw('MONTH(purchase_date) as period, COUNT(*) as count, SUM(grand_total) as total')
                ->groupByRaw('MONTH(purchase_date)')
                ->orderBy('period')
                ->get(),
            'weekly' => $query->selectRaw('WEEK(purchase_date) as period, COUNT(*) as count, SUM(grand_total) as total')
                ->groupByRaw('WEEK(purchase_date)')
                ->orderBy('period')
                ->get(),
            'daily' => $query->selectRaw('DATE(purchase_date) as period, COUNT(*) as count, SUM(grand_total) as total')
                ->groupByRaw('DATE(purchase_date)')
                ->orderBy('period')
                ->get(),
            default => [],
        };

        return response()->json($data);
    }

    public function ordersByStatus(): JsonResponse
    {
        $data = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return response()->json($data);
    }

    public function movementsByPeriod(Request $request): JsonResponse
    {
        $period = $request->period ?? 'monthly';
        $year = $request->year ?? Carbon::now()->year;

        $query = StockMovement::whereYear('created_at', $year);

        $data = match ($period) {
            'monthly' => $query->selectRaw('MONTH(created_at) as period, type, SUM(quantity) as total')
                ->groupByRaw('MONTH(created_at), type')
                ->orderBy('period')
                ->get(),
            'daily' => $query->selectRaw('DATE(created_at) as period, type, SUM(quantity) as total')
                ->groupByRaw('DATE(created_at), type')
                ->orderBy('period')
                ->get(),
            default => [],
        };

        return response()->json($data);
    }

    public function pdf(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $purchases = Purchase::with('supplier')
            ->withCount('items')
            ->whereBetween('purchase_date', [$from, $to])
            ->orderBy('purchase_date')
            ->get();

        $orders = Order::with(['user', 'warehouse'])
            ->withCount('items')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $sales = PosSale::with('user')
            ->withCount('items')
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->get();

        $warehouses = Warehouse::withCount('items')->get()->map(function ($w) {
            $items = Item::where('warehouse_id', $w->id)->get();
            return [
                'name' => $w->name,
                'code' => $w->code,
                'items_count' => $w->items_count,
                'total_quantity' => $items->sum('quantity'),
                'total_value' => $items->sum(fn($i) => $i->quantity * $i->purchase_price),
                'low_stock_count' => $items->filter(fn($i) => $i->quantity <= $i->min_quantity)->count(),
            ];
        });

        $movements = StockMovement::with(['item', 'user'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit(50)
            ->get();

        $data = [
            'fromDate' => $from->format('Y-m-d'),
            'toDate' => $to->format('Y-m-d'),
            'totalItems' => Item::count(),
            'totalWarehouses' => Warehouse::count(),
            'totalPurchases' => $purchases->count(),
            'totalOrders' => $orders->count(),
            'totalSales' => $sales->count(),
            'totalSuppliers' => Supplier::count(),
            'lowStockItems' => Item::whereRaw('quantity <= min_quantity')->count(),
            'expiredItems' => Item::whereNotNull('expiry_date')->where('expiry_date', '<', now())->count(),
            'totalPurchaseAmount' => $purchases->sum('grand_total'),
            'totalSaleAmount' => $sales->sum('grand_total'),
            'purchases' => $purchases,
            'orders' => $orders,
            'sales' => $sales,
            'inventory' => $warehouses,
            'movements' => $movements,
        ];

        $html = view('reports.pdf', $data)->render();

        $pdf = PDF::loadHtml($html, 'utf-8');
        $pdf->setPaper('a4', 'portrait');

        $filename = 'تقرير_مخازن_' . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }

    public function orderReceipt(Order $order)
    {
        $order->load([
            'items.item',
            'user',
            'storekeeper',
            'warehouse',
        ]);

        $statusLabels = [
            'pending' => 'قيد الانتظار',
            'approved' => 'تمت الموافقة',
            'rejected' => 'مرفوض',
            'completed' => 'مكتمل',
            'received' => 'تم الاستلام',
        ];

        $statusColors = [
            'pending' => '#f59e0b',
            'approved' => '#3b82f6',
            'rejected' => '#ef4444',
            'completed' => '#10b981',
            'received' => '#6366f1',
        ];

        $statusLabel = $statusLabels[$order->status] ?? $order->status;
        $statusColor = $statusColors[$order->status] ?? '#6b7280';

        $itemsHtml = '';
        foreach ($order->items as $index => $orderItem) {
            $unitType = $orderItem->unit_type === 'sub' ? 'فرعي' : 'رئيسي';
            $unitTypeBadge = $orderItem->unit_type === 'sub'
                ? '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:11px;">فرعي</span>'
                : '<span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:4px;font-size:11px;">رئيسي</span>';

            $itemName = $orderItem->item->name ?? 'غير محدد';
            $itemUnit = $orderItem->item->unit ?? '-';

            $itemsHtml .= "
                <tr>
                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$index}</td>
                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$itemName}</td>
                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$unitTypeBadge}</td>
                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:bold;'>{$orderItem->quantity}</td>
                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$itemUnit}</td>
                </tr>";
        }

        $userName = $order->user->full_name ?? $order->user->name ?? '-';
        $storekeeperName = $order->storekeeper->full_name ?? $order->storekeeper->name ?? '-';
        $warehouseName = $order->warehouse->name ?? '-';
        $orderDate = $order->created_at ? $order->created_at->format('Y-m-d H:i') : '-';
        $orderNumber = $order->order_number ?? $order->id;

        $html = "
        <!DOCTYPE html>
        <html dir='rtl' lang='ar'>
        <head>
            <meta charset='UTF-8'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; color: #1f2937; }
                .receipt-container { padding: 30px; }
                .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 20px; margin-bottom: 25px; }
                .company-name { font-size: 24px; font-weight: bold; color: #1e40af; margin-bottom: 5px; }
                .receipt-title { font-size: 16px; color: #4b5563; }
                .info-grid { display: flex; flex-wrap: wrap; justify-content: space-between; margin-bottom: 25px; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; }
                .info-item { width: 48%; margin-bottom: 10px; }
                .info-label { font-size: 12px; color: #6b7280; margin-bottom: 2px; }
                .info-value { font-size: 14px; font-weight: bold; color: #111827; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background: #1e40af; color: white; padding: 12px 10px; font-size: 13px; text-align: center; }
                .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; color: white; font-size: 13px; font-weight: bold; }
                .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #e5e7eb; color: #9ca3af; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='receipt-container'>
                <div class='header'>
                    <div class='company-name'>نظام إدارة المخازن</div>
                    <div class='receipt-title'>سِجل استلام الطلب</div>
                </div>

                <div class='info-grid'>
                    <div class='info-item'>
                        <div class='info-label'>رقم الطلب</div>
                        <div class='info-value'>#{$orderNumber}</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>التاريخ</div>
                        <div class='info-value'>{$orderDate}</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>المطالب</div>
                        <div class='info-value'>{$userName}</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'> أمين المستودع</div>
                        <div class='info-value'>{$storekeeperName}</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>المخزن</div>
                        <div class='info-value'>{$warehouseName}</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>الحالة</div>
                        <div class='info-value'><span class='status-badge' style='background:{$statusColor};'>{$statusLabel}</span></div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style='width:8%;'>#</th>
                            <th style='width:35%;'>اسم الصنف</th>
                            <th style='width:20%;'>نوع الوحدة</th>
                            <th style='width:20%;'>الكمية</th>
                            <th style='width:17%;'>الوحدة</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                </table>

                <div class='footer'>
                    <p>تم الإصدار بواسطة نظام إدارة المخازن - {$orderDate}</p>
                </div>
            </div>
        </body>
        </html>";

        $pdf = PDF::loadHtml($html, 'utf-8');
        $pdf->setPaper('a4', 'portrait');

        $filename = "سِجل_طلب_{$orderNumber}_" . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }

    public function warehouseReport(Request $request, Warehouse $warehouse)
    {
        $from = $request->from ? Carbon::parse($request->from) : Carbon::now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::now();

        $warehouse->load('items.category');

        $items = $warehouse->items;
        $totalQuantity = $items->sum('quantity');
        $totalValue = $items->sum(fn($i) => $i->quantity * $i->purchase_price);
        $lowStockCount = $items->filter(fn($i) => $i->quantity <= $i->min_quantity)->count();

        $itemsHtml = '';
        foreach ($items as $index => $item) {
            $value = $item->quantity * $item->purchase_price;
            $categoryName = $item->category->name ?? '-';
            $stockStatus = $item->quantity <= 0
                ? '<span style="color:#ef4444;font-weight:bold;">نفذ</span>'
                : ($item->quantity <= $item->min_quantity
                    ? '<span style="color:#f59e0b;font-weight:bold;">منخفض</span>'
                    : '<span style="color:#10b981;">متوفر</span>');

            $itemsHtml .= "
                <tr>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$index}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$item->name}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$categoryName}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:bold;'>{$item->quantity}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$item->unit}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$item->purchase_price}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$value}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$stockStatus}</td>
                </tr>";
        }

        $html = "
        <!DOCTYPE html>
        <html dir='rtl' lang='ar'>
        <head>
            <meta charset='UTF-8'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; color: #1f2937; }
                .report-container { padding: 30px; }
                .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 20px; margin-bottom: 25px; }
                .company-name { font-size: 24px; font-weight: bold; color: #1e40af; margin-bottom: 5px; }
                .report-title { font-size: 16px; color: #4b5563; }
                .summary-grid { display: flex; flex-wrap: wrap; gap: 15px; justify-content: space-between; margin-bottom: 25px; }
                .summary-card { flex: 1; min-width: 200px; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; text-align: center; }
                .summary-card .label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
                .summary-card .value { font-size: 20px; font-weight: bold; color: #1e40af; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background: #1e40af; color: white; padding: 10px 8px; font-size: 12px; text-align: center; }
                .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #e5e7eb; color: #9ca3af; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='report-container'>
                <div class='header'>
                    <div class='company-name'>نظام إدارة المخازن</div>
                    <div class='report-title'>تقرير المخزن: {$warehouse->name}</div>
                </div>

                <div class='summary-grid'>
                    <div class='summary-card'>
                        <div class='label'>إجمالي الأصناف</div>
                        <div class='value'>{$items->count()}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>إجمالي الكمية</div>
                        <div class='value'>{$totalQuantity}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>إجمالي القيمة</div>
                        <div class='value'>{$totalValue}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>أصناف مخزون منخفض</div>
                        <div class='value' style='color:#f59e0b;'>{$lowStockCount}</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style='width:6%;'>#</th>
                            <th style='width:22%;'>اسم الصنف</th>
                            <th style='width:15%;'>الفئة</th>
                            <th style='width:12%;'>الكمية</th>
                            <th style='width:12%;'>الوحدة</th>
                            <th style='width:13%;'>سعر الشراء</th>
                            <th style='width:12%;'>القيمة</th>
                            <th style='width:8%;'>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                </table>

                <div class='footer'>
                    <p>تقرير المخزن: {$warehouse->name} - تم الإصدار بتاريخ {$from->format('Y-m-d')} إلى {$to->format('Y-m-d')}</p>
                </div>
            </div>
        </body>
        </html>";

        $pdf = PDF::loadHtml($html, 'utf-8');
        $pdf->setPaper('a4', 'landscape');

        $filename = "تقرير_المخزن_{$warehouse->name}_" . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }

    public function departmentReport(Request $request, Department $department)
    {
        $from = $request->from ? Carbon::parse($request->from) : Carbon::now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to) : Carbon::now();

        $department->load('items.item');

        $departmentItems = $department->items;
        $totalQuantity = $departmentItems->sum('quantity');
        $totalSubQuantity = $departmentItems->sum('sub_unit_quantity');

        $departmentUserIds = $department->users()->pluck('users.id');
        $orders = Order::whereIn('user_id', $departmentUserIds)
            ->whereBetween('created_at', [$from, $to])
            ->with(['items.item'])
            ->orderBy('created_at', 'desc')
            ->get();

        $itemsHtml = '';
        foreach ($departmentItems as $index => $deptItem) {
            $itemName = $deptItem->item->name ?? '-';
            $itemUnit = $deptItem->item->unit ?? '-';

            $itemsHtml .= "
                <tr>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$index}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$itemName}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$deptItem->quantity}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$deptItem->sub_unit_quantity}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$itemUnit}</td>
                </tr>";
        }

        $ordersHtml = '';
        foreach ($orders as $index => $order) {
            $orderDate = $order->created_at->format('Y-m-d');
            $itemCount = $order->items->count();

            $statusLabels = [
                'pending' => 'قيد الانتظار',
                'approved' => 'تمت الموافقة',
                'rejected' => 'مرفوض',
                'completed' => 'مكتمل',
                'received' => 'تم الاستلام',
            ];
            $statusLabel = $statusLabels[$order->status] ?? $order->status;

            $ordersHtml .= "
                <tr>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$index}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$order->order_number}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$orderDate}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$itemCount}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$statusLabel}</td>
                </tr>";
        }

        $html = "
        <!DOCTYPE html>
        <html dir='rtl' lang='ar'>
        <head>
            <meta charset='UTF-8'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; color: #1f2937; }
                .report-container { padding: 30px; }
                .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 20px; margin-bottom: 25px; }
                .company-name { font-size: 24px; font-weight: bold; color: #1e40af; margin-bottom: 5px; }
                .report-title { font-size: 16px; color: #4b5563; }
                .summary-grid { display: flex; flex-wrap: wrap; gap: 15px; justify-content: space-between; margin-bottom: 25px; }
                .summary-card { flex: 1; min-width: 180px; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; text-align: center; }
                .summary-card .label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
                .summary-card .value { font-size: 20px; font-weight: bold; color: #1e40af; }
                .section-title { font-size: 16px; font-weight: bold; color: #1e40af; margin: 20px 0 10px 0; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background: #1e40af; color: white; padding: 10px 8px; font-size: 12px; text-align: center; }
                .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #e5e7eb; color: #9ca3af; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='report-container'>
                <div class='header'>
                    <div class='company-name'>نظام إدارة المخازن</div>
                    <div class='report-title'>تقرير القسم: {$department->name}</div>
                </div>

                <div class='summary-grid'>
                    <div class='summary-card'>
                        <div class='label'>إجمالي الأصناف</div>
                        <div class='value'>{$departmentItems->count()}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>إجمالي الكمية</div>
                        <div class='value'>{$totalQuantity}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>إجمالي الوحدات الفرعية</div>
                        <div class='value'>{$totalSubQuantity}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>عدد الطلبات في الفترة</div>
                        <div class='value'>{$orders->count()}</div>
                    </div>
                </div>

                <div class='section-title'>أصناف القسم</div>
                <table>
                    <thead>
                        <tr>
                            <th style='width:8%;'>#</th>
                            <th style='width:40%;'>اسم الصنف</th>
                            <th style='width:17%;'>الكمية</th>
                            <th style='width:17%;'>الكمية الفرعية</th>
                            <th style='width:18%;'>الوحدة</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                </table>

                <div class='section-title'>سجل الطلبات</div>
                <table>
                    <thead>
                        <tr>
                            <th style='width:8%;'>#</th>
                            <th style='width:25%;'>رقم الطلب</th>
                            <th style='width:25%;'>التاريخ</th>
                            <th style='width:22%;'>عدد الأصناف</th>
                            <th style='width:20%;'>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$ordersHtml}
                    </tbody>
                </table>

                <div class='footer'>
                    <p>تقرير القسم: {$department->name} - تم الإصدار بتاريخ {$from->format('Y-m-d')} إلى {$to->format('Y-m-d')}</p>
                </div>
            </div>
        </body>
        </html>";

        $pdf = PDF::loadHtml($html, 'utf-8');
        $pdf->setPaper('a4', 'portrait');

        $filename = "تقرير_القسم_{$department->name}_" . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }

    public function purchasesReport(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : Carbon::now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : Carbon::now()->endOfDay();

        $purchases = Purchase::with(['supplier', 'items'])
            ->whereBetween('purchase_date', [$from, $to])
            ->orderBy('purchase_date', 'desc')
            ->get();

        $totalAmount = $purchases->sum('grand_total');
        $totalTax = $purchases->sum('tax');
        $totalDiscount = $purchases->sum('discount');
        $completedCount = $purchases->where('status', 'completed')->count();
        $pendingCount = $purchases->where('status', 'pending')->count();

        $purchasesHtml = '';
        foreach ($purchases as $index => $purchase) {
            $supplierName = $purchase->supplier->name ?? '-';
            $purchaseDate = $purchase->purchase_date ? $purchase->purchase_date->format('Y-m-d') : '-';
            $itemCount = $purchase->items->count();

            $statusLabels = [
                'pending' => 'قيد الانتظار',
                'approved' => 'تمت الموافقة',
                'completed' => 'مكتمل',
                'cancelled' => 'ملغي',
            ];
            $statusLabel = $statusLabels[$purchase->status] ?? $purchase->status;

            $purchasesHtml .= "
                <tr>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$index}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$purchase->invoice_number}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$purchaseDate}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$supplierName}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$itemCount}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$purchase->total}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$purchase->tax}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$purchase->discount}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:bold;'>{$purchase->grand_total}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$statusLabel}</td>
                </tr>";
        }

        $html = "
        <!DOCTYPE html>
        <html dir='rtl' lang='ar'>
        <head>
            <meta charset='UTF-8'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; color: #1f2937; }
                .report-container { padding: 30px; }
                .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 20px; margin-bottom: 25px; }
                .company-name { font-size: 24px; font-weight: bold; color: #1e40af; margin-bottom: 5px; }
                .report-title { font-size: 16px; color: #4b5563; }
                .summary-grid { display: flex; flex-wrap: wrap; gap: 15px; justify-content: space-between; margin-bottom: 25px; }
                .summary-card { flex: 1; min-width: 150px; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; text-align: center; }
                .summary-card .label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
                .summary-card .value { font-size: 18px; font-weight: bold; color: #1e40af; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background: #1e40af; color: white; padding: 10px 6px; font-size: 11px; text-align: center; }
                .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #e5e7eb; color: #9ca3af; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='report-container'>
                <div class='header'>
                    <div class='company-name'>نظام إدارة المخازن</div>
                    <div class='report-title'>تقرير المشتريات</div>
                </div>

                <div class='summary-grid'>
                    <div class='summary-card'>
                        <div class='label'>إجمالي المشتريات</div>
                        <div class='value'>{$purchases->count()}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>المشتريات المكتملة</div>
                        <div class='value' style='color:#10b981;'>{$completedCount}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>قيد الانتظار</div>
                        <div class='value' style='color:#f59e0b;'>{$pendingCount}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>المبلغ الإجمالي</div>
                        <div class='value'>{$totalAmount}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>الضريبة</div>
                        <div class='value'>{$totalTax}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>الخصم</div>
                        <div class='value'>{$totalDiscount}</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style='width:5%;'>#</th>
                            <th style='width:12%;'>رقم الفاتورة</th>
                            <th style='width:12%;'>التاريخ</th>
                            <th style='width:16%;'>المورد</th>
                            <th style='width:8%;'>عدد الأصناف</th>
                            <th style='width:10%;'>المجموع</th>
                            <th style='width:9%;'>الضريبة</th>
                            <th style='width:9%;'>الخصم</th>
                            <th style='width:12%;'>الإجمالي</th>
                            <th style='width:10%;'>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$purchasesHtml}
                    </tbody>
                </table>

                <div class='footer'>
                    <p>تقرير المشتريات - من {$from->format('Y-m-d')} إلى {$to->format('Y-m-d')}</p>
                </div>
            </div>
        </body>
        </html>";

        $pdf = PDF::loadHtml($html, 'utf-8');
        $pdf->setPaper('a4', 'landscape');

        $filename = "تقرير_المشتريات_" . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }

    public function stockMovementsReport(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from)->startOfDay() : Carbon::now()->startOfMonth();
        $to = $request->to ? Carbon::parse($request->to)->endOfDay() : Carbon::now()->endOfDay();

        $movements = StockMovement::with(['item', 'user'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'desc')
            ->get();

        $typeLabels = [
            'in' => 'وارد',
            'out' => 'صادر',
            'adjustment' => 'تعديل',
            'transfer' => 'نقل',
            'purchase' => 'شراء',
            'return' => 'مرتجع',
            'sale' => 'بيع',
            'order' => 'طلب',
        ];

        $totalIn = $movements->where('type', 'in')->sum('quantity');
        $totalOut = $movements->where('type', 'out')->sum('quantity');
        $inCount = $movements->where('type', 'in')->count();
        $outCount = $movements->where('type', 'out')->count();

        $movementsHtml = '';
        foreach ($movements as $index => $movement) {
            $itemName = $movement->item->name ?? '-';
            $userName = $movement->user->name ?? '-';
            $movementDate = $movement->created_at->format('Y-m-d H:i');
            $typeLabel = $typeLabels[$movement->type] ?? $movement->type;

            $typeColor = match ($movement->type) {
                'in', 'purchase', 'return' => '#10b981',
                'out', 'sale', 'order' => '#ef4444',
                default => '#6b7280',
            };

            $movementsHtml .= "
                <tr>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$index}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$itemName}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$movementDate}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'><span style='color:{$typeColor};font-weight:bold;'>{$typeLabel}</span></td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:bold;'>{$movement->quantity}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$movement->price}</td>
                    <td style='padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;'>{$userName}</td>
                </tr>";
        }

        $html = "
        <!DOCTYPE html>
        <html dir='rtl' lang='ar'>
        <head>
            <meta charset='UTF-8'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; color: #1f2937; }
                .report-container { padding: 30px; }
                .header { text-align: center; border-bottom: 3px solid #1e40af; padding-bottom: 20px; margin-bottom: 25px; }
                .company-name { font-size: 24px; font-weight: bold; color: #1e40af; margin-bottom: 5px; }
                .report-title { font-size: 16px; color: #4b5563; }
                .summary-grid { display: flex; flex-wrap: wrap; gap: 15px; justify-content: space-between; margin-bottom: 25px; }
                .summary-card { flex: 1; min-width: 180px; background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; text-align: center; }
                .summary-card .label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
                .summary-card .value { font-size: 20px; font-weight: bold; color: #1e40af; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background: #1e40af; color: white; padding: 10px 8px; font-size: 12px; text-align: center; }
                .footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #e5e7eb; color: #9ca3af; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='report-container'>
                <div class='header'>
                    <div class='company-name'>نظام إدارة المخازن</div>
                    <div class='report-title'>تقرير حركة المخزون</div>
                </div>

                <div class='summary-grid'>
                    <div class='summary-card'>
                        <div class='label'>إجمالي الحركات</div>
                        <div class='value'>{$movements->count()}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>حركات واردة</div>
                        <div class='value' style='color:#10b981;'>{$inCount}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>حركات صادرة</div>
                        <div class='value' style='color:#ef4444;'>{$outCount}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>إجمالي الوارد</div>
                        <div class='value' style='color:#10b981;'>{$totalIn}</div>
                    </div>
                    <div class='summary-card'>
                        <div class='label'>إجمالي الصادر</div>
                        <div class='value' style='color:#ef4444;'>{$totalOut}</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style='width:6%;'>#</th>
                            <th style='width:25%;'>اسم الصنف</th>
                            <th style='width:18%;'>التاريخ والوقت</th>
                            <th style='width:14%;'>النوع</th>
                            <th style='width:12%;'>الكمية</th>
                            <th style='width:12%;'>السعر</th>
                            <th style='width:13%;'>المسؤول</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$movementsHtml}
                    </tbody>
                </table>

                <div class='footer'>
                    <p>تقرير حركة المخزون - من {$from->format('Y-m-d')} إلى {$to->format('Y-m-d')}</p>
                </div>
            </div>
        </body>
        </html>";

        $pdf = PDF::loadHtml($html, 'utf-8');
        $pdf->setPaper('a4', 'landscape');

        $filename = "تقرير_حركة_المخزون_" . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }
}
