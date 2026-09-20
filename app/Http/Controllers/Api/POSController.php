<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use App\Models\Setting;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class POSController extends Controller
{
    public function getCatalog(): JsonResponse
    {
        $categories = ServiceCategory::with(['services' => function ($q) {
            $q->where('status', 'active');
        }])->where('status', 'active')->orderBy('display_order')->get();

        $packages = ServicePackage::with('services')->where('status', 'active')->get();

        $products = Product::with(['category', 'brand'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $customers = Customer::where('status', 'active')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'packages' => $packages,
                'products' => $products,
                'customers' => $customers,
            ]
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'staff_id' => 'nullable|exists:staff,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:service,product,package',
            'items.*.item_id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.staff_id' => 'nullable|exists:staff,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0',
            'service_charge' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,card,bank_transfer,split',
            'paid_amount' => 'required|numeric|min:0',
            'change_amount' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.payment_method' => 'required|in:cash,card,bank_transfer',
            'payments.*.amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // 1. Validate Max Discount Permitted for User Role
        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $subtotal += ($item['unit_price'] * $item['quantity']) - ($item['discount_amount'] ?? 0);
        }

        $totalDiscount = (float) ($validated['discount_amount'] ?? 0);
        if ($subtotal > 0 && $totalDiscount > 0) {
            $discountPct = ($totalDiscount / $subtotal) * 100;
            $userMaxPct = (float) ($user->max_discount_percentage ?? 10.0);
            if (!$user->hasRole('super_admin', 'Super Admin') && $discountPct > $userMaxPct) {
                return response()->json([
                    'success' => false,
                    'message' => "Discount of " . number_format($discountPct, 1) . "% exceeds your authorized limit of {$userMaxPct}%."
                ], 422);
            }
        }

        // 2. Validate Product Stock Availability
        foreach ($validated['items'] as $item) {
            if ($item['item_type'] === 'product') {
                $product = Product::find($item['item_id']);
                if (!$product) {
                    return response()->json(['success' => false, 'message' => "Product not found."], 422);
                }
                if ($product->stock_quantity < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for '{$product->name}'. Available: {$product->stock_quantity}, Requested: {$item['quantity']}."
                    ], 422);
                }
            }
        }

        // 3. Begin Transaction
        DB::beginTransaction();
        try {
            // Generate Unique Invoice Number (SV-INV-YYYY-XXXXXX)
            $year = date('Y');
            $prefix = Setting::get('invoice_prefix', 'SV-INV-') . $year . '-';
            $lastSale = Sale::where('invoice_number', 'LIKE', "{$prefix}%")
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $sequence = 1;
            if ($lastSale && preg_match('/-(\d+)$/', $lastSale->invoice_number, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }
            $invoiceNumber = $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

            // Compute Tax & Totals
            $taxPercentage = (float) ($validated['tax_percentage'] ?? 0);
            $taxAmount = ($subtotal - $totalDiscount) * ($taxPercentage / 100);
            $serviceCharge = (float) ($validated['service_charge'] ?? 0);
            $grandTotal = ($subtotal - $totalDiscount) + $taxAmount + $serviceCharge;

            $paidAmount = (float) $validated['paid_amount'];
            $changeAmount = max(0, $paidAmount - $grandTotal);
            $paymentStatus = $paidAmount >= $grandTotal ? 'paid' : 'partial';

            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $validated['customer_id'] ?? null,
                'cashier_id' => $user->id,
                'staff_id' => $validated['staff_id'] ?? null,
                'quotation_id' => $validated['quotation_id'] ?? null,
                'subtotal' => $subtotal,
                'discount_type' => $validated['discount_type'] ?? 'fixed',
                'discount_value' => $validated['discount_value'] ?? 0,
                'discount_amount' => $totalDiscount,
                'tax_percentage' => $taxPercentage,
                'tax_amount' => $taxAmount,
                'service_charge' => $serviceCharge,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Save Sale Items & Adjust Product Inventory
            foreach ($validated['items'] as $item) {
                $itemLineTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount_amount'] ?? 0);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_type' => $item['item_type'],
                    'item_id' => $item['item_id'],
                    'name' => $item['name'],
                    'staff_id' => $item['staff_id'] ?? $validated['staff_id'] ?? null,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'total_price' => $itemLineTotal,
                ]);

                // Inventory reduction
                if ($item['item_type'] === 'product') {
                    $prod = Product::lockForUpdate()->find($item['item_id']);
                    $prod->stock_quantity -= $item['quantity'];
                    $prod->save();

                    InventoryMovement::create([
                        'product_id' => $prod->id,
                        'movement_type' => 'pos_sale',
                        'quantity' => -$item['quantity'],
                        'balance_after' => $prod->stock_quantity,
                        'reference_type' => 'Sale',
                        'reference_id' => $sale->id,
                        'notes' => "POS Invoice #{$invoiceNumber}",
                        'created_by' => $user->id,
                    ]);
                }
            }

            // Save Payments
            if ($validated['payment_method'] === 'split' && !empty($validated['payments'])) {
                foreach ($validated['payments'] as $p) {
                    Payment::create([
                        'sale_id' => $sale->id,
                        'payment_method' => $p['payment_method'],
                        'amount' => $p['amount'],
                        'tendered_amount' => $p['amount'],
                        'change_amount' => 0,
                        'created_by' => $user->id,
                    ]);
                }
            } else {
                Payment::create([
                    'sale_id' => $sale->id,
                    'payment_method' => $validated['payment_method'],
                    'amount' => min($paidAmount, $grandTotal),
                    'tendered_amount' => $paidAmount,
                    'change_amount' => $changeAmount,
                    'created_by' => $user->id,
                ]);
            }

            // Loyalty Points & Customer Accumulation
            if (!empty($validated['customer_id'])) {
                $customer = Customer::lockForUpdate()->find($validated['customer_id']);
                if ($customer) {
                    $spendPerPoint = (float) Setting::get('loyalty_spend_per_point', 100);
                    $pointsEarned = $spendPerPoint > 0 ? (int) floor($grandTotal / $spendPerPoint) : 0;

                    $customer->total_spend += $grandTotal;
                    $customer->total_visits += 1;
                    if ($pointsEarned > 0) {
                        $customer->loyalty_points += $pointsEarned;
                        \App\Models\LoyaltyTransaction::create([
                            'customer_id' => $customer->id,
                            'sale_id' => $sale->id,
                            'points' => $pointsEarned,
                            'type' => 'earn',
                            'balance_after' => $customer->loyalty_points,
                            'description' => "Earned from Invoice #{$invoiceNumber}",
                        ]);
                    }
                    $customer->save();
                }
            }

            DB::commit();

            AuditLogService::log('sale', 'pos', $sale->id, null, ['invoice_number' => $invoiceNumber, 'total' => $grandTotal], $user->name);

            $sale->load(['customer', 'cashier', 'staff', 'items', 'payments']);

            return response()->json([
                'success' => true,
                'message' => 'Sale completed successfully.',
                'data' => $sale,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process sale: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function holdBill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hold_reference' => 'required|string|max:100',
            'cart_data' => 'required|array',
        ]);

        $sale = Sale::create([
            'invoice_number' => 'HOLD-' . strtoupper(uniqid()),
            'subtotal' => 0,
            'grand_total' => 0,
            'is_held' => true,
            'hold_reference' => $validated['hold_reference'],
            'notes' => json_encode($validated['cart_data']),
            'cashier_id' => $request->user()->id,
            'status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bill put on hold successfully.',
            'data' => $sale,
        ]);
    }

    public function getHeldBills(): JsonResponse
    {
        $held = Sale::where('is_held', true)->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $held]);
    }

    public function resumeHeldBill($id): JsonResponse
    {
        $sale = Sale::where('id', $id)->where('is_held', true)->firstOrFail();
        $cartData = json_decode($sale->notes, true);
        $sale->delete(); // Remove hold entry on resume

        return response()->json([
            'success' => true,
            'message' => 'Bill resumed.',
            'data' => $cartData,
        ]);
    }

    public function deleteHeldBill($id): JsonResponse
    {
        $sale = Sale::where('id', $id)->where('is_held', true)->firstOrFail();
        $sale->delete();
        return response()->json(['success' => true, 'message' => 'Held bill removed.']);
    }

    public function refund(Request $request, $saleId): JsonResponse
    {
        $request->validate([
            'refund_reason' => 'required|string|max:500',
        ]);

        $sale = Sale::with('items')->findOrFail($saleId);

        if ($sale->status === 'refunded') {
            return response()->json(['success' => false, 'message' => 'Sale is already refunded.'], 422);
        }

        DB::beginTransaction();
        try {
            $sale->status = 'refunded';
            $sale->payment_status = 'refunded';
            $sale->refund_reason = $request->refund_reason;
            $sale->refunded_at = now();
            $sale->refunded_by = $request->user()->id;
            $sale->save();

            // Re-increment stock for products
            foreach ($sale->items as $item) {
                if ($item->item_type === 'product') {
                    $product = Product::lockForUpdate()->find($item->item_id);
                    if ($product) {
                        $product->stock_quantity += $item->quantity;
                        $product->save();

                        InventoryMovement::create([
                            'product_id' => $product->id,
                            'movement_type' => 'adjustment_in',
                            'quantity' => $item->quantity,
                            'balance_after' => $product->stock_quantity,
                            'reference_type' => 'Refund',
                            'reference_id' => $sale->id,
                            'notes' => "Refund of Invoice #{$sale->invoice_number}",
                            'created_by' => $request->user()->id,
                        ]);
                    }
                }
            }

            DB::commit();

            AuditLogService::log('refund', 'pos', $sale->id, null, ['reason' => $request->refund_reason, 'amount' => $sale->grand_total], $request->user()->name);

            return response()->json([
                'success' => true,
                'message' => 'Sale refunded successfully.',
                'data' => $sale,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Refund failed: ' . $e->getMessage()], 500);
        }
    }
}
