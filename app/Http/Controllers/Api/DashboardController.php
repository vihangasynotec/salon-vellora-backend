<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // 1. KPI Cards
        $todaySalesCount = Sale::whereDate('created_at', $today)->where('status', 'completed')->count();
        $todayRevenue = Sale::whereDate('created_at', $today)->where('status', 'completed')->sum('grand_total');
        $todayAppointmentsCount = Appointment::whereDate('appointment_date', $today)->count();
        $totalCustomersCount = Customer::count();
        
        $lowStockCount = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')->count();
        $pendingQuotationsCount = Quotation::whereIn('status', ['Pending', 'Draft', 'Sent'])->count();
        $monthlyRevenue = Sale::where('created_at', '>=', $startOfMonth)->where('status', 'completed')->sum('grand_total');
        $pendingPayments = Sale::where('payment_status', 'partial')->sum(DB::raw('grand_total - paid_amount'));

        // 2. Chart: Daily Sales (Last 7 Days)
        $dailySales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayLabel = $date->format('D, d M');
            $total = Sale::whereDate('created_at', $date)->where('status', 'completed')->sum('grand_total');
            $dailySales[] = [
                'date' => $dayLabel,
                'total' => (float) $total,
            ];
        }

        // 3. Chart: Monthly Revenue (Last 6 Months)
        $monthlyRevenueTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthLabel = $month->format('M Y');
            $total = Sale::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->where('status', 'completed')
                ->sum('grand_total');
            $monthlyRevenueTrend[] = [
                'month' => $monthLabel,
                'total' => (float) $total,
            ];
        }

        // 4. Chart: Service vs Product breakdown
        $serviceSalesTotal = SaleItem::where('item_type', 'service')->sum('total_price');
        $productSalesTotal = SaleItem::where('item_type', 'product')->sum('total_price');
        $packageSalesTotal = SaleItem::where('item_type', 'package')->sum('total_price');

        // 5. Chart: Payment Methods
        $paymentMethods = Payment::select('payment_method', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get();

        // 6. Upcoming Appointments
        $upcomingAppointments = Appointment::with(['customer', 'staff', 'service'])
            ->where('appointment_date', '>=', $today)
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->orderBy('appointment_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->take(5)
            ->get();

        // 7. Recent Sales
        $recentSales = Sale::with(['customer', 'cashier'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // 8. Recent Quotations
        $recentQuotations = Quotation::with('customer')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // 9. Low-stock Products
        $lowStockProducts = Product::whereColumn('stock_quantity', '<=', 'alert_quantity')
            ->take(5)
            ->get();

        // 10. Recent Customers
        $recentCustomers = Customer::orderBy('created_at', 'desc')->take(5)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'today_sales' => $todaySalesCount,
                    'today_revenue' => (float) $todayRevenue,
                    'today_appointments' => $todayAppointmentsCount,
                    'total_customers' => $totalCustomersCount,
                    'low_stock_count' => $lowStockCount,
                    'pending_quotations' => $pendingQuotationsCount,
                    'monthly_revenue' => (float) $monthlyRevenue,
                    'pending_payments' => (float) $pendingPayments,
                ],
                'charts' => [
                    'daily_sales' => $dailySales,
                    'monthly_revenue' => $monthlyRevenueTrend,
                    'revenue_breakdown' => [
                        'services' => (float) $serviceSalesTotal,
                        'products' => (float) $productSalesTotal,
                        'packages' => (float) $packageSalesTotal,
                    ],
                    'payment_methods' => $paymentMethods,
                ],
                'recent' => [
                    'appointments' => $upcomingAppointments,
                    'sales' => $recentSales,
                    'quotations' => $recentQuotations,
                    'low_stock' => $lowStockProducts,
                    'customers' => $recentCustomers,
                ]
            ]
        ]);
    }
}
