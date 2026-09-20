<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::with(['customer', 'staff', 'service', 'package'])->orderBy('appointment_date', 'asc')->orderBy('start_time', 'asc');

        if ($request->has('date') && $request->date !== '') {
            $query->whereDate('appointment_date', $request->date);
        } elseif ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('appointment_date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('staff_id') && $request->staff_id !== '') {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id') && $request->customer_id !== '') {
            $query->where('customer_id', $request->customer_id);
        }

        $appointments = $query->get();

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'nullable|exists:services,id',
            'package_id' => 'nullable|exists:service_packages,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'duration_minutes' => 'nullable|integer|min:15',
            'status' => 'nullable|in:Pending,Confirmed,In Progress,Completed,Cancelled,No Show',
            'notes' => 'nullable|string',
        ]);

        // Calculate duration & end time
        $duration = $validated['duration_minutes'] ?? 60;
        if (empty($validated['duration_minutes'])) {
            if (!empty($validated['service_id'])) {
                $srv = Service::find($validated['service_id']);
                $duration = $srv ? $srv->duration_minutes : 60;
            } elseif (!empty($validated['package_id'])) {
                $pkg = ServicePackage::find($validated['package_id']);
                $duration = $pkg ? $pkg->duration_minutes : 120;
            }
        }

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = (clone $startTime)->addMinutes($duration)->format('H:i:s');
        $startTimeStr = $startTime->format('H:i:s');

        // Check for stylist schedule conflict
        $hasConflict = Appointment::where('staff_id', $validated['staff_id'])
            ->where('appointment_date', $validated['appointment_date'])
            ->whereNotIn('status', ['Cancelled', 'No Show'])
            ->where(function ($q) use ($startTimeStr, $endTime) {
                $q->where(function ($sub) use ($startTimeStr, $endTime) {
                    $sub->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTimeStr);
                });
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'success' => false,
                'message' => "Schedule conflict: The selected stylist already has an active appointment between {$startTimeStr} and {$endTime} on {$validated['appointment_date']}."
            ], 422);
        }

        $aptNumber = 'APT-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $appointment = Appointment::create([
            'appointment_number' => $aptNumber,
            'customer_id' => $validated['customer_id'],
            'staff_id' => $validated['staff_id'],
            'service_id' => $validated['service_id'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
            'appointment_date' => $validated['appointment_date'],
            'start_time' => $startTimeStr,
            'end_time' => $endTime,
            'duration_minutes' => $duration,
            'status' => $validated['status'] ?? 'Confirmed',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        AuditLogService::log('create', 'appointments', $appointment->id, null, ['number' => $aptNumber, 'date' => $validated['appointment_date']], $request->user()->name);

        $appointment->load(['customer', 'staff', 'service', 'package']);

        return response()->json([
            'success' => true,
            'message' => 'Appointment scheduled successfully.',
            'data' => $appointment,
        ], 201);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:Pending,Confirmed,In Progress,Completed,Cancelled,No Show',
            'notes' => 'nullable|string',
        ]);

        $appointment = Appointment::findOrFail($id);
        $oldStatus = $appointment->status;
        $appointment->status = $validated['status'];
        if (!empty($validated['notes'])) {
            $appointment->notes = ($appointment->notes ? $appointment->notes . " | " : "") . $validated['notes'];
        }
        $appointment->save();

        AuditLogService::log('status_change', 'appointments', $appointment->id, ['status' => $oldStatus], ['status' => $validated['status']], $request->user()->name);

        return response()->json([
            'success' => true,
            'message' => "Appointment marked as {$validated['status']}.",
            'data' => $appointment,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->delete();

        AuditLogService::log('delete', 'appointments', $id, null, null);

        return response()->json([
            'success' => true,
            'message' => 'Appointment removed successfully.',
        ]);
    }
}
