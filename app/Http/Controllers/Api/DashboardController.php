<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Order;
use App\Models\PosSale;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $warehouses = Warehouse::withCount('items')->get();

        $itemsPerWarehouse = $warehouses->map(fn ($w) => [
            'name' => $w->name,
            'count' => $w->items_count,
        ]);

        $purchasesByStatus = [
            'pending' => Purchase::where('status', 'pending')->count(),
            'completed' => Purchase::where('status', 'completed')->count(),
            'cancelled' => Purchase::where('status', 'cancelled')->count(),
        ];

        $ordersByStatus = [
            'pending' => Order::where('status', 'pending')->count(),
            'approved' => Order::where('status', 'approved')->count(),
            'received' => Order::where('status', 'received')->count(),
            'completed' => Order::where('status', 'completed')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        return response()->json([
            'total_items' => Item::count(),
            'total_categories' => \App\Models\Category::count(),
            'total_shelves' => \App\Models\Shelf::count(),
            'total_warehouses' => Warehouse::count(),
            'total_suppliers' => \App\Models\Supplier::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'total_purchases' => Purchase::count(),
            'low_stock_items' => Item::whereColumn('quantity', '<=', 'min_quantity')->count(),
            'expired_items' => Item::whereNotNull('expiry_date')->where('expiry_date', '<', $today)->count(),
            'approaching_expiry' => Item::whereNotNull('expiry_date')->where('expiry_date', '>=', $today)->where('expiry_date', '<=', now()->addDays(30))->count(),

            'today_purchases' => Purchase::whereDate('created_at', $today)->count(),
            'today_purchases_total' => Purchase::whereDate('created_at', $today)->sum('grand_total'),
            'month_purchases_total' => Purchase::where('created_at', '>=', $monthStart)->sum('grand_total'),

            'today_sales' => PosSale::whereDate('created_at', $today)->count(),
            'today_sales_total' => PosSale::whereDate('created_at', $today)->sum('grand_total'),
            'month_sales_total' => PosSale::where('created_at', '>=', $monthStart)->sum('grand_total'),

            'total_sales' => PosSale::count(),

            'purchases_by_status' => $purchasesByStatus,
            'orders_by_status' => $ordersByStatus,
            'items_per_warehouse' => $itemsPerWarehouse,

            'recent_movements' => StockMovement::with(['item.warehouse', 'user'])
                ->latest()->limit(10)->get(),
        ]);
    }

    public function chartData(): JsonResponse
    {
        $purchasesChart = Purchase::select(
            DB::raw("DATE_FORMAT(purchase_date, '%Y-%m') as month"),
            DB::raw('SUM(grand_total) as total')
        )
            ->where('purchase_date', '>=', now()->startOfYear())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $ordersChart = Order::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->startOfYear())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $salesChart = PosSale::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            DB::raw('SUM(grand_total) as total'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->startOfYear())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $itemsByCategory = Item::select('category_id', DB::raw('COUNT(*) as count'))
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get()
            ->map(fn ($i) => ['name' => $i->category?->name ?? 'بدون تصنيف', 'count' => $i->count]);

        return response()->json([
            'purchases' => $purchasesChart,
            'orders' => $ordersChart,
            'sales' => $salesChart,
            'items_by_category' => $itemsByCategory,
        ]);
    }
}
