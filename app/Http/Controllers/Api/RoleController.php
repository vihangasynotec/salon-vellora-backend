<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->withCount('users')->get();
        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function getPermissions(): JsonResponse
    {
        $permissions = Permission::all()->groupBy('module');
        return response()->json(['success' => true, 'data' => $permissions]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $roleName = Str::slug($validated['display_name'], '_');

        $role = Role::create([
            'name' => $roleName,
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'status' => 'active',
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        AuditLogService::log('create', 'roles', $role->id, null, ['name' => $role->name]);

        return response()->json([
            'success' => true,
            'message' => 'Custom role created successfully.',
            'data' => $role->load('permissions'),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->display_name = $validated['display_name'];
        $role->description = $validated['description'] ?? $role->description;
        $role->save();

        $role->permissions()->sync($validated['permission_ids']);

        AuditLogService::log('update', 'roles', $role->id, null, ['name' => $role->name]);

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated.',
            'data' => $role->load('permissions'),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $role = Role::findOrFail($id);
        if ($role->is_system) {
            return response()->json(['success' => false, 'message' => 'System default roles cannot be deleted.'], 403);
        }

        $role->permissions()->detach();
        $role->delete();

        AuditLogService::log('delete', 'roles', $id, null, null);

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }
}
