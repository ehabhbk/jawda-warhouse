<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PosSale;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function todaySummary(): JsonResponse
    {
        $today = now()->startOfDay();
        $sales = PosSale::where('status', 'completed')
            ->where('created_at', '>=', $today)
            ->get();

        return response()->json([
            'total_sales' => $sales->count(),
            'total_amount' => $sales->sum('grand_total'),
            'total_cash' => $sales->where('payment_method', 'cash')->sum('grand_total'),
            'total_card' => $sales->where('payment_method', 'card')->sum('grand_total'),
            'total_items' => $sales->sum(function ($s) {
                return $s->items->sum('quantity');
            }),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $sales = PosSale::with(['user', 'items.item'])
            ->when($request->search, function ($q, $v) {
                $q->where('sale_number', 'like', "%$v%");
            })
            ->when($request->from_date, function ($q, $v) {
                $q->whereDate('created_at', '>=', $v);
            })
            ->when($request->to_date, function ($q, $v) {
                $q->whereDate('created_at', '<=', $v);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($sales);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'change_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'notes' => 'nullable|string',
        ]);

        foreach ($validated['items'] as $itemData) {
            $item = Item::find($itemData['item_id']);
            if (!$item || $item->quantity < $itemData['quantity']) {
                $name = $item->name ?? 'غير معروف';
                $available = $item->quantity ?? 0;
                return response()->json([
                    'message' => "الكمية غير متوفرة للصنف: {$name} (المتوفر: {$available})",
                ], 400);
            }
        }

        $sale = DB::transaction(function () use ($request, $validated) {
            $saleNumber = 'POS-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

            $sale = PosSale::create([
                'sale_number' => $saleNumber,
                'user_id' => $request->user()->id,
                'subtotal' => $validated['subtotal'],
                'tax' => $validated['tax'] ?? 0,
                'discount' => $validated['discount'] ?? 0,
                'grand_total' => $validated['grand_total'],
                'paid_amount' => $validated['paid_amount'],
                'change_amount' => $validated['change_amount'] ?? 0,
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
            ]);

            foreach ($validated['items'] as $itemData) {
                $item = Item::findOrFail($itemData['item_id']);
                $item->decrement('quantity', $itemData['quantity']);

                $sale->items()->create([
                    'item_id' => $itemData['item_id'],
                    'item_name' => $item->name,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['total_price'],
                ]);

                StockMovement::create([
                    'item_id' => $itemData['item_id'],
                    'user_id' => $request->user()->id,
                    'type' => 'out',
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['unit_price'],
                    'reference_type' => 'pos_sale',
                    'reference_id' => $sale->id,
                    'notes' => 'بيع نقطة بيع - رقم: ' . $saleNumber,
                ]);
            }

            return $sale;
        });

        return response()->json(
            $sale->load(['user', 'items.item']),
            201
        );
    }

    public function show(PosSale $posSale): JsonResponse
    {
        return response()->json($posSale->load(['user', 'items.item']));
    }

    public function cancel(PosSale $posSale): JsonResponse
    {
        if ($posSale->status === 'cancelled') {
            return response()->json(['message' => 'عملية البيع ملغاة بالفعل.'], 400);
        }

        DB::transaction(function () use ($posSale) {
            $posSale->update(['status' => 'cancelled']);

            foreach ($posSale->items as $saleItem) {
                $saleItem->item->increment('quantity', $saleItem->quantity);
            }
        });

        return response()->json(['message' => 'تم إلغاء عملية البيع واسترجاع الكميات.']);
    }

    public function destroy(PosSale $posSale): JsonResponse
    {
        if ($posSale->status === 'completed') {
            return response()->json(['message' => 'لا يمكن حذف عملية بيع مكتملة. قم بإلغائها أولاً.'], 400);
        }

        $posSale->delete();
        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
