<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::when($request->search, function ($q, $v) {
            $q->where('name', 'like', "%$v%")->orWhere('code', 'like', "%$v%");
        })->latest()->paginate($request->per_page ?? 10);

        return response()->json($warehouses);
    }

    public function all(): JsonResponse
    {
        return response()->json(Warehouse::where('is_active', true)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:warehouses,code',
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $warehouse = Warehouse::create($validated);

        return response()->json($warehouse, 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return response()->json($warehouse->load('items'));
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:warehouses,code,' . $warehouse->id,
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $warehouse->update($validated);

        return response()->json($warehouse);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->items()->count() > 0) {
            return response()->json(['message' => 'لا يمكن حذف المخزن لوجود أصناف مرتبطة به.'], 400);
        }

        $warehouse->delete();

        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
