<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $roles = Role::with('permissions')
            ->when($request->search, function ($q, $v) {
                $q->where('name', 'like', "%$v%")->orWhere('label', 'like', "%$v%");
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($roles);
    }

    public function all(): JsonResponse
    {
        return response()->json(Role::with('permissions')->where('is_active', true)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::create($validated);

        if (!empty($validated['permission_ids'])) {
            $role->permissions()->attach($validated['permission_ids']);
        }

        return response()->json($role->load('permissions'), 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name,' . $role->id,
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->update($validated);

        if (isset($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        return response()->json($role->load('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'لا يمكن حذف الدور لوجود مستخدمين مرتبطين به.'], 400);
        }

        $role->delete();

        return response()->json(['message' => 'تم الحذف بنجاح.']);
    }
}
