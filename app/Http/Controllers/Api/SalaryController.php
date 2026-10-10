<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Salary;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalaryController extends Controller
{
    /**
     * List all salary records with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Salary::with(['staff', 'creator'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('month')) {
            $query->where('month', (int) $request->month);
        }

        if ($request->filled('year')) {
            $query->where('year', (int) $request->year);
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', (int) $request->staff_id);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $salaries = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $salaries,
        ]);
    }

    /**
     * Preview salary calculation with system data and live overrides.
     * Formula:
     * - Basic Salary (default LKR 20,000, editable)
     * - Monthly Target (default LKR 500,000, editable)
     * - Monthly Revenue (auto-calculated from sales, editable)
     * - Deductions: Electricity + Rent + Petty Cash (auto-fetched or editable)
     * - Remaining Amount = Monthly Revenue - Total Deductions
     * - If Monthly Revenue >= Monthly Target:
     *     Incentive (15%) = 15% of Remaining Amount
     * - Net Salary = Basic Salary + Incentive + Allowances - Advances
     */
    public function previewCalculate(Request $request): JsonResponse
    {
        $staffId = $request->get('staff_id');
        $month = (int) ($request->get('month') ?: Carbon::now()->month);
        $year = (int) ($request->get('year') ?: Carbon::now()->year);

        $staff = null;
        if ($staffId) {
            $staff = Staff::find($staffId);
        }

        // 1. Basic Salary (editable, default 20,000)
        $basicSalary = $request->has('basic_salary') && $request->basic_salary !== null
            ? (float) $request->basic_salary
            : ($staff && (float) $staff->base_salary > 0 ? (float) $staff->base_salary : 20000.00);

        // 2. Monthly Target (editable, default 500,000)
        $monthlyTarget = $request->has('monthly_target') && $request->monthly_target !== null
            ? (float) $request->monthly_target
            : 500000.00;

        // 3. Monthly Salon Revenue (auto-fetched or editable override)
        if ($request->has('monthly_revenue') && $request->monthly_revenue !== null) {
            $monthlyRevenue = (float) $request->monthly_revenue;
        } else {
            $monthlyRevenue = (float) Sale::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where('payment_status', 'paid')
                ->sum('grand_total');
        }

        // 4. Operational Deductions Breakdown (Auto-fetched from Operational Expenses page for this month)
        $expenseBreakdown = [];
        
        $hasCustomNonZero = false;
        if ($request->has('expense_breakdown') && is_array($request->expense_breakdown) && !$request->boolean('refresh_expenses')) {
            foreach ($request->expense_breakdown as $item) {
                if ((float) ($item['amount'] ?? 0) > 0) {
                    $hasCustomNonZero = true;
                    break;
                }
            }
        }

        if ($hasCustomNonZero) {
            // User provided or edited specific custom expenses
            $rawBreakdown = $request->expense_breakdown;
            foreach ($rawBreakdown as $item) {
                $name = trim($item['name'] ?? '');
                $amount = max(0.00, (float) ($item['amount'] ?? 0));
                if ($name !== '' || $amount > 0) {
                    $expenseBreakdown[] = [
                        'name' => $name ?: 'Operational Expense',
                        'amount' => $amount,
                    ];
                }
            }
        } else {
            // Auto-fetch ALL actual expenses logged in the Operational Expenses page for this month & year!
            $actualExpenses = Expense::with('category')
                ->whereYear('expense_date', $year)
                ->whereMonth('expense_date', $month)
                ->orderBy('expense_date', 'asc')
                ->get();

            if ($actualExpenses->count() > 0) {
                foreach ($actualExpenses as $exp) {
                    $expenseBreakdown[] = [
                        'name' => $exp->title ?: ($exp->category ? $exp->category->name : 'Operational Expense'),
                        'amount' => (float) $exp->amount,
                        'expense_id' => $exp->id,
                        'expense_number' => $exp->expense_number,
                        'category' => $exp->category ? $exp->category->name : 'General',
                        'date' => Carbon::parse($exp->expense_date)->format('d M Y'),
                    ];
                }
            } else {
                // Default placeholders if no expenses recorded yet for this month
                $expenseBreakdown = [
                    ['name' => 'Monthly Rent', 'amount' => 0.00],
                    ['name' => 'Monthly Electricity Bill', 'amount' => 0.00],
                    ['name' => 'Water Bill', 'amount' => 0.00],
                    ['name' => 'Petty Cash & Misc', 'amount' => 0.00],
                ];
            }
        }

        $totalExpensesDeducted = array_sum(array_column($expenseBreakdown, 'amount'));

        // Map known items for backward-compatible fields
        $electricityBill = 0.00;
        $shopRent = 0.00;
        $pettyCash = 0.00;
        $otherDeductions = 0.00;
        foreach ($expenseBreakdown as $eb) {
            $lower = strtolower($eb['name']);
            if (str_contains($lower, 'electr') || str_contains($lower, 'ceb')) {
                $electricityBill += $eb['amount'];
            } elseif (str_contains($lower, 'rent')) {
                $shopRent += $eb['amount'];
            } elseif (str_contains($lower, 'petty') || str_contains($lower, 'tea')) {
                $pettyCash += $eb['amount'];
            } else {
                $otherDeductions += $eb['amount'];
            }
        }

        // 5. Remaining Net Income (Net Profit) after deducting all operational expenses
        $remainingAmount = max(0.00, $monthlyRevenue - $totalExpensesDeducted);

        // 6. 15% Net Profit Incentive
        $requireTarget = $request->boolean('require_target', false);
        $targetAchieved = ($monthlyTarget <= 0 || $monthlyRevenue >= $monthlyTarget);
        $incentivePercentage = (float) ($request->get('incentive_percentage') ?: 15.00);
        $calculationMode = $request->get('calculation_mode', 'standard');
        
        // By default, 15% of Net Profit is ALWAYS added to basic salary
        $incentiveUnlocked = !$requireTarget || $targetAchieved;
        $incentiveAmount = $incentiveUnlocked ? max(0.00, $remainingAmount * ($incentivePercentage / 100)) : 0.00;

        // 7. Adjustments & Net Salary:
        // Standard Mode: Basic Salary (20,000) + 15% of Net Profit + Allowances - Advances
        // Full Profit Mode: Basic Salary (20,000) + Net Profit + 15% Bonus + Allowances - Advances
        $netProfitAdded = ($calculationMode === 'full_profit_plus_bonus') ? $remainingAmount : 0.00;
        $allowances = (float) ($request->get('allowances') ?: 0);
        $advances = (float) ($request->get('advances') ?: 0);
        $netSalary = max(0.00, $basicSalary + $netProfitAdded + $incentiveAmount + $allowances - $advances);

        // Individual Staff performance metrics in that month
        $individualServicesCount = 0;
        $individualSalesRevenue = 0.00;
        if ($staff) {
            $individualServicesCount = SaleItem::where('staff_id', $staff->id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();

            $individualSalesRevenue = (float) SaleItem::where('staff_id', $staff->id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->sum('total_price');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'staff' => $staff,
                'month' => $month,
                'year' => $year,
                'month_name' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
                'basic_salary' => $basicSalary,
                'monthly_target' => $monthlyTarget,
                'monthly_revenue' => $monthlyRevenue,
                'target_achieved' => $targetAchieved,
                'target_progress_percentage' => $monthlyTarget > 0 ? round(($monthlyRevenue / $monthlyTarget) * 100, 1) : 0,
                'expense_breakdown' => $expenseBreakdown,
                'electricity_bill' => $electricityBill,
                'shop_rent' => $shopRent,
                'petty_cash' => $pettyCash,
                'other_deductions' => $otherDeductions,
                'total_expenses_deducted' => $totalExpensesDeducted,
                'remaining_amount' => $remainingAmount,
                'incentive_percentage' => $incentivePercentage,
                'incentive_amount' => $incentiveAmount,
                'incentive_unlocked' => $incentiveUnlocked,
                'calculation_mode' => $calculationMode,
                'require_target' => $requireTarget,
                'net_profit_added' => $netProfitAdded,
                'allowances' => $allowances,
                'advances' => $advances,
                'net_salary' => $netSalary,
                'individual_services_count' => $individualServicesCount,
                'individual_sales_revenue' => $individualSalesRevenue,
            ]
        ]);
    }

    /**
     * Store and generate a new salary slip.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
            'basic_salary' => 'required|numeric|min:0',
            'monthly_target' => 'required|numeric|min:0',
            'monthly_revenue' => 'required|numeric|min:0',
            'expense_breakdown' => 'nullable|array',
            'expense_breakdown.*.name' => 'nullable|string',
            'expense_breakdown.*.amount' => 'nullable|numeric|min:0',
            'electricity_bill' => 'nullable|numeric|min:0',
            'shop_rent' => 'nullable|numeric|min:0',
            'petty_cash' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'incentive_percentage' => 'nullable|numeric|min:0|max:100',
            'allowances' => 'nullable|numeric|min:0',
            'advances' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_status' => 'nullable|in:pending,paid',
            'payment_method' => 'nullable|in:cash,bank_transfer,cheque',
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $staffId = (int) $validated['staff_id'];

        // Calculate values safely
        $basicSalary = (float) $validated['basic_salary'];
        $monthlyTarget = (float) $validated['monthly_target'];
        $monthlyRevenue = (float) $validated['monthly_revenue'];
        // Operational expenses breakdown & deduction calculation
        $electricityBill = (float) ($validated['electricity_bill'] ?? 0);
        $shopRent = (float) ($validated['shop_rent'] ?? 0);
        $pettyCash = (float) ($validated['petty_cash'] ?? 0);
        $otherDeductions = (float) ($validated['other_deductions'] ?? 0);

        $expenseBreakdown = [];
        if ($request->has('expense_breakdown') && is_array($request->expense_breakdown)) {
            $rawBreakdown = $request->expense_breakdown;
            foreach ($rawBreakdown as $item) {
                $name = trim($item['name'] ?? '');
                $amount = max(0.00, (float) ($item['amount'] ?? 0));
                if ($name !== '' || $amount > 0) {
                    $expenseBreakdown[] = [
                        'name' => $name ?: 'Expense Item',
                        'amount' => $amount,
                    ];
                }
            }
            $totalExpensesDeducted = array_sum(array_column($expenseBreakdown, 'amount'));
            foreach ($expenseBreakdown as $eb) {
                $lower = strtolower($eb['name']);
                if (str_contains($lower, 'electr') || str_contains($lower, 'ceb')) {
                    $electricityBill = $eb['amount'];
                } elseif (str_contains($lower, 'rent')) {
                    $shopRent = $eb['amount'];
                } elseif (str_contains($lower, 'petty') || str_contains($lower, 'tea')) {
                    $pettyCash = $eb['amount'];
                }
            }
        } else {
            $totalExpensesDeducted = $electricityBill + $shopRent + $pettyCash + $otherDeductions;
            $expenseBreakdown = [
                ['name' => 'Electricity Bill', 'amount' => $electricityBill],
                ['name' => 'Shop Rent', 'amount' => $shopRent],
                ['name' => 'Petty Cash / Refreshments', 'amount' => $pettyCash],
            ];
            if ($otherDeductions > 0) {
                $expenseBreakdown[] = ['name' => 'Other Deductions', 'amount' => $otherDeductions];
            }
        }

        $remainingAmount = max(0.00, $monthlyRevenue - $totalExpensesDeducted);
        
        $requireTarget = $request->boolean('require_target', false);
        $targetAchieved = ($monthlyTarget <= 0 || $monthlyRevenue >= $monthlyTarget);
        $incentivePercentage = (float) ($validated['incentive_percentage'] ?? 15.00);
        $calculationMode = $request->get('calculation_mode', 'standard');

        $incentiveUnlocked = !$requireTarget || $targetAchieved;
        $incentiveAmount = $incentiveUnlocked ? max(0.00, $remainingAmount * ($incentivePercentage / 100)) : 0.00;

        $netProfitAdded = ($calculationMode === 'full_profit_plus_bonus') ? $remainingAmount : 0.00;
        $allowances = (float) ($validated['allowances'] ?? 0);
        $advances = (float) ($validated['advances'] ?? 0);
        $netSalary = max(0.00, $basicSalary + $netProfitAdded + $incentiveAmount + $allowances - $advances);

        $paymentStatus = $validated['payment_status'] ?? 'pending';
        $paymentMethod = $validated['payment_method'] ?? null;
        $paidAt = ($paymentStatus === 'paid') ? Carbon::now() : null;

        // Check if slip already exists for this staff member and month
        $existing = Salary::where('staff_id', $staffId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existing) {
            // Seamlessly update existing slip with latest operational expenses & net calculations
            $oldData = $existing->toArray();
            $existing->update([
                'basic_salary' => $basicSalary,
                'monthly_target' => $monthlyTarget,
                'monthly_revenue' => $monthlyRevenue,
                'target_achieved' => $targetAchieved,
                'electricity_bill' => $electricityBill,
                'shop_rent' => $shopRent,
                'petty_cash' => $pettyCash,
                'other_deductions' => $otherDeductions,
                'total_expenses_deducted' => $totalExpensesDeducted,
                'expense_breakdown' => $expenseBreakdown,
                'remaining_amount' => $remainingAmount,
                'incentive_percentage' => $incentivePercentage,
                'incentive_amount' => $incentiveAmount,
                'allowances' => $allowances,
                'advances' => $advances,
                'net_salary' => $netSalary,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod ?: $existing->payment_method,
                'paid_at' => $paidAt ?: $existing->paid_at,
                'notes' => $validated['notes'] ?? $existing->notes,
            ]);

            AuditLogService::log('update', 'salaries', $existing->id, $oldData, $existing->toArray());

            return response()->json([
                'success' => true,
                'message' => "Salary slip {$existing->salary_slip_number} updated with all operational expenses & 15% net incentive.",
                'data' => $existing->load('staff'),
            ], 200);
        }

        // Generate unique slip number: SAL-YYYYMM-XXX
        $countThisMonth = Salary::where('year', $year)->where('month', $month)->count() + 1;
        $slipNumber = sprintf('SAL-%04d%02d-%03d', $year, $month, $countThisMonth);

        $salary = Salary::create([
            'salary_slip_number' => $slipNumber,
            'staff_id' => $staffId,
            'month' => $month,
            'year' => $year,
            'basic_salary' => $basicSalary,
            'monthly_target' => $monthlyTarget,
            'monthly_revenue' => $monthlyRevenue,
            'target_achieved' => $targetAchieved,
            'electricity_bill' => $electricityBill,
            'shop_rent' => $shopRent,
            'petty_cash' => $pettyCash,
            'other_deductions' => $otherDeductions,
            'total_expenses_deducted' => $totalExpensesDeducted,
            'expense_breakdown' => $expenseBreakdown,
            'remaining_amount' => $remainingAmount,
            'incentive_percentage' => $incentivePercentage,
            'incentive_amount' => $incentiveAmount,
            'allowances' => $allowances,
            'advances' => $advances,
            'net_salary' => $netSalary,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'paid_at' => $paidAt,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        AuditLogService::log('create', 'salaries', $salary->id, null, $salary->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Salary slip generated successfully.',
            'data' => $salary->load('staff'),
        ], 201);
    }

    /**
     * Show single salary record.
     */
    public function show($id): JsonResponse
    {
        $salary = Salary::with(['staff', 'creator'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $salary,
        ]);
    }

    /**
     * Update salary record (manual adjustments).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $salary = Salary::findOrFail($id);

        $validated = $request->validate([
            'basic_salary' => 'nullable|numeric|min:0',
            'monthly_target' => 'nullable|numeric|min:0',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'expense_breakdown' => 'nullable|array',
            'expense_breakdown.*.name' => 'nullable|string',
            'expense_breakdown.*.amount' => 'nullable|numeric|min:0',
            'electricity_bill' => 'nullable|numeric|min:0',
            'shop_rent' => 'nullable|numeric|min:0',
            'petty_cash' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'incentive_percentage' => 'nullable|numeric|min:0|max:100',
            'allowances' => 'nullable|numeric|min:0',
            'advances' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $basicSalary = isset($validated['basic_salary']) ? (float) $validated['basic_salary'] : (float) $salary->basic_salary;
        $monthlyTarget = isset($validated['monthly_target']) ? (float) $validated['monthly_target'] : (float) $salary->monthly_target;
        $monthlyRevenue = isset($validated['monthly_revenue']) ? (float) $validated['monthly_revenue'] : (float) $salary->monthly_revenue;
        $electricityBill = isset($validated['electricity_bill']) ? (float) $validated['electricity_bill'] : (float) $salary->electricity_bill;
        $shopRent = isset($validated['shop_rent']) ? (float) $validated['shop_rent'] : (float) $salary->shop_rent;
        $pettyCash = isset($validated['petty_cash']) ? (float) $validated['petty_cash'] : (float) $salary->petty_cash;
        $otherDeductions = isset($validated['other_deductions']) ? (float) $validated['other_deductions'] : (float) $salary->other_deductions;

        $expenseBreakdown = $salary->expense_breakdown ?: [];
        if ($request->has('expense_breakdown') && is_array($request->expense_breakdown)) {
            $expenseBreakdown = [];
            foreach ($request->expense_breakdown as $item) {
                $name = trim($item['name'] ?? '');
                $amount = max(0.00, (float) ($item['amount'] ?? 0));
                if ($name !== '' || $amount > 0) {
                    $expenseBreakdown[] = [
                        'name' => $name ?: 'Expense Item',
                        'amount' => $amount,
                    ];
                }
            }
            $totalExpensesDeducted = array_sum(array_column($expenseBreakdown, 'amount'));
        } else {
            $totalExpensesDeducted = $electricityBill + $shopRent + $pettyCash + $otherDeductions;
        }

        $remainingAmount = max(0.00, $monthlyRevenue - $totalExpensesDeducted);

        $requireTarget = $request->boolean('require_target', false);
        $targetAchieved = ($monthlyTarget <= 0.00 || $monthlyRevenue >= $monthlyTarget);
        $incentivePercentage = isset($validated['incentive_percentage']) ? (float) $validated['incentive_percentage'] : (float) $salary->incentive_percentage;
        $calculationMode = $request->get('calculation_mode', 'standard');

        $incentiveUnlocked = !$requireTarget || $targetAchieved;
        $incentiveAmount = $incentiveUnlocked ? max(0.00, $remainingAmount * ($incentivePercentage / 100)) : 0.00;

        $netProfitAdded = ($calculationMode === 'full_profit_plus_bonus') ? $remainingAmount : 0.00;
        $allowances = isset($validated['allowances']) ? (float) $validated['allowances'] : (float) $salary->allowances;
        $advances = isset($validated['advances']) ? (float) $validated['advances'] : (float) $salary->advances;
        $netSalary = max(0.00, $basicSalary + $netProfitAdded + $incentiveAmount + $allowances - $advances);

        $oldData = $salary->toArray();

        $salary->update([
            'basic_salary' => $basicSalary,
            'monthly_target' => $monthlyTarget,
            'monthly_revenue' => $monthlyRevenue,
            'target_achieved' => $targetAchieved,
            'electricity_bill' => $electricityBill,
            'shop_rent' => $shopRent,
            'petty_cash' => $pettyCash,
            'other_deductions' => $otherDeductions,
            'total_expenses_deducted' => $totalExpensesDeducted,
            'expense_breakdown' => $expenseBreakdown,
            'remaining_amount' => $remainingAmount,
            'incentive_percentage' => $incentivePercentage,
            'incentive_amount' => $incentiveAmount,
            'allowances' => $allowances,
            'advances' => $advances,
            'net_salary' => $netSalary,
            'notes' => $validated['notes'] ?? $salary->notes,
        ]);

        AuditLogService::log('update', 'salaries', $salary->id, $oldData, $salary->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Salary slip updated successfully.',
            'data' => $salary->load('staff'),
        ]);
    }

    /**
     * Mark salary as paid.
     */
    public function markAsPaid(Request $request, $id): JsonResponse
    {
        $salary = Salary::with('staff')->findOrFail($id);

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,bank_transfer,cheque',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $oldData = $salary->toArray();

        $salary->update([
            'payment_status' => 'paid',
            'payment_method' => $validated['payment_method'],
            'paid_at' => $validated['paid_at'] ? Carbon::parse($validated['paid_at']) : Carbon::now(),
            'notes' => $validated['notes'] ?? $salary->notes,
        ]);

        AuditLogService::log('pay', 'salaries', $salary->id, $oldData, $salary->toArray());

        return response()->json([
            'success' => true,
            'message' => "Salary of LKR " . number_format($salary->net_salary, 2) . " marked as paid to {$salary->staff->name}.",
            'data' => $salary,
        ]);
    }

    /**
     * Delete pending salary slip.
     */
    public function destroy($id): JsonResponse
    {
        $salary = Salary::findOrFail($id);
        $slipNo = $salary->salary_slip_number;

        $salary->delete();
        AuditLogService::log('delete', 'salaries', $id, null, null);

        return response()->json([
            'success' => true,
            'message' => "Salary slip {$slipNo} deleted successfully.",
        ]);
    }

    /**
     * Payload for printing luxury payslip.
     */
    public function getPayslip($id): JsonResponse
    {
        $salary = Salary::with(['staff', 'creator'])->findOrFail($id);

        $settings = Setting::pluck('value', 'key')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'salary' => $salary,
                'salon' => [
                    'name' => $settings['salon_name'] ?? 'Salon Vellora',
                    'tagline' => $settings['salon_tagline'] ?? 'Ladies Salon & Bridal Dressing',
                    'address' => $settings['salon_address'] ?? 'Galwatawaththa Junction, Pinnaduwa, Galle, Sri Lanka',
                    'phone' => $settings['salon_phone'] ?? '074 084 4739',
                    'email' => $settings['salon_email'] ?? 'salonvellora26@gmail.com',
                    'currency' => $settings['currency'] ?? 'LKR',
                ],
                'month_name' => Carbon::createFromDate($salary->year, $salary->month, 1)->format('F Y'),
            ]
        ]);
    }
}
