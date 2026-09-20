<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::orderBy('name', 'asc');

        if ($request->has('search') && $request->search !== '') {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                  ->orWhere('phone', 'LIKE', "%{$s}%")
                  ->orWhere('email', 'LIKE', "%{$s}%");
            });
        }

        $customers = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    public function show($id): JsonResponse
    {
        $customer = Customer::with([
            'sales' => fn($q) => $q->orderBy('created_at', 'desc')->take(10),
            'appointments' => fn($q) => $q->with(['staff', 'service'])->orderBy('appointment_date', 'desc')->take(10),
            'quotations' => fn($q) => $q->orderBy('created_at', 'desc')->take(10),
            'loyaltyTransactions' => fn($q) => $q->take(15),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30|unique:customers,phone',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'birthday' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::create($validated);

        AuditLogService::log('create', 'customers', $customer->id, null, ['name' => $customer->name, 'phone' => $customer->phone], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'Customer registered successfully.',
            'data' => $customer,
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => "required|string|max:30|unique:customers,phone,{$id}",
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'birthday' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $old = $customer->toArray();
        $customer->update($validated);

        AuditLogService::log('update', 'customers', $customer->id, $old, $validated, $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'Customer details updated.',
            'data' => $customer,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        AuditLogService::log('delete', 'customers', $id, null, null);

        return response()->json([
            'success' => true,
            'message' => 'Customer archived successfully.',
        ]);
    }
}
