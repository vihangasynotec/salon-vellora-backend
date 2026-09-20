<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleItem;
use App\Models\Staff;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Staff::with('user')->orderBy('name', 'asc');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $staff = $query->get();

        return response()->json([
            'success' => true,
            'data' => $staff,
        ]);
    }

    public function show($id): JsonResponse
    {
        $staff = Staff::with(['appointments' => fn($q) => $q->with('customer')->orderBy('appointment_date', 'desc')->take(10)])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $staff,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'designation' => 'required|string|max:100',
            'specialization' => 'nullable|string|max:255',
            'base_salary' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,inactive',
        ]);

        $staff = Staff::create($validated);

        AuditLogService::log('create', 'staff', $staff->id, null, ['name' => $staff->name], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'Staff profile created successfully.',
            'data' => $staff,
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $staff = Staff::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'designation' => 'required|string|max:100',
            'specialization' => 'nullable|string|max:255',
            'base_salary' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,inactive',
        ]);

        $old = $staff->toArray();
        $staff->update($validated);

        AuditLogService::log('update', 'staff', $staff->id, $old, $validated, $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'Staff profile updated.',
            'data' => $staff,
        ]);
    }

    public function performance($id): JsonResponse
    {
        $staff = Staff::findOrFail($id);

        $totalServicesCount = SaleItem::where('staff_id', $id)->count();
        $totalRevenue = SaleItem::where('staff_id', $id)->sum('total_price');
        $commissionEarned = $totalRevenue * ($staff->commission_rate / 100);

        $recentServices = SaleItem::where('staff_id', $id)
            ->with('sale')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'staff' => $staff,
                'total_services' => $totalServicesCount,
                'total_revenue' => (float) $totalRevenue,
                'commission_earned' => (float) $commissionEarned,
                'recent_services' => $recentServices,
            ]
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $staff = Staff::findOrFail($id);
        $staff->delete();

        AuditLogService::log('delete', 'staff', $id, null, null);

        return response()->json([
            'success' => true,
            'message' => 'Staff member archived.',
        ]);
    }
}
