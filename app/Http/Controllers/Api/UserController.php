<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roleRelation')->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'max_discount_percentage' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,inactive',
        ]);

        $role = Role::find($validated['role_id']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $role->display_name,
            'role_id' => $role->id,
            'max_discount_percentage' => $validated['max_discount_percentage'] ?? 10.0,
            'status' => $validated['status'] ?? 'active',
        ]);

        AuditLogService::log('create', 'users', $user->id, null, ['name' => $user->name, 'email' => $user->email], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load('roleRelation'),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|max:255|unique:users,email,{$id}",
            'phone' => 'nullable|string|max:30',
            'role_id' => 'required|exists:roles,id',
            'max_discount_percentage' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,inactive',
            'password' => 'nullable|string|min:6',
        ]);

        $role = Role::find($validated['role_id']);
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? $user->phone;
        $user->role = $role->display_name;
        $user->role_id = $role->id;
        $user->max_discount_percentage = $validated['max_discount_percentage'] ?? $user->max_discount_percentage;
        $user->status = $validated['status'] ?? $user->status;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        AuditLogService::log('update', 'users', $user->id, null, ['name' => $user->name, 'role' => $user->role], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->load('roleRelation'),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($user->hasRole('super_admin', 'Super Admin')) {
            return response()->json(['success' => false, 'message' => 'Cannot delete the Super Administrator account.'], 403);
        }

        $user->delete();

        AuditLogService::log('delete', 'users', $id, null, null);

        return response()->json([
            'success' => true,
            'message' => 'User deactivated and removed.',
        ]);
    }
}
