<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['user', 'storekeeper', 'items.item'])
            ->when($request->search, function ($q, $v) {
                $q->where('order_number', 'like', "%$v%");
            })
            ->when($request->status, function ($q, $v) {
                $q->where('status', $v);
            })
            ->when($request->user_id, function ($q, $v) {
                $q->where('user_id', $v);
            })
            ->when(!$request->user()->isAdmin() && !$request->user()->isStorekeeper(), function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($orders);
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data) {
            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ]);

            foreach ($data['items'] as $itemData) {
                $item = Item::findOrFail($itemData['item_id']);

                OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'price' => $item->sale_price,
                ]);
            }

            return response()->json(
                $order->load(['user', 'items.item']),
                201
            );
        });
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['user', 'storekeeper', 'items.item']));
    }

    public function approve(Order $order): JsonResponse
    {
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'يمكن الموافقة على الطلبات المعلقة فقط.'], 400);
        }

        return DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'approved',
                'storekeeper_id' => request()->user()->id,
            ]);

            foreach ($order->items as $orderItem) {
                $item = $orderItem->item;
                if ($item->quantity < $orderItem->quantity) {
                    throw new \Exception('الكمية غير كافية للصنف: ' . $item->name);
                }
            }

            return response()->json($order->load(['user', 'storekeeper', 'items.item']));
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

        return response()->json($order->load(['user', 'storekeeper', 'items.item']));
    }

    public function complete(Order $order): JsonResponse
    {
        if ($order->status !== 'approved') {
            return response()->json(['message' => 'يمكن إكمال الطلبات المعتمدة فقط.'], 400);
        }

        return DB::transaction(function () use ($order) {
            foreach ($order->items as $orderItem) {
                $item = $orderItem->item;
                if ($item->quantity < $orderItem->quantity) {
                    return response()->json([
                        'message' => 'الكمية غير كافية للصنف: ' . $item->name,
                    ], 400);
                }

                $item->decrement('quantity', $orderItem->quantity);

                StockMovement::create([
                    'item_id' => $orderItem->item_id,
                    'user_id' => request()->user()->id,
                    'type' => 'out',
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'notes' => 'صرف طلبية رقم: ' . $order->order_number,
                ]);
            }

            $order->update(['status' => 'completed']);

            return response()->json($order->load(['user', 'storekeeper', 'items.item']));
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
