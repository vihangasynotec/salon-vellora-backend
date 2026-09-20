<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationStatusHistory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Quotation::with(['customer', 'preparer'])->orderBy('id', 'desc');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id !== '') {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'LIKE', "%{$search}%")
                  ->orWhere('customer_name', 'LIKE', "%{$search}%")
                  ->orWhere('customer_phone', 'LIKE', "%{$search}%");
            });
        }

        $quotations = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $quotations,
        ]);
    }

    public function show($id): JsonResponse
    {
        $quotation = Quotation::with(['customer', 'preparer', 'items.service', 'items.package', 'items.staff', 'statusHistory.user'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $quotation,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'customer_email' => 'nullable|email',
            'customer_address' => 'nullable|string',
            'quotation_date' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:quotation_date',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'nullable|exists:services,id',
            'items.*.package_id' => 'nullable|exists:service_packages,id',
            'items.*.staff_id' => 'nullable|exists:staff,id',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.duration_minutes' => 'nullable|integer',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'status' => 'nullable|in:Draft,Sent,Pending,Approved',
        ]);

        DB::beginTransaction();
        try {
            // Generate Unique Quotation Number (QTN-YYYY-XXXXXX)
            $year = date('Y');
            $prefix = Setting::get('quotation_prefix', 'QTN-') . $year . '-';
            $lastQuote = Quotation::where('quotation_number', 'LIKE', "{$prefix}%")
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();

            $seq = 1;
            if ($lastQuote && preg_match('/-(\d+)$/', $lastQuote->quotation_number, $matches)) {
                $seq = ((int) $matches[1]) + 1;
            }
            $quotationNumber = $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);

            // Auto-create or link customer if new
            $customerId = $validated['customer_id'] ?? null;
            if (!$customerId && !empty($validated['customer_phone'])) {
                $existingCust = Customer::where('phone', $validated['customer_phone'])->first();
                if ($existingCust) {
                    $customerId = $existingCust->id;
                } else {
                    $newCust = Customer::create([
                        'name' => $validated['customer_name'],
                        'phone' => $validated['customer_phone'],
                        'email' => $validated['customer_email'] ?? null,
                        'address' => $validated['customer_address'] ?? null,
                    ]);
                    $customerId = $newCust->id;
                }
            }

            // Calculate Subtotal and Grand Total
            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $lineTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount_amount'] ?? 0);
                $subtotal += $lineTotal;
            }

            $discountAmount = (float) ($validated['discount_amount'] ?? 0);
            $taxAmount = (float) ($validated['tax_amount'] ?? 0);
            $grandTotal = ($subtotal - $discountAmount) + $taxAmount;

            $initialStatus = $validated['status'] ?? 'Draft';
            $defaultTerms = Setting::get('quotation_default_terms', "1. 50% advance deposit to confirm.\n2. Balance on event date.");

            $quotation = Quotation::create([
                'quotation_number' => $quotationNumber,
                'customer_id' => $customerId,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'quotation_date' => $validated['quotation_date'],
                'valid_until' => $validated['valid_until'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'status' => $initialStatus,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? $defaultTerms,
                'prepared_by' => $user->id,
            ]);

            foreach ($validated['items'] as $item) {
                $lineTotal = ($item['unit_price'] * $item['quantity']) - ($item['discount_amount'] ?? 0);
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'service_id' => $item['service_id'] ?? null,
                    'package_id' => $item['package_id'] ?? null,
                    'staff_id' => $item['staff_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'duration_minutes' => $item['duration_minutes'] ?? 60,
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'total_price' => $lineTotal,
                ]);
            }

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'status_from' => null,
                'status_to' => $initialStatus,
                'changed_by' => $user->id,
                'notes' => 'Quotation initially created.',
            ]);

            DB::commit();

            AuditLogService::log('create', 'quotations', $quotation->id, null, ['quotation_number' => $quotationNumber, 'total' => $grandTotal], $user->name);

            $quotation->load(['items', 'customer']);

            return response()->json([
                'success' => true,
                'message' => "Quotation {$quotationNumber} created successfully.",
                'data' => $quotation,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to create quotation: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Draft,Sent,Viewed,Pending,Approved,Rejected,Expired,Cancelled',
            'notes' => 'nullable|string',
        ]);

        $quotation = Quotation::findOrFail($id);

        if ($quotation->status === 'Converted') {
            return response()->json(['success' => false, 'message' => 'Cannot modify status of a converted quotation.'], 422);
        }

        $oldStatus = $quotation->status;
        $quotation->status = $validated['status'];
        $quotation->save();

        QuotationStatusHistory::create([
            'quotation_id' => $quotation->id,
            'status_from' => $oldStatus,
            'status_to' => $validated['status'],
            'changed_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? "Status changed from {$oldStatus} to {$validated['status']}",
        ]);

        AuditLogService::log('status_change', 'quotations', $quotation->id, ['status' => $oldStatus], ['status' => $validated['status']], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => "Quotation status updated to {$validated['status']}.",
            'data' => $quotation,
        ]);
    }

    public function convertToAppointment(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'notes' => 'nullable|string',
        ]);

        $quotation = Quotation::with('items')->findOrFail($id);

        if ($quotation->status === 'Converted') {
            return response()->json(['success' => false, 'message' => 'This quotation has already been converted.'], 422);
        }

        // Calculate total duration from quotation items
        $totalDuration = $quotation->items->sum('duration_minutes') ?: 60;
        $startTime = Carbon::parse($validated['start_time']);
        $endTime = (clone $startTime)->addMinutes($totalDuration)->format('H:i:s');

        // Prevent double booking
        $conflict = Appointment::where('staff_id', $validated['staff_id'])
            ->where('appointment_date', $validated['appointment_date'])
            ->whereNotIn('status', ['Cancelled'])
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime->format('H:i:s'), $endTime])
                  ->orWhereBetween('end_time', [$startTime->format('H:i:s'), $endTime]);
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'The selected stylist is already booked during this time window. Please select another time or stylist.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $appointmentNumber = 'APT-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $firstItem = $quotation->items->first();

            $appointment = Appointment::create([
                'appointment_number' => $appointmentNumber,
                'customer_id' => $quotation->customer_id,
                'staff_id' => $validated['staff_id'],
                'service_id' => $firstItem ? $firstItem->service_id : null,
                'package_id' => $firstItem ? $firstItem->package_id : null,
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $startTime->format('H:i:s'),
                'end_time' => $endTime,
                'duration_minutes' => $totalDuration,
                'status' => 'Confirmed',
                'notes' => ($validated['notes'] ? $validated['notes'] . " | " : "") . "Converted from Quotation #{$quotation->quotation_number}",
                'quotation_id' => $quotation->id,
                'created_by' => $request->user()->id,
            ]);

            $quotation->status = 'Converted';
            $quotation->converted_at = now();
            $quotation->converted_type = 'appointment';
            $quotation->converted_id = $appointment->id;
            $quotation->save();

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'status_from' => 'Approved',
                'status_to' => 'Converted',
                'changed_by' => $request->user()->id,
                'notes' => "Converted to Appointment #{$appointmentNumber}",
            ]);

            DB::commit();

            AuditLogService::log('convert_appointment', 'quotations', $quotation->id, null, ['appointment_id' => $appointment->id], $request->user()->name);

            return response()->json([
                'success' => true,
                'message' => "Quotation converted to Appointment #{$appointmentNumber} successfully.",
                'data' => $appointment,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Conversion failed: ' . $e->getMessage()], 500);
        }
    }

    public function convertToSale(Request $request, $id): JsonResponse
    {
        $quotation = Quotation::with('items')->findOrFail($id);

        if ($quotation->status === 'Converted') {
            return response()->json(['success' => false, 'message' => 'This quotation has already been converted.'], 422);
        }

        DB::beginTransaction();
        try {
            $year = date('Y');
            $prefix = Setting::get('invoice_prefix', 'SV-INV-') . $year . '-';
            $lastSale = Sale::where('invoice_number', 'LIKE', "{$prefix}%")->lockForUpdate()->orderBy('id', 'desc')->first();
            $seq = 1;
            if ($lastSale && preg_match('/-(\d+)$/', $lastSale->invoice_number, $matches)) {
                $seq = ((int) $matches[1]) + 1;
            }
            $invoiceNumber = $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);

            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $quotation->customer_id,
                'cashier_id' => $request->user()->id,
                'quotation_id' => $quotation->id,
                'subtotal' => $quotation->subtotal,
                'discount_amount' => $quotation->discount_amount,
                'tax_amount' => $quotation->tax_amount,
                'grand_total' => $quotation->grand_total,
                'paid_amount' => $quotation->grand_total,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'status' => 'completed',
                'notes' => "Converted from Quotation #{$quotation->quotation_number}",
            ]);

            foreach ($quotation->items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_type' => $item->package_id ? 'package' : 'service',
                    'item_id' => $item->package_id ?: ($item->service_id ?: 0),
                    'name' => $item->description,
                    'staff_id' => $item->staff_id,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'discount_amount' => $item->discount_amount,
                    'total_price' => $item->total_price,
                ]);
            }

            $quotation->status = 'Converted';
            $quotation->converted_at = now();
            $quotation->converted_type = 'sale';
            $quotation->converted_id = $sale->id;
            $quotation->save();

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'status_from' => 'Approved',
                'status_to' => 'Converted',
                'changed_by' => $request->user()->id,
                'notes' => "Converted to POS Invoice #{$invoiceNumber}",
            ]);

            DB::commit();

            AuditLogService::log('convert_sale', 'quotations', $quotation->id, null, ['invoice_number' => $invoiceNumber], $request->user()->name);

            return response()->json([
                'success' => true,
                'message' => "Quotation converted to Invoice #{$invoiceNumber} successfully.",
                'data' => $sale,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Sale conversion failed: ' . $e->getMessage()], 500);
        }
    }
}
