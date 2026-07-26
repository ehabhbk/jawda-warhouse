<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('batch')->nullable()->after('barcode');
            $table->string('sub_unit')->nullable()->after('unit');
            $table->integer('sub_unit_quantity')->default(1)->after('sub_unit');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['batch', 'sub_unit', 'sub_unit_quantity']);
        });
    }
};
