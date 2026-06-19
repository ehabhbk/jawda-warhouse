<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequest;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $purchases = Purchase::with(['supplier', 'user', 'items.item'])
            ->when($request->search, function ($q, $v) {
                $q->where('invoice_number', 'like', "%$v%")
                  ->orWhereHas('supplier', function ($sq) use ($v) {
                      $sq->where('name', 'like', "%$v%");
                  });
            })
            ->when($request->supplier_id, function ($q, $v) {
                $q->where('supplier_id', $v);
            })
            ->when($request->status, function ($q, $v) {
                $q->where('status', $v);
            })
            ->when($request->from_date, function ($q, $v) {
                $q->whereDate('purchase_date', '>=', $v);
            })
            ->when($request->to_date, function ($q, $v) {
                $q->whereDate('purchase_date', '<=', $v);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($purchases);
    }

    public function store(PurchaseRequest $request): JsonResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data) {
            $data['user_id'] = $request->user()->id;

            if ($request->hasFile('invoice_file')) {
                $data['invoice_file'] = $request->file('invoice_file')->store('invoices', 'public');
            }

            $items = $data['items'];
            unset($data['items']);

            $purchase = Purchase::create($data);

            foreach ($items as $i => $itemData) {
                $itemId = $itemData['item_id'] ?? null;
                $itemName = $itemData['item_name'] ?? null;
                $warehouseId = $itemData['warehouse_id'] ?? null;
                $unitPrice = $itemData['unit_price'] ?? ($itemData['quantity'] > 0 ? $itemData['total_price'] / $itemData['quantity'] : 0);

                if (!$itemId && $itemName) {
                    $code = 'ITM-' . strtoupper(substr(md5(uniqid()), 0, 8));
                    $item = Item::create([
                        'code' => $code,
                        'name' => $itemName,
                        'quantity' => 0,
                        'purchase_price' => $unitPrice,
                        'sale_price' => $unitPrice,
                        'warehouse_id' => $warehouseId,
                        'category_id' => $itemData['category_id'] ?? null,
                        'unit' => $itemData['unit'] ?? 'piece',
                    ]);
                    $itemId = $item->id;
                } else {
                    $item = Item::findOrFail($itemId);
                    if ($warehouseId) {
                        $item->warehouse_id = $warehouseId;
                    }
                    if (isset($itemData['category_id'])) {
                        $item->category_id = $itemData['category_id'];
                    }
                    if (isset($itemData['unit'])) {
                        $item->unit = $itemData['unit'];
                    }
                }

                $itemImage = null;
                if ($request->hasFile("items.$i.image")) {
                    $itemImage = $request->file("items.$i.image")->store('items', 'public');
                    $item->image = $itemImage;
                }

                $item->increment('quantity', $itemData['quantity']);
                $item->purchase_price = $unitPrice;
                $item->save();

                $piData = [
                    'purchase_id' => $purchase->id,
                    'item_id' => $itemId,
                    'item_name' => $itemName,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $itemData['total_price'],
                    'expiry_date' => $itemData['expiry_date'] ?? null,
                    'warehouse_id' => $warehouseId,
                    'image' => $itemImage,
                ];
                PurchaseItem::create($piData);

                StockMovement::create([
                    'item_id' => $itemId,
                    'user_id' => $request->user()->id,
                    'type' => 'in',
                    'quantity' => $itemData['quantity'],
                    'price' => $unitPrice,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'notes' => 'مشتريات - فاتورة رقم: ' . $purchase->invoice_number,
                ]);
            }

            return response()->json(
                $purchase->load(['supplier', 'user', 'items.item', 'items.warehouse']),
                201
            );
        });
    }

    public function show(Purchase $purchase): JsonResponse
    {
        return response()->json($purchase->load(['supplier', 'user', 'items.item', 'items.warehouse']));
    }

    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        if ($purchase->status === 'completed') {
            return response()->json(['message' => 'لا يمكن تعديل فاتورة مكتملة.'], 400);
        }

        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'invoice_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:10240',
            'notes' => 'nullable|string',
            'status' => 'required|in:pending,completed,cancelled',
        ]);

        if ($request->hasFile('invoice_file')) {
            if ($purchase->invoice_file) {
                Storage::disk('public')->delete($purchase->invoice_file);
            }
            $validated['invoice_file'] = $request->file('invoice_file')->store('invoices', 'public');
        }

        $purchase->update($validated);

        return response()->json($purchase->load(['supplier', 'user', 'items.item']));
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        if ($purchase->status === 'completed') {
            return response()->json(['message' => 'لا يمكن حذف فاتورة مكتملة.'], 400);
        }

        $purchase->delete();

        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
