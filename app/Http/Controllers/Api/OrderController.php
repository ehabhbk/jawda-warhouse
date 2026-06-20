<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['user', 'storekeeper', 'items.item', 'warehouse'])
            ->when($request->search, function ($q, $v) {
                $q->where('order_number', 'like', "%$v%");
            })
            ->when($request->status, function ($q, $v) {
                $q->where('status', $v);
            })
            ->when($request->user_id, function ($q, $v) {
                $q->where('user_id', $v);
            })
            ->when($request->warehouse_id, function ($q, $v) {
                $q->where('warehouse_id', $v);
            })
            ->when(!$request->user()->isAdmin() && !$request->user()->isStorekeeper(), function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->when($request->user()->isStorekeeper(), function ($q) use ($request) {
                $warehouseIds = $request->user()->warehouses()->pluck('warehouses.id');
                $q->whereIn('warehouse_id', $warehouseIds);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($orders);
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data) {
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $request->user()->id,
                'warehouse_id' => $warehouse->id,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($data['items'] as $itemData) {
                $item = Item::where('id', $itemData['item_id'])
                    ->where('warehouse_id', $warehouse->id)
                    ->firstOrFail();

                OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'price' => $item->sale_price,
                ]);
            }

            return response()->json(
                $order->load(['user', 'items.item', 'warehouse']),
                201
            );
        });
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['user', 'storekeeper', 'receiver', 'items.item', 'warehouse']));
    }

    public function approve(Order $order): JsonResponse
    {
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'يمكن الموافقة على الطلبات المعلقة فقط.'], 400);
        }

        $user = request()->user();

        $isAssigned = $user->warehouses()->where('warehouse_id', $order->warehouse_id)->exists();
        if (!$user->isAdmin() && !$isAssigned) {
            return response()->json(['message' => 'ليس لديك صلاحية للموافقة على هذا الطلب.'], 403);
        }

        return DB::transaction(function () use ($order, $user) {
            foreach ($order->items as $orderItem) {
                $item = $orderItem->item;
                if ($item->quantity < $orderItem->quantity) {
                    return response()->json([
                        'message' => 'الكمية غير كافية للصنف: ' . $item->name,
                    ], 400);
                }
            }

            $order->update([
                'status' => 'approved',
                'storekeeper_id' => $user->id,
            ]);

            return response()->json($order->load(['user', 'storekeeper', 'items.item', 'warehouse']));
        });
    }

    public function receive(Request $request, Order $order): JsonResponse
    {
        if ($order->status !== 'approved') {
            return response()->json(['message' => 'يمكن استلام الطلبات المعتمدة فقط.'], 400);
        }

        $user = request()->user();

        if ($order->user_id !== $user->id) {
            return response()->json(['message' => 'يمكن لمقدم الطلب فقط استلامه.'], 403);
        }

        $fulfillItems = $request->input('items'); // [{item_id, quantity}]

        return DB::transaction(function () use ($order, $user, $fulfillItems) {
            $allFulfilled = true;

            foreach ($order->items as $orderItem) {
                $fulfillQty = 0;
                if ($fulfillItems && is_array($fulfillItems)) {
                    $match = collect($fulfillItems)->firstWhere('item_id', $orderItem->item_id);
                    $fulfillQty = $match ? (int)$match['quantity'] : 0;
                } else {
                    $fulfillQty = $orderItem->remaining_quantity;
                }

                if ($fulfillQty <= 0) {
                    if ($orderItem->remaining_quantity > 0) $allFulfilled = false;
                    continue;
                }

                $item = $orderItem->item;
                if ($item->quantity < $fulfillQty) {
                    return response()->json([
                        'message' => 'الكمية غير كافية للصنف: ' . $item->name,
                    ], 400);
                }

                $item->decrement('quantity', $fulfillQty);

                StockMovement::create([
                    'item_id' => $orderItem->item_id,
                    'user_id' => $user->id,
                    'type' => 'out',
                    'quantity' => $fulfillQty,
                    'price' => $orderItem->price,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'notes' => 'صرف طلبية رقم: ' . $order->order_number,
                ]);

                $orderItem->increment('fulfilled_quantity', $fulfillQty);

                if ($orderItem->remaining_quantity > 0) $allFulfilled = false;
            }

            $updateData = [
                'received_at' => now(),
                'received_by' => $user->id,
            ];

            if ($allFulfilled) {
                $updateData['status'] = 'completed';
            }

            $order->update($updateData);

            return response()->json($order->load(['user', 'storekeeper', 'receiver', 'items.item', 'warehouse']));
        });
    }

    public function reject(Request $request, Order $order): JsonResponse
    {
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'يمكن رفض الطلبات المعلقة فقط.'], 400);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $order->update([
            'status' => 'rejected',
            'storekeeper_id' => $request->user()->id,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json($order->load(['user', 'storekeeper', 'items.item', 'warehouse']));
    }

    public function complete(Request $request, Order $order): JsonResponse
    {
        if ($order->status !== 'approved') {
            return response()->json(['message' => 'يمكن إكمال الطلبات المعتمدة فقط.'], 400);
        }

        $fulfillItems = $request->input('items');

        return DB::transaction(function () use ($order, $request, $fulfillItems) {
            $allFulfilled = true;

            foreach ($order->items as $orderItem) {
                $fulfillQty = 0;
                if ($fulfillItems && is_array($fulfillItems)) {
                    $match = collect($fulfillItems)->firstWhere('item_id', $orderItem->item_id);
                    $fulfillQty = $match ? (int)$match['quantity'] : 0;
                } else {
                    $fulfillQty = $orderItem->remaining_quantity;
                }

                if ($fulfillQty <= 0) {
                    if ($orderItem->remaining_quantity > 0) $allFulfilled = false;
                    continue;
                }

                $item = $orderItem->item;
                if ($item->quantity < $fulfillQty) {
                    return response()->json([
                        'message' => 'الكمية غير كافية للصنف: ' . $item->name,
                    ], 400);
                }

                $item->decrement('quantity', $fulfillQty);

                StockMovement::create([
                    'item_id' => $orderItem->item_id,
                    'user_id' => $request->user()->id,
                    'type' => 'out',
                    'quantity' => $fulfillQty,
                    'price' => $orderItem->price,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'notes' => 'صرف طلبية رقم: ' . $order->order_number,
                ]);

                $orderItem->increment('fulfilled_quantity', $fulfillQty);

                if ($orderItem->remaining_quantity > 0) $allFulfilled = false;
            }

            $updateData = [];
            if ($allFulfilled) {
                $updateData['status'] = 'completed';
            }

            $order->update($updateData);

            return response()->json($order->load(['user', 'storekeeper', 'items.item', 'warehouse']));
        });
    }

    public function destroy(Order $order): JsonResponse
    {
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'يمكن حذف الطلبات المعلقة فقط.'], 400);
        }

        $order->delete();

        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
