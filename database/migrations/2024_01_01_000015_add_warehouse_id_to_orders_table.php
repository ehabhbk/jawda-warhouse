<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('storekeeper_id')->constrained()->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('rejection_reason');
            $table->foreignId('received_by')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn('received_at');
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
