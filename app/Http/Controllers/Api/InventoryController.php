<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand'])->orderBy('name');

        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('brand_id') && $request->brand_id !== '') {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->has('low_stock') && $request->low_stock == '1') {
            $query->whereColumn('stock_quantity', '<=', 'alert_quantity');
        }

        if ($request->has('search') && $request->search !== '') {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                  ->orWhere('sku', 'LIKE', "%{$s}%")
                  ->orWhere('barcode', 'LIKE', "%{$s}%");
            });
        }

        $products = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:product_categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'cost_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'alert_quantity' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (empty($validated['sku'])) {
            $validated['sku'] = 'PRD-' . strtoupper(Str::random(6));
        }

        DB::beginTransaction();
        try {
            $product = Product::create($validated);

            if ($product->stock_quantity > 0) {
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => 'initial',
                    'quantity' => $product->stock_quantity,
                    'balance_after' => $product->stock_quantity,
                    'notes' => 'Initial opening stock entry',
                    'created_by' => $request->user()->id,
                ]);
            }

            DB::commit();

            AuditLogService::log('create', 'inventory', $product->id, null, ['name' => $product->name, 'stock' => $product->stock_quantity], $request->user()->name);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully.',
                'data' => $product->load(['category', 'brand']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to save product: ' . $e->getMessage()], 500);
        }
    }

    public function updateProduct(Request $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'nullable|exists:product_categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'name' => 'required|string|max:255',
            'sku' => "nullable|string|max:100|unique:products,sku,{$id}",
            'barcode' => "nullable|string|max:100|unique:products,barcode,{$id}",
            'cost_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'alert_quantity' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive',
        ]);

        $old = $product->toArray();
        $product->update($validated);

        AuditLogService::log('update', 'inventory', $product->id, $old, $validated, $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product->load(['category', 'brand']),
        ]);
    }

    public function adjustStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:adjustment_in,adjustment_out',
            'quantity' => 'required|integer|min:1',
            'notes' => 'required|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);

            $qtyChange = $validated['type'] === 'adjustment_in' ? $validated['quantity'] : -$validated['quantity'];

            if ($validated['type'] === 'adjustment_out' && $product->stock_quantity < $validated['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot deduct {$validated['quantity']} units. Current stock is only {$product->stock_quantity}."
                ], 422);
            }

            $product->stock_quantity += $qtyChange;
            $product->save();

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'movement_type' => $validated['type'],
                'quantity' => $qtyChange,
                'balance_after' => $product->stock_quantity,
                'notes' => $validated['notes'],
                'created_by' => $request->user()->id,
            ]);

            DB::commit();

            AuditLogService::log('adjust_stock', 'inventory', $product->id, null, ['change' => $qtyChange, 'new_balance' => $product->stock_quantity, 'notes' => $validated['notes']], $request->user()->name);

            return response()->json([
                'success' => true,
                'message' => "Stock adjusted successfully. New stock: {$product->stock_quantity}.",
                'data' => [
                    'product' => $product,
                    'movement' => $movement,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Adjustment failed: ' . $e->getMessage()], 500);
        }
    }

    public function movements(Request $request): JsonResponse
    {
        $query = InventoryMovement::with(['product', 'user'])->orderBy('created_at', 'desc');

        if ($request->has('product_id') && $request->product_id !== '') {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type') && $request->type !== '') {
            $query->where('movement_type', $request->type);
        }

        $movements = $query->paginate($request->get('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $low = Product::with(['category', 'brand'])
            ->whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $low,
        ]);
    }
}
