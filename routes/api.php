<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\POSController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
Route::get('/catalog', [CatalogController::class, 'getCatalog']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum Authentication Required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth & Profile
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/profile', [AuthController::class, 'updateProfile']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view');

    // POS & Checkout
    Route::prefix('pos')->group(function () {
        Route::get('/catalog', [POSController::class, 'getCatalog'])->middleware('permission:pos.view');
        Route::post('/checkout', [POSController::class, 'checkout'])->middleware('permission:pos.create');
        Route::post('/hold', [POSController::class, 'holdBill'])->middleware('permission:pos.hold');
        Route::get('/held', [POSController::class, 'getHeldBills'])->middleware('permission:pos.hold');
        Route::post('/resume/{id}', [POSController::class, 'resumeHeldBill'])->middleware('permission:pos.resume');
        Route::delete('/held/{id}', [POSController::class, 'deleteHeldBill'])->middleware('permission:pos.hold');
        Route::post('/refund/{id}', [POSController::class, 'refund'])->middleware('permission:pos.refund');
    });

    // Sales & Invoices
    Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales.view');
    Route::get('/sales/{id}', [SaleController::class, 'show'])->middleware('permission:sales.view');

    // Quotations
    Route::prefix('quotations')->group(function () {
        Route::get('/', [QuotationController::class, 'index'])->middleware('permission:quotations.view');
        Route::post('/', [QuotationController::class, 'store'])->middleware('permission:quotations.create');
        Route::get('/{id}', [QuotationController::class, 'show'])->middleware('permission:quotations.view');
        Route::put('/{id}/status', [QuotationController::class, 'updateStatus'])->middleware('permission:quotations.edit');
        Route::post('/{id}/convert-to-appointment', [QuotationController::class, 'convertToAppointment'])->middleware('permission:quotations.convert');
        Route::post('/{id}/convert-to-sale', [QuotationController::class, 'convertToSale'])->middleware('permission:quotations.convert');
    });

    // Appointments
    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->middleware('permission:appointments.view');
        Route::post('/', [AppointmentController::class, 'store'])->middleware('permission:appointments.create');
        Route::put('/{id}/status', [AppointmentController::class, 'updateStatus'])->middleware('permission:appointments.edit');
        Route::delete('/{id}', [AppointmentController::class, 'destroy'])->middleware('permission:appointments.delete');
    });

    // Customers
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->middleware('permission:customers.view');
        Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customers.create');
        Route::get('/{id}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
        Route::put('/{id}', [CustomerController::class, 'update'])->middleware('permission:customers.edit');
        Route::delete('/{id}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');
    });

    // Services & Packages
    Route::get('/services', [ServiceController::class, 'index'])->middleware('permission:services.view');
    Route::post('/services', [ServiceController::class, 'store'])->middleware('permission:services.create');
    Route::put('/services/{id}', [ServiceController::class, 'update'])->middleware('permission:services.edit');
    Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->middleware('permission:services.delete');
    Route::get('/service-categories', [ServiceController::class, 'getCategories'])->middleware('permission:services.view');
    Route::post('/service-categories', [ServiceController::class, 'storeCategory'])->middleware('permission:services.create');
    Route::get('/service-packages', [ServiceController::class, 'getPackages'])->middleware('permission:services.view');
    Route::post('/service-packages', [ServiceController::class, 'storePackage'])->middleware('permission:services.create');

    // Staff
    Route::prefix('staff')->group(function () {
        Route::get('/', [StaffController::class, 'index']);
        Route::post('/', [StaffController::class, 'store'])->middleware('permission:staff.create');
        Route::get('/{id}', [StaffController::class, 'show'])->middleware('permission:staff.view');
        Route::put('/{id}', [StaffController::class, 'update'])->middleware('permission:staff.edit');
        Route::get('/{id}/performance', [StaffController::class, 'performance'])->middleware('permission:staff.view');
        Route::delete('/{id}', [StaffController::class, 'destroy'])->middleware('permission:staff.edit');
    });

    // Staff Salaries & Payroll
    Route::prefix('salaries')->group(function () {
        Route::get('/', [SalaryController::class, 'index'])->middleware('permission:staff.view');
        Route::post('/calculate-preview', [SalaryController::class, 'previewCalculate'])->middleware('permission:staff.view');
        Route::post('/', [SalaryController::class, 'store'])->middleware('permission:staff.create');
        Route::get('/{id}', [SalaryController::class, 'show'])->middleware('permission:staff.view');
        Route::put('/{id}', [SalaryController::class, 'update'])->middleware('permission:staff.edit');
        Route::post('/{id}/pay', [SalaryController::class, 'markAsPaid'])->middleware('permission:staff.edit');
        Route::delete('/{id}', [SalaryController::class, 'destroy'])->middleware('permission:staff.edit');
        Route::get('/{id}/payslip', [SalaryController::class, 'getPayslip'])->middleware('permission:staff.view');
    });

    // Inventory & Products
    Route::prefix('inventory')->group(function () {
        Route::get('/products', [InventoryController::class, 'index'])->middleware('permission:inventory.view');
        Route::post('/products', [InventoryController::class, 'storeProduct'])->middleware('permission:inventory.create');
        Route::put('/products/{id}', [InventoryController::class, 'updateProduct'])->middleware('permission:inventory.create');
        Route::post('/adjust', [InventoryController::class, 'adjustStock'])->middleware('permission:inventory.adjust');
        Route::get('/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view');
        Route::get('/low-stock', [InventoryController::class, 'lowStock'])->middleware('permission:inventory.view');
    });

    // Purchases & Suppliers
    Route::prefix('purchases')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->middleware('permission:purchases.view');
        Route::post('/', [PurchaseController::class, 'store'])->middleware('permission:purchases.create');
        Route::get('/{id}', [PurchaseController::class, 'show'])->middleware('permission:purchases.view');
    });
    Route::get('/suppliers', [PurchaseController::class, 'getSuppliers'])->middleware('permission:purchases.view');
    Route::post('/suppliers', [PurchaseController::class, 'storeSupplier'])->middleware('permission:purchases.create');

    // Bridal & Item Rentals
    Route::prefix('rentals')->group(function () {
        Route::get('/catalog', [RentalController::class, 'index'])->middleware('permission:inventory.view');
        Route::post('/catalog', [RentalController::class, 'store'])->middleware('permission:inventory.create');
        Route::get('/catalog/{id}', [RentalController::class, 'show'])->middleware('permission:inventory.view');
        Route::put('/catalog/{id}', [RentalController::class, 'update'])->middleware('permission:inventory.create');
        Route::delete('/catalog/{id}', [RentalController::class, 'destroy'])->middleware('permission:inventory.create');
        
        Route::get('/orders', [RentalController::class, 'getOrders'])->middleware('permission:pos.view');
        Route::post('/orders', [RentalController::class, 'createOrder'])->middleware('permission:pos.create');
        Route::post('/orders/{id}/return', [RentalController::class, 'returnOrder'])->middleware('permission:pos.create');
        Route::get('/summary', [RentalController::class, 'getSummary'])->middleware('permission:dashboard.view');
    });

    // Expenses
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expenses.view');
        Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
        Route::delete('/{id}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');
        Route::get('/categories', [ExpenseController::class, 'getCategories'])->middleware('permission:expenses.view');
        Route::post('/categories', [ExpenseController::class, 'storeCategory'])->middleware('permission:expenses.create');
    });

    // Reports
    Route::get('/reports/summary', [ReportController::class, 'getSummary'])->middleware('permission:reports.view');

    // User Management
    Route::prefix('users')->middleware('permission:users.view')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
        Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.edit');
        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
    });

    // Roles & Permissions
    Route::prefix('roles')->middleware('permission:roles.view')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:roles.edit');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
    });
    Route::get('/permissions', [RoleController::class, 'getPermissions'])->middleware('permission:roles.view');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit_logs.view');

    // System Settings
    Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings.view');
    Route::post('/settings', [SettingController::class, 'update'])->middleware('permission:settings.edit');
});
