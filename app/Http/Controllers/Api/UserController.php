<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('warehouses')
            ->when($request->search, function ($q, $v) {
                $q->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%");
            })
            ->when($request->role, function ($q, $v) {
                $q->where('role', $v);
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return response()->json($users);
    }

    public function all(): JsonResponse
    {
        return response()->json(User::where('is_active', true)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,storekeeper,user',
            'role_id' => 'nullable|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'exists:warehouses,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        if (empty($validated['role_id'])) {
            $role = \App\Models\Role::where('name', $validated['role'])->first();
            $validated['role_id'] = $role?->id;
        }

        $user = User::create($validated);

        if (!empty($validated['warehouse_ids'])) {
            $user->warehouses()->attach($validated['warehouse_ids']);
        }

        return response()->json($user->load('warehouses'), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('warehouses'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:admin,storekeeper,user',
            'role_id' => 'nullable|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'exists:warehouses,id',
        ]);

        if ($validated['password'] ?? false) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (empty($validated['role_id'])) {
            $role = \App\Models\Role::where('name', $validated['role'])->first();
            $validated['role_id'] = $role?->id;
        }

        $user->update($validated);

        if (isset($validated['warehouse_ids'])) {
            $user->warehouses()->sync($validated['warehouse_ids']);
        }

        return response()->json($user->load('warehouses'));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'لا يمكن حذف نفسك.'], 400);
        }

        $user->update(['is_active' => false]);

        return response()->json(['message' => 'تم تعطيل المستخدم بنجاح.']);
    }
}
