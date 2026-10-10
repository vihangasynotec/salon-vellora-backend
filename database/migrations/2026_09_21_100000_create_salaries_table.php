<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->string('salary_slip_number')->unique();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->unsignedTinyInteger('month'); // 1 - 12
            $table->unsignedSmallInteger('year'); // e.g. 2026
            
            // Base Salary & Target Threshold
            $table->decimal('basic_salary', 12, 2)->default(20000.00);
            $table->decimal('monthly_target', 12, 2)->default(500000.00);
            $table->decimal('monthly_revenue', 12, 2)->default(0.00);
            $table->boolean('target_achieved')->default(false);
            
            // Operational Deductions
            $table->decimal('electricity_bill', 10, 2)->default(0.00);
            $table->decimal('shop_rent', 10, 2)->default(0.00);
            $table->decimal('petty_cash', 10, 2)->default(0.00);
            $table->decimal('other_deductions', 10, 2)->default(0.00);
            $table->decimal('total_expenses_deducted', 12, 2)->default(0.00);
            
            // Profit & 15% Incentive Calculation
            $table->decimal('remaining_amount', 12, 2)->default(0.00);
            $table->decimal('incentive_percentage', 5, 2)->default(15.00);
            $table->decimal('incentive_amount', 12, 2)->default(0.00);
            
            // Adjustments
            $table->decimal('allowances', 10, 2)->default(0.00);
            $table->decimal('advances', 10, 2)->default(0.00);
            $table->decimal('net_salary', 12, 2)->default(0.00);
            
            // Payment Status
            $table->enum('payment_status', ['pending', 'paid'])->default('pending');
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque'])->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['staff_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
