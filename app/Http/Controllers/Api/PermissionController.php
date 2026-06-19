<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::when($request->group, function ($q, $v) {
            $q->where('group', $v);
        })
        ->when($request->search, function ($q, $v) {
            $q->where('label', 'like', "%$v%")->orWhere('name', 'like', "%$v%");
        })
        ->latest()
        ->paginate($request->per_page ?? 50);

        return response()->json($permissions);
    }

    public function all(): JsonResponse
    {
        return response()->json(Permission::all()->groupBy('group'));
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json($permission->load('roles'));
    }
}
