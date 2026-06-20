<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\PosSale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

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

        $movements = \App\Models\StockMovement::with(['item', 'user'])
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
            'totalSuppliers' => \App\Models\Supplier::count(),
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

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'directionality' => 'rtl',
            'autoArabic' => true,
        ]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->WriteHTML($html);

        $filename = 'تقرير_مخازن_' . now()->format('Ymd_His') . '.pdf';
        return response($mpdf->Output($filename, 'S'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
