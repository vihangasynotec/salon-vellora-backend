<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier', 'creator'])->orderBy('purchase_date', 'desc');

        if ($request->has('supplier_id') && $request->supplier_id !== '') {
            $query->where('supplier_id', $request->supplier_id);
        }

        $purchases = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }

    public function show($id): JsonResponse
    {
        $purchase = Purchase::with(['supplier', 'items.product', 'creator'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $purchase,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_reference' => 'nullable|string|max:100',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $subtotal += $item['quantity'] * $item['unit_cost'];
            }

            $discount = (float) ($validated['discount'] ?? 0);
            $tax = (float) ($validated['tax'] ?? 0);
            $totalAmount = ($subtotal - $discount) + $tax;
            $paidAmount = (float) ($validated['paid_amount'] ?? $totalAmount);
            $paymentStatus = $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

            $purchaseNumber = 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'purchase_number' => $purchaseNumber,
                'invoice_reference' => $validated['invoice_reference'] ?? null,
                'purchase_date' => $validated['purchase_date'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'payment_status' => $paymentStatus,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_cost'];
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $lineTotal,
                ]);

                // Increase product stock & log movement
                $prod = Product::lockForUpdate()->find($item['product_id']);
                $prod->stock_quantity += $item['quantity'];
                $prod->cost_price = $item['unit_cost']; // Update latest cost
                $prod->save();

                InventoryMovement::create([
                    'product_id' => $prod->id,
                    'movement_type' => 'purchase',
                    'quantity' => $item['quantity'],
                    'balance_after' => $prod->stock_quantity,
                    'reference_type' => 'Purchase',
                    'reference_id' => $purchase->id,
                    'notes' => "Purchased via #{$purchaseNumber}",
                    'created_by' => $request->user()->id,
                ]);
            }

            // Update Supplier Balance if partially paid
            if ($paidAmount < $totalAmount) {
                $unpaid = $totalAmount - $paidAmount;
                $supplier = Supplier::find($validated['supplier_id']);
                $supplier->current_balance += $unpaid;
                $supplier->save();
            }

            DB::commit();

            AuditLogService::log('create', 'purchases', $purchase->id, null, ['number' => $purchaseNumber, 'total' => $totalAmount], $request->user()->name);

            return response()->json([
                'success' => true,
                'message' => "Purchase {$purchaseNumber} recorded. Stock updated.",
                'data' => $purchase->load('items.product'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Purchase recording failed: ' . $e->getMessage()], 500);
        }
    }

    // Suppliers
    public function getSuppliers(): JsonResponse
    {
        $suppliers = Supplier::orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $suppliers]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'opening_balance' => 'nullable|numeric',
        ]);

        $supplier = Supplier::create($validated);
        return response()->json(['success' => true, 'message' => 'Supplier added.', 'data' => $supplier], 201);
    }
}
