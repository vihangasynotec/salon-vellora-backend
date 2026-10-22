<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('db:reset-transactions {--force : Bypass confirmation}', function () {
    if (!$this->option('force') && !$this->confirm('This will wipe all sales, appointments, quotations, rentals, expenses, and transaction logs, but KEEP user accounts and catalog intact. Continue?')) {
        $this->info('Reset cancelled.');
        return;
    }

    $this->info('Resetting transaction data while preserving user accounts...');

    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

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

    foreach ($tablesToTruncate as $table) {
        if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
            \Illuminate\Support\Facades\DB::table($table)->truncate();
            $this->line(" - Truncated {$table}");
        }
    }

    if (\Illuminate\Support\Facades\Schema::hasTable('customers')) {
        \Illuminate\Support\Facades\DB::table('customers')->update([
            'loyalty_points' => 0,
            'total_spend' => 0.00,
            'total_visits' => 0,
        ]);
        $this->line(" - Reset customer stats (points, spend, visits)");
    }

    if (\Illuminate\Support\Facades\Schema::hasTable('rental_items')) {
        \Illuminate\Support\Facades\DB::statement("UPDATE rental_items SET available_quantity = total_quantity, status = 'available'");
        $this->line(" - Reset rental inventory availability");
    }

    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

    $this->info('Database reset complete! All user accounts, roles, and permissions have been preserved.');
})->purpose('Reset operational and transactional data while keeping user accounts and master catalog');
