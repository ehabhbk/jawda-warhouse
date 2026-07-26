<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
        });
    }
};
