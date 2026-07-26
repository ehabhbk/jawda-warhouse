<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->integer('system_quantity')->default(0);
            $table->integer('actual_quantity')->nullable();
            $table->integer('difference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['count_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
    }
};
