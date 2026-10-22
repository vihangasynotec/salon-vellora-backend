<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_items')) {
            DB::statement("ALTER TABLE sale_items MODIFY COLUMN item_type VARCHAR(50) NOT NULL;");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_items')) {
            DB::statement("ALTER TABLE sale_items MODIFY COLUMN item_type ENUM('service', 'product', 'package') NOT NULL;");
        }
    }
};
