<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Clear all operational and transactional data while keeping user accounts,
     * roles, permissions, master catalog and settings intact.
     */
    public function clearDatabase(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('super_admin', 'Super Admin') && !$user->hasPermission('settings.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only administrators with settings permissions can reset database transactions.',
            ], 403);
        }

        $validated = $request->validate([
            'confirm_text' => 'required|string',
            'delete_customers' => 'nullable|boolean',
            'reset_stock' => 'nullable|boolean',
        ]);

        if (strtoupper(trim($validated['confirm_text'])) !== 'CLEAR') {
            return response()->json([
                'success' => false,
                'message' => "Confirmation failed. Please type 'CLEAR' in capital letters to confirm the reset.",
            ], 422);
        }

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

            $tablesToTruncate = [
                'sale_items',
                'payments',
                'sales',
                'appointments',
                'quotation_status_history',
                'quotation_items',
                'quotations',
                'rental_orders',
                'purchase_return_items',
                'purchase_returns',
                'purchase_items',
                'purchases',
                'expenses',
                'salaries',
                'inventory_movements',
                'loyalty_transactions',
                'audit_logs',
                'failed_jobs',
                'sessions',
            ];

            if (!empty($validated['delete_customers'])) {
                $tablesToTruncate[] = 'customers';
            }

            $clearedTables = [];
            foreach ($tablesToTruncate as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                    $clearedTables[] = $table;
                }
            }

            // Reset customer visit/spend stats if customers table is kept
            if (empty($validated['delete_customers']) && Schema::hasTable('customers')) {
                DB::table('customers')->update([
                    'loyalty_points' => 0,
                    'total_spend' => 0.00,
                    'total_visits' => 0,
                ]);
            }

            // Optionally reset product stock quantities to 0
            if (!empty($validated['reset_stock']) && Schema::hasTable('products')) {
                DB::table('products')->update(['stock_quantity' => 0]);
            }

            // Reset rental item inventory availability
            if (Schema::hasTable('rental_items')) {
                DB::statement("UPDATE rental_items SET available_quantity = total_quantity, status = 'available'");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

            AuditLogService::log(
                'clear_database',
                'settings',
                null,
                null,
                [
                    'cleared_by' => $user->name,
                    'cleared_tables' => $clearedTables,
                    'customers_deleted' => !empty($validated['delete_customers']),
                    'stock_reset' => !empty($validated['reset_stock']),
                ],
                $user->name
            );

            return response()->json([
                'success' => true,
                'message' => 'Database successfully cleared! All sales, appointments, quotations, and expenses have been reset. All user accounts, roles, and permissions remain safe.',
            ]);
        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Database clear failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

