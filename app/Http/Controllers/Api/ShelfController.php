<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shelf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShelfController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shelves = Shelf::when($request->search, function ($q, $v) {
            $q->where('name', 'like', "%$v%")->orWhere('code', 'like', "%$v%");
        })->latest()->paginate($request->per_page ?? 10);

        return response()->json($shelves);
    }

    public function all(): JsonResponse
    {
        return response()->json(Shelf::where('is_active', true)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:shelves,code',
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $shelf = Shelf::create($validated);

        return response()->json($shelf, 201);
    }

    public function show(Shelf $shelf): JsonResponse
    {
        return response()->json($shelf->load('items'));
    }

    public function update(Request $request, Shelf $shelf): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:shelves,code,' . $shelf->id,
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $shelf->update($validated);

        return response()->json($shelf);
    }

    public function destroy(Shelf $shelf): JsonResponse
    {
        if ($shelf->items()->count() > 0) {
            return response()->json(['message' => 'لا يمكن حذف الرف لوجود أصناف مرتبطة به.'], 400);
        }

        $shelf->delete();

        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
