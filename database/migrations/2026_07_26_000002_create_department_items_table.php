<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('sub_unit_quantity')->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_items');
    }
};
