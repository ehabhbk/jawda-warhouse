<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemRequest;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Item::with(['category', 'shelf', 'warehouse'])
            ->when($request->search, function ($q, $v) {
                $q->where('name', 'like', "%$v%")
                  ->orWhere('code', 'like', "%$v%")
                  ->orWhere('barcode', 'like', "%$v%");
            })
            ->when($request->category_id, function ($q, $v) {
                $q->where('category_id', $v);
            })
            ->when($request->shelf_id, function ($q, $v) {
                $q->where('shelf_id', $v);
            })
            ->when($request->warehouse_id, function ($q, $v) {
                $q->where('warehouse_id', $v);
            })
            ->when($request->has('is_active'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('is_active'));
            })
            ->when($request->low_stock, function ($q) {
                $q->whereColumn('quantity', '<=', 'min_quantity');
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($items);
    }

    public function all(): JsonResponse
    {
        return response()->json(Item::where('is_active', true)->with(['category', 'shelf', 'warehouse'])->get());
    }

    public function store(ItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('items', 'public');
        }

        $item = Item::create($data);

        return response()->json($item->load(['category', 'shelf']), 201);
    }

    public function show(Item $item): JsonResponse
    {
        return response()->json($item->load(['category', 'shelf', 'stockMovements' => function ($q) {
            $q->latest()->limit(20);
        }]));
    }

    public function update(ItemRequest $request, Item $item): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($item->image) {
                Storage::disk('public')->delete($item->image);
            }
            $data['image'] = $request->file('image')->store('items', 'public');
        }

        $item->update($data);

        return response()->json($item->load(['category', 'shelf']));
    }

    public function destroy(Item $item): JsonResponse
    {
        if ($item->image) {
            Storage::disk('public')->delete($item->image);
        }

        $item->delete();

        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
