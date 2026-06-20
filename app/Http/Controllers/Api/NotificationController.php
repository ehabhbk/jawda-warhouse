<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $monthLater = now()->addDays(30)->endOfDay();

        $lowStock = Item::with('warehouse')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'min_quantity')
            ->get()
            ->map(fn ($item) => [
                'type' => 'low_stock',
                'message' => "{$item->name} - الكمية المتبقية {$item->quantity} ({$item->warehouse?->name})",
                'item' => $item,
            ]);

        $approachingExpiry = Item::with('warehouse')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $today)
            ->where('expiry_date', '<=', $monthLater)
            ->get()
            ->map(fn ($item) => [
                'type' => 'approaching_expiry',
                'message' => "{$item->name} - ينتهي {$item->expiry_date->format('Y-m-d')} ({$item->warehouse?->name})",
                'item' => $item,
            ]);

        $expired = Item::with('warehouse')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->get()
            ->map(fn ($item) => [
                'type' => 'expired',
                'message' => "{$item->name} - منتهي منذ {$item->expiry_date->format('Y-m-d')} ({$item->warehouse?->name})",
                'loss' => $item->quantity * $item->purchase_price,
                'item' => $item,
            ]);

        $totalLoss = $expired->sum('loss');
        $totalLowStock = $lowStock->count();
        $totalApproachingExpiry = $approachingExpiry->count();
        $totalExpired = $expired->count();

        return response()->json([
            'notifications' => collect()
                ->merge($lowStock)
                ->merge($approachingExpiry)
                ->merge($expired)
                ->sortByDesc(fn ($n) => $n['type'] === 'expired' ? 0 : ($n['type'] === 'approaching_expiry' ? 1 : 2))
                ->values(),
            'summary' => [
                'total' => $totalLowStock + $totalApproachingExpiry + $totalExpired,
                'low_stock' => $totalLowStock,
                'approaching_expiry' => $totalApproachingExpiry,
                'expired' => $totalExpired,
                'total_loss' => $totalLoss,
            ],
        ]);
    }
}
