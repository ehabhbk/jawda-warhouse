<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentItem;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $departments = Department::withCount(['items', 'users'])
            ->when($request->search, function ($q, $v) {
                $q->where('name', 'like', "%$v%")
                    ->orWhere('description', 'like', "%$v%");
            })
            ->latest()
            ->paginate($request->per_page ?? 50);

        return response()->json($departments);
    }

    public function all(): JsonResponse
    {
        $departments = Department::where('is_active', true)
            ->withCount('items')
            ->get();

        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $department = Department::create($validated);

        return response()->json($department, 201);
    }

    public function show(Department $department): JsonResponse
    {
        $department->load([
            'items.item.warehouse',
            'users' => function ($q) {
                $q->where('is_active', true);
            },
        ]);

        $department->loadCount('items');

        $totalQuantity = $department->items->sum(function ($di) {
            return $di->quantity;
        });

        $department->total_quantity = $totalQuantity;

        return response()->json($department);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $department->update($validated);

        return response()->json($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $department->delete();
        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }

    public function items(Request $request, Department $department): JsonResponse
    {
        $items = DepartmentItem::where('department_id', $department->id)
            ->with('item.warehouse')
            ->get();

        return response()->json($items);
    }

    public function orders(Request $request, Department $department): JsonResponse
    {
        $user = $request->user();
        $userIds = $department->users()->pluck('users.id');

        $orders = Order::whereIn('user_id', $userIds)
            ->with(['user', 'items.item', 'warehouse', 'receiver'])
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($orders);
    }

    public function requestFromWarehouse(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_type' => 'required|in:main,sub',
            'notes' => 'nullable|string',
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        foreach ($validated['items'] as $itemData) {
            $item = Item::where('id', $itemData['item_id'])
                ->where('warehouse_id', $warehouse->id)
                ->firstOrFail();

            $subUnitQty = $item->sub_unit_quantity ?: 1;

            if ($itemData['unit_type'] === 'main') {
                if ($item->quantity < $itemData['quantity']) {
                    return response()->json([
                        'message' => "الكمية غير كافية للصنف: {$item->name}. المتوفر: {$item->quantity} " . ($item->unit ?: 'وحدة'),
                    ], 422);
                }
            } else {
                $availableSub = $item->quantity * $subUnitQty;
                if ($availableSub < $itemData['quantity']) {
                    return response()->json([
                        'message' => "الكمية غير كافية للصنف: {$item->name}. المتوفر: {$availableSub} " . ($item->sub_unit ?: 'وحدة فرعية'),
                    ], 422);
                }
            }
        }

        $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id' => $request->user()->id,
            'warehouse_id' => $warehouse->id,
            'notes' => ($validated['notes'] ?? '') . " [قسم: {$department->name}]",
            'status' => 'pending',
        ]);

        foreach ($validated['items'] as $itemData) {
            $item = Item::where('id', $itemData['item_id'])
                ->where('warehouse_id', $warehouse->id)
                ->firstOrFail();

            OrderItem::create([
                'order_id' => $order->id,
                'item_id' => $itemData['item_id'],
                'quantity' => $itemData['quantity'],
                'price' => $item->sale_price,
                'unit_type' => $itemData['unit_type'],
                'sub_unit_name' => $itemData['unit_type'] === 'sub' ? ($item->sub_unit ?? null) : null,
            ]);
        }

        return response()->json(
            $order->load(['user', 'items.item', 'warehouse']),
            201
        );
    }
}
