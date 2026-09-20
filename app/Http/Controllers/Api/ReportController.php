<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function getSummary(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        // 1. Sales Totals
        $salesQuery = Sale::whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->where('status', 'completed');

        $totalSalesCount = (clone $salesQuery)->count();
        $totalRevenue = (clone $salesQuery)->sum('grand_total');
        $totalDiscountGiven = (clone $salesQuery)->sum('discount_amount');
        $totalTaxCollected = (clone $salesQuery)->sum('tax_amount');

        // 2. Expenses Totals
        $totalExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount');
        $netProfit = $totalRevenue - $totalExpenses;

        // 3. Top Services
        $topServices = SaleItem::select('name', 'item_type', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_sales'))
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->where('item_type', 'service')
            ->groupBy('name', 'item_type')
            ->orderBy('total_sales', 'desc')
            ->take(10)
            ->get();

        // 4. Top Products
        $topProducts = SaleItem::select('name', 'item_type', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_price) as total_sales'))
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->where('item_type', 'product')
            ->groupBy('name', 'item_type')
            ->orderBy('total_sales', 'desc')
            ->take(10)
            ->get();

        // 5. Staff Performance
        $staffStats = Staff::withCount(['appointments' => function ($q) use ($startDate, $endDate) {
            $q->whereBetween('appointment_date', [$startDate, $endDate]);
        }])->get()->map(function ($s) use ($startDate, $endDate) {
            $revenue = SaleItem::where('staff_id', $s->id)
                ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                ->sum('total_price');
            return [
                'id' => $s->id,
                'name' => $s->name,
                'designation' => $s->designation,
                'appointments_count' => $s->appointments_count,
                'revenue_generated' => (float) $revenue,
                'commission_earned' => (float) ($revenue * ($s->commission_rate / 100)),
            ];
        });

        // 6. Quotations Conversion Analytics
        $totalQuotes = Quotation::whereBetween('quotation_date', [$startDate, $endDate])->count();
        $approvedQuotes = Quotation::whereBetween('quotation_date', [$startDate, $endDate])->where('status', 'Approved')->count();
        $convertedQuotes = Quotation::whereBetween('quotation_date', [$startDate, $endDate])->where('status', 'Converted')->count();
        $quotedValue = Quotation::whereBetween('quotation_date', [$startDate, $endDate])->sum('grand_total');
        $convertedValue = Quotation::whereBetween('quotation_date', [$startDate, $endDate])->where('status', 'Converted')->sum('grand_total');
        $conversionRate = $totalQuotes > 0 ? round(($convertedQuotes / $totalQuotes) * 100, 1) : 0;

        // 7. Payment Methods Breakdown
        $paymentMethods = Payment::select('payment_method', DB::raw('SUM(amount) as total'))
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->groupBy('payment_method')
            ->get();

        // 8. Appointments Breakdown
        $appointmentsStatus = Appointment::select('status', DB::raw('count(*) as count'))
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'financials' => [
                    'total_sales' => $totalSalesCount,
                    'total_revenue' => (float) $totalRevenue,
                    'total_expenses' => (float) $totalExpenses,
                    'net_profit' => (float) $netProfit,
                    'discount_given' => (float) $totalDiscountGiven,
                    'tax_collected' => (float) $totalTaxCollected,
                ],
                'top_services' => $topServices,
                'top_products' => $topProducts,
                'staff_performance' => $staffStats,
                'quotations' => [
                    'total' => $totalQuotes,
                    'approved' => $approvedQuotes,
                    'converted' => $convertedQuotes,
                    'quoted_value' => (float) $quotedValue,
                    'converted_value' => (float) $convertedValue,
                    'conversion_rate' => $conversionRate,
                ],
                'payment_methods' => $paymentMethods,
                'appointment_statuses' => $appointmentsStatus,
            ]
        ]);
    }
}
