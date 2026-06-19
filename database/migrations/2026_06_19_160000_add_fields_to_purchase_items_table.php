<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('total_price');
            $table->foreignId('warehouse_id')->nullable()->after('expiry_date')->constrained()->nullOnDelete();
            $table->string('image')->nullable()->after('warehouse_id');
            $table->string('item_name')->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['expiry_date', 'warehouse_id', 'image', 'item_name']);
        });
    }
};
