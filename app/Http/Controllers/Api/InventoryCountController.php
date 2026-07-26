<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryCountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $counts = InventoryCount::with(['warehouse', 'user', 'items'])
            ->when($request->status, function ($q, $v) {
                $q->where('status', $v);
            })
            ->when($request->warehouse_id, function ($q, $v) {
                $q->where('warehouse_id', $v);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($counts);
    }

    public function all(): JsonResponse
    {
        $counts = InventoryCount::with(['warehouse', 'user'])
            ->latest()
            ->get();

        return response()->json($counts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
        ]);

        $countNumber = 'CNT-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $count = InventoryCount::create([
            'count_number' => $countNumber,
            'warehouse_id' => $validated['warehouse_id'],
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($count->load(['warehouse', 'user']), 201);
    }

    public function show(InventoryCount $inventoryCount): JsonResponse
    {
        return response()->json(
            $inventoryCount->load(['warehouse', 'user', 'items.item'])
        );
    }

    public function start(InventoryCount $inventoryCount): JsonResponse
    {
        if ($inventoryCount->status !== 'pending') {
            return response()->json(['message' => 'يمكن بدء الجرود الجاهزة فقط.'], 400);
        }

        return DB::transaction(function () use ($inventoryCount) {
            $items = Item::where('warehouse_id', $inventoryCount->warehouse_id)
                ->where('is_active', true)
                ->get();

            foreach ($items as $item) {
                InventoryCountItem::create([
                    'count_id' => $inventoryCount->id,
                    'item_id' => $item->id,
                    'system_quantity' => $item->quantity,
                ]);
            }

            $inventoryCount->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            return response()->json(
                $inventoryCount->load(['warehouse', 'user', 'items.item'])
            );
        });
    }

    public function updateItem(Request $request, InventoryCount $inventoryCount, InventoryCountItem $item): JsonResponse
    {
        if ($inventoryCount->status !== 'in_progress') {
            return response()->json(['message' => 'يمكن تحديث الجرود قيد الجرد فقط.'], 400);
        }

        $validated = $request->validate([
            'actual_quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $item->update([
            'actual_quantity' => $validated['actual_quantity'],
            'difference' => $validated['actual_quantity'] - $item->system_quantity,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($item->load('item'));
    }

    public function complete(InventoryCount $inventoryCount): JsonResponse
    {
        if ($inventoryCount->status !== 'in_progress') {
            return response()->json(['message' => 'يمكن إكمال الجرود قيد الجرد فقط.'], 400);
        }

        $unfilled = $inventoryCount->items()->whereNull('actual_quantity')->count();
        if ($unfilled > 0) {
            return response()->json(['message' => "يجب إدخال الكمية الفعلية لـ {$unfilled} صنف."], 400);
        }

        $inventoryCount->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json($inventoryCount->load(['warehouse', 'user', 'items.item']));
    }

    public function cancel(InventoryCount $inventoryCount): JsonResponse
    {
        if ($inventoryCount->status !== 'in_progress') {
            return response()->json(['message' => 'يمكن إلغاء الجرود قيد الجرد فقط.'], 400);
        }

        $inventoryCount->update(['status' => 'cancelled']);

        return response()->json($inventoryCount->load(['warehouse', 'user', 'items.item']));
    }

    public function destroy(InventoryCount $inventoryCount): JsonResponse
    {
        if ($inventoryCount->status !== 'pending') {
            return response()->json(['message' => 'يمكن حذف الجرود الجاهزة فقط.'], 400);
        }

        $inventoryCount->delete();

        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
