<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Service::with('category')->orderBy('category_id')->orderBy('name');

        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search !== '') {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                  ->orWhere('code', 'LIKE', "%{$s}%")
                  ->orWhere('description', 'LIKE', "%{$s}%");
            });
        }

        $services = $query->get();

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:services,code',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:5',
            'discount' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = 'SV-' . strtoupper(Str::random(6));
        }

        $service = Service::create($validated);

        AuditLogService::log('create', 'services', $service->id, null, ['name' => $service->name, 'price' => $service->price], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'Service added successfully.',
            'data' => $service->load('category'),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasRole('super_admin', 'Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can edit services or modify service pricing.'
            ], 403);
        }

        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'code' => "nullable|string|max:50|unique:services,code,{$id}",
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:5',
            'discount' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        $old = $service->toArray();
        $service->update($validated);

        AuditLogService::log('update', 'services', $service->id, $old, $validated, $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully.',
            'data' => $service->load('category'),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasRole('super_admin', 'Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can delete services.'
            ], 403);
        }

        $service = Service::findOrFail($id);
        $serviceName = $service->name;
        $service->delete();

        AuditLogService::log('delete', 'services', $id, ['name' => $serviceName], null, $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => "Service '{$serviceName}' deleted successfully.",
        ]);
    }

    // Categories
    public function getCategories(): JsonResponse
    {
        $categories = ServiceCategory::withCount('services')->orderBy('display_order')->get();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('super_admin', 'Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can add service categories.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . rand(100, 999);
        $category = ServiceCategory::create($validated);

        return response()->json(['success' => true, 'message' => 'Category created.', 'data' => $category], 201);
    }

    public function destroyCategory(Request $request, $id): JsonResponse
    {
        if (!$request->user()->hasRole('super_admin', 'Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Administrators can delete service categories.'
            ], 403);
        }

        $category = ServiceCategory::findOrFail($id);
        $name = $category->name;
        $category->delete();

        AuditLogService::log('delete', 'service_categories', $id, ['name' => $name], null, $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => "Service category '{$name}' deleted successfully.",
        ]);
    }

    // Packages
    public function getPackages(): JsonResponse
    {
        $packages = ServicePackage::with('services')->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $packages]);
    }

    public function storePackage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'package_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'duration_minutes' => 'required|integer|min:15',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'is_featured' => 'nullable|boolean',
        ]);

        $package = ServicePackage::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'package_price' => $validated['package_price'],
            'discount' => $validated['discount'] ?? 0,
            'duration_minutes' => $validated['duration_minutes'],
            'is_featured' => $validated['is_featured'] ?? false,
        ]);

        $package->services()->sync($validated['service_ids']);

        AuditLogService::log('create', 'packages', $package->id, null, ['name' => $package->name, 'price' => $package->package_price]);

        return response()->json([
            'success' => true,
            'message' => 'Service package created successfully.',
            'data' => $package->load('services'),
        ], 201);
    }
}
