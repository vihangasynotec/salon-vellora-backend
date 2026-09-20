<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::all()->groupBy('group');
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
            'settings.*.group' => 'nullable|string',
            'settings.*.type' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $item) {
            Setting::set($item['key'], $item['value'] ?? '', $item['group'] ?? 'general', $item['type'] ?? 'string');
        }

        AuditLogService::log('update', 'settings', null, null, ['updated_count' => count($validated['settings'])], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'System settings updated successfully.',
            'data' => Setting::all()->groupBy('group'),
        ]);
    }
}
