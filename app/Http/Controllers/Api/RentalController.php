<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\RentalItem;
use App\Models\RentalOrder;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RentalController extends Controller
{
    /**
     * List all rental items in catalog
     */
    public function index(Request $request): JsonResponse
    {
        $query = RentalItem::query()->withCount('activeOrders');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $items = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Store a new rental item
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:rental_items,code',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'rental_price_per_24h' => 'required|numeric|min:0',
            'security_deposit' => 'required|numeric|min:0',
            'late_fee_per_day' => 'nullable|numeric|min:0',
            'total_quantity' => 'required|integer|min:1',
            'condition' => 'nullable|string|in:Pristine,Excellent,Good,Needs Cleaning,Minor Wear',
            'image' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['available_quantity'] = $validated['total_quantity'];
        $validated['status'] = 'available';

        $item = RentalItem::create($validated);

        AuditLogService::log('create', 'rentals', $item->id, null, $item->toArray(), $request->user()->name ?? 'System');

        return response()->json([
            'success' => true,
            'message' => 'Rental item added successfully.',
            'data' => $item,
        ], 201);
    }

    /**
     * Show single rental item with active/past orders
     */
    public function show($id): JsonResponse
    {
        $item = RentalItem::with(['orders.customer'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    /**
     * Update rental item
     */
    public function update(Request $request, $id): JsonResponse
    {
        $item = RentalItem::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => "required|string|max:50|unique:rental_items,code,{$id}",
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'rental_price_per_24h' => 'required|numeric|min:0',
            'security_deposit' => 'required|numeric|min:0',
            'late_fee_per_day' => 'nullable|numeric|min:0',
            'total_quantity' => 'required|integer|min:1',
            'available_quantity' => 'nullable|integer|min:0',
            'condition' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'nullable|string|in:available,rented,maintenance,inactive',
            'notes' => 'nullable|string',
        ]);

        $oldData = $item->toArray();
        $item->update($validated);

        AuditLogService::log('update', 'rentals', $item->id, $oldData, $item->toArray(), $request->user()->name ?? 'System');

        return response()->json([
            'success' => true,
            'message' => 'Rental item updated successfully.',
            'data' => $item,
        ]);
    }

    /**
     * Delete rental item
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $item = RentalItem::findOrFail($id);

        if ($item->activeOrders()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete item that is currently rented out to a customer.',
            ], 422);
        }

        $item->delete();

        AuditLogService::log('delete', 'rentals', $id, ['name' => $item->name], null, $request->user()->name ?? 'System');

        return response()->json([
            'success' => true,
            'message' => 'Rental item removed.',
        ]);
    }

    /**
     * Get rental orders / bookings
     */
    public function getOrders(Request $request): JsonResponse
    {
        $query = RentalOrder::with(['item', 'customer', 'creator']);

        if ($request->filled('status')) {
            if ($request->status === 'overdue') {
                $query->where('status', 'active')
                      ->where('due_return_date', '<', Carbon::now());
            } else if ($request->status !== 'all') {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('rental_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('customer_nic', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 50));

        // Mark overdue status dynamically
        $now = Carbon::now();
        foreach ($orders as $order) {
            if ($order->status === 'active' && $now->greaterThan($order->due_return_date)) {
                $order->is_overdue = true;
                $order->hours_overdue = (int) $now->diffInHours($order->due_return_date);
            } else {
                $order->is_overdue = false;
                $order->hours_remaining = max(0, (int) $now->diffInHours($order->due_return_date, false));
            }
        }

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Rent out an item (24h Rental Agreement)
     */
    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_nic' => 'nullable|string|max:50',
            'rental_item_id' => 'required|exists:rental_items,id',
            'quantity' => 'nullable|integer|min:1',
            'rental_start_date' => 'nullable|date',
            'duration_hours' => 'nullable|integer|min:1',
            'payment_method' => 'nullable|in:cash,card,bank_transfer',
            'notes' => 'nullable|string',
        ]);

        $item = RentalItem::lockForUpdate()->findOrFail($validated['rental_item_id']);
        $qty = $validated['quantity'] ?? 1;

        if ($item->available_quantity < $qty) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient stock. Only {$item->available_quantity} available for '{$item->name}'.",
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate Unique Rental Number: SV-RNT-YYYY-XXXXXX
            $year = date('Y');
            $prefix = 'SV-RNT-' . $year . '-';
            $last = RentalOrder::where('rental_number', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $sequence = 1;
            if ($last && preg_match('/-(\d+)$/', $last->rental_number, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }
            $rentalNumber = $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

            $durationHours = (int) ($validated['duration_hours'] ?? 24);
            $startDate = !empty($validated['rental_start_date']) ? Carbon::parse($validated['rental_start_date']) : Carbon::now();
            $dueDate = (clone $startDate)->addHours($durationHours);

            $rentalFee = (float) $item->rental_price_per_24h * $qty;
            $depositAmount = (float) $item->security_deposit * $qty;
            $totalCollected = $rentalFee + $depositAmount;

            $order = RentalOrder::create([
                'rental_number' => $rentalNumber,
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_nic' => $validated['customer_nic'] ?? null,
                'rental_item_id' => $item->id,
                'quantity' => $qty,
                'rental_start_date' => $startDate,
                'duration_hours' => $durationHours,
                'due_return_date' => $dueDate,
                'rental_fee' => $rentalFee,
                'deposit_amount' => $depositAmount,
                'total_collected' => $totalCollected,
                'deposit_refunded' => 0,
                'late_fee_charged' => 0,
                'damage_fee_charged' => 0,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'status' => 'active',
                'created_by' => $request->user()->id ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Deduct available stock
            $item->available_quantity -= $qty;
            if ($item->available_quantity <= 0) {
                $item->status = 'rented';
            }
            $item->save();

            DB::commit();

            AuditLogService::log('create_rental', 'rentals', $order->id, null, [
                'rental_number' => $rentalNumber,
                'item' => $item->name,
                'client' => $order->customer_name,
                'due' => $dueDate->toDateTimeString()
            ], $request->user()->name ?? 'Staff');

            $order->load(['item', 'customer']);

            return response()->json([
                'success' => true,
                'message' => "Rental booking #{$rentalNumber} issued successfully.",
                'data' => $order,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue rental: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process return of rented item & settle deposit
     */
    public function returnOrder(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'return_condition' => 'nullable|string',
            'damage_fee' => 'nullable|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $order = RentalOrder::with('item')->findOrFail($id);

        if ($order->status === 'returned') {
            return response()->json([
                'success' => false,
                'message' => 'This rental order has already been marked as returned.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $now = Carbon::now();
            $order->actual_return_date = $now;
            $order->status = 'returned';

            $damageFee = (float) ($validated['damage_fee'] ?? 0);
            $lateFee = (float) ($validated['late_fee'] ?? 0);

            // Auto-calculate late fee if overdue and not explicitly provided
            if ($lateFee === 0 && $now->greaterThan($order->due_return_date)) {
                $hoursLate = $now->diffInHours($order->due_return_date);
                $daysLate = ceil($hoursLate / 24);
                $lateFeeRate = (float) ($order->item->late_fee_per_day ?? 500);
                $lateFee = $daysLate * $lateFeeRate;
            }

            $order->late_fee_charged = $lateFee;
            $order->damage_fee_charged = $damageFee;

            // Refund deposit minus damages and late fee
            $deductions = $damageFee + $lateFee;
            $depositRefund = max(0, $order->deposit_amount - $deductions);
            $order->deposit_refunded = $depositRefund;
            $order->return_condition_notes = $validated['return_condition'] ?? 'Returned in good condition';

            if (!empty($validated['notes'])) {
                $order->notes = ($order->notes ? $order->notes . ' | ' : '') . 'Return note: ' . $validated['notes'];
            }

            $order->save();

            // Re-increment item inventory
            $item = RentalItem::lockForUpdate()->find($order->rental_item_id);
            if ($item) {
                $item->available_quantity += $order->quantity;
                if ($item->status === 'rented' && $item->available_quantity > 0) {
                    $item->status = 'available';
                }
                $item->save();
            }

            DB::commit();

            AuditLogService::log('return_rental', 'rentals', $order->id, null, [
                'rental_number' => $order->rental_number,
                'refunded' => $depositRefund,
                'damage_deductions' => $damageFee,
                'late_fee' => $lateFee
            ], $request->user()->name ?? 'Staff');

            $order->load(['item', 'customer']);

            return response()->json([
                'success' => true,
                'message' => "Item returned successfully. Refund due to client: LKR " . number_format($depositRefund, 2),
                'data' => $order,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process return: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rental Dashboard Summary
     */
    public function getSummary(): JsonResponse
    {
        $activeCount = RentalOrder::where('status', 'active')->count();
        $overdueCount = RentalOrder::where('status', 'active')
            ->where('due_return_date', '<', Carbon::now())
            ->count();

        $totalItems = RentalItem::sum('total_quantity');
        $availableItems = RentalItem::sum('available_quantity');
        $depositsHeld = RentalOrder::where('status', 'active')->sum('deposit_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'active_rentals' => $activeCount,
                'overdue_rentals' => $overdueCount,
                'total_inventory' => $totalItems,
                'available_inventory' => $availableItems,
                'deposits_held' => $depositsHeld,
            ],
        ]);
    }
}
