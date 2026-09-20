<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with(['roleRelation.permissions'])->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated. Please contact an administrator.',
            ], 403);
        }

        $user->tokens()->delete(); // Enforce single active session
        $token = $user->createToken('salon-vellora-token')->plainTextToken;

        $user->last_login_at = now();
        $user->save();

        // Extract permissions
        $permissions = [];
        if ($user->hasRole('super_admin', 'Super Admin')) {
            $permissions = ['*'];
        } elseif ($user->roleRelation && $user->roleRelation->permissions) {
            $permissions = $user->roleRelation->permissions->pluck('name')->toArray();
        }

        AuditLogService::log('login', 'auth', $user->id, null, ['email' => $user->email], $user->name);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'max_discount_percentage' => (float) $user->max_discount_percentage,
                    'permissions' => $permissions,
                ]
            ]
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roleRelation.permissions');

        $permissions = [];
        if ($user->hasRole('super_admin', 'Super Admin')) {
            $permissions = ['*'];
        } elseif ($user->roleRelation && $user->roleRelation->permissions) {
            $permissions = $user->roleRelation->permissions->pluck('name')->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'max_discount_percentage' => (float) $user->max_discount_percentage,
                'permissions' => $permissions,
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            AuditLogService::log('logout', 'auth', $user->id, null, null, $user->name);
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'current_password' => 'nullable|required_with:new_password',
            'new_password' => 'nullable|min:6',
        ]);

        if (!empty($validated['new_password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password does not match.',
                ], 422);
            }
            $user->password = Hash::make($validated['new_password']);
        }

        $user->name = $validated['name'];
        $user->phone = $validated['phone'] ?? $user->phone;
        $user->save();

        AuditLogService::log('update', 'users', $user->id, null, ['name' => $user->name]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $user
        ]);
    }
}
