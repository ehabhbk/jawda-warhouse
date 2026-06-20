<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // daily, weekly, monthly, yearly
            $table->json('report_types'); // ['summary', 'inventory', 'purchases', 'sales', 'orders']
            $table->string('phone_numbers'); // comma separated
            $table->time('send_time')->default('08:00');
            $table->json('days')->nullable(); // for weekly: [1,3,5] (sun=0)
            $table->unsignedTinyInteger('day_of_month')->nullable(); // for monthly
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
