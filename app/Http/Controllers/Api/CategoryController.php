<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::when($request->search, function ($q, $v) {
            $q->where('name', 'like', "%$v%");
        })->latest()->paginate($request->per_page ?? 10);

        return response()->json($categories);
    }

    public function all(): JsonResponse
    {
        return response()->json(Category::where('is_active', true)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category->load('items'));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->items()->count() > 0) {
            return response()->json(['message' => 'لا يمكن حذف التصنيف لوجود أصناف مرتبطة به.'], 400);
        }

        $category->delete();

        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
