<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rental Items Catalog (Sarees, Accessories, Bridal Jewellery, Veils, etc.)
        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g. RNT-SAR-001
            $table->string('category')->default('Bridal Accessories'); // Sarees & Lehengas, Jewellery Sets, Hair & Veils, Bridal Accessories, Costumes & Props
            $table->text('description')->nullable();
            $table->decimal('rental_price_per_24h', 10, 2); // 24-hour standard rental fee
            $table->decimal('security_deposit', 10, 2)->default(0.00); // Refundable deposit
            $table->decimal('late_fee_per_day', 10, 2)->default(500.00); // Late fee if overdue
            $table->integer('total_quantity')->default(1);
            $table->integer('available_quantity')->default(1);
            $table->enum('condition', ['Pristine', 'Excellent', 'Good', 'Needs Cleaning', 'Minor Wear'])->default('Excellent');
            $table->string('image')->nullable();
            $table->enum('status', ['available', 'rented', 'maintenance', 'inactive'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Rental Orders / Bookings
        Schema::create('rental_orders', function (Blueprint $table) {
            $table->id();
            $table->string('rental_number')->unique(); // e.g. SV-RNT-2026-000001
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_nic')->nullable(); // National ID for rental guarantee
            $table->foreignId('rental_item_id')->constrained('rental_items')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->dateTime('rental_start_date');
            $table->integer('duration_hours')->default(24); // Standard 24h
            $table->dateTime('due_return_date'); // start + 24 hours
            $table->dateTime('actual_return_date')->nullable();
            $table->decimal('rental_fee', 10, 2);
            $table->decimal('deposit_amount', 10, 2)->default(0.00);
            $table->decimal('total_collected', 10, 2); // rental_fee + deposit_amount
            $table->decimal('deposit_refunded', 10, 2)->default(0.00);
            $table->decimal('late_fee_charged', 10, 2)->default(0.00);
            $table->decimal('damage_fee_charged', 10, 2)->default(0.00);
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'split'])->default('cash');
            $table->enum('status', ['active', 'returned', 'overdue', 'cancelled'])->default('active');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('return_condition_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Update SaleItems table item_type if possible
        // (In MySQL/SQLite, sale_items has enum ['service', 'product', 'package'])
        // We will support 'rental' in application layer and add rental_order_id foreign key if column added
        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->foreignId('rental_order_id')->nullable()->after('item_id');
                $table->dateTime('rental_return_due')->nullable()->after('rental_order_id');
                $table->decimal('rental_deposit', 10, 2)->default(0.00)->after('rental_return_due');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_items')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropColumn(['rental_order_id', 'rental_return_due', 'rental_deposit']);
            });
        }
        Schema::dropIfExists('rental_orders');
        Schema::dropIfExists('rental_items');
    }
};
