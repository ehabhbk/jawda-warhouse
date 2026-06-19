<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_items' => Item::count(),
            'total_categories' => \App\Models\Category::count(),
            'total_shelves' => \App\Models\Shelf::count(),
            'total_warehouses' => \App\Models\Warehouse::count(),
            'total_suppliers' => \App\Models\Supplier::count(),
            'total_users' => User::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'total_purchases' => Purchase::count(),
            'low_stock_items' => Item::whereColumn('quantity', '<=', 'min_quantity')->count(),
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

        return response()->json([
            'purchases' => $purchasesChart,
            'orders' => $ordersChart,
        ]);
    }
}
