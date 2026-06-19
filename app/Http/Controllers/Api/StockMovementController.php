<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $movements = StockMovement::with(['item', 'user'])
            ->when($request->item_id, function ($q, $v) {
                $q->where('item_id', $v);
            })
            ->when($request->type, function ($q, $v) {
                $q->where('type', $v);
            })
            ->when($request->from_date, function ($q, $v) {
                $q->whereDate('created_at', '>=', $v);
            })
            ->when($request->to_date, function ($q, $v) {
                $q->whereDate('created_at', '<=', $v);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($movements);
    }
}
