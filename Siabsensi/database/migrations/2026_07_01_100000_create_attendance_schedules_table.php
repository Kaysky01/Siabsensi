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
        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('day_of_week')->comment('1=Monday, 2=Tuesday, ..., 7=Sunday');
            $table->time('check_in_start')->nullable()->comment('Waktu mulai absen masuk (contoh: 05:00)');
            $table->time('check_in_end')->nullable()->comment('Batas waktu absen masuk / batas telat (contoh: 06:50)');
            $table->time('check_out_start')->nullable()->comment('Waktu minimal check-out (contoh: 16:00)');
            $table->time('check_out_end')->nullable()->comment('Waktu maksimal check-out (contoh: 18:00)');
            $table->boolean('is_active')->default(true)->comment('Status aktif jadwal untuk hari ini');
            $table->timestamps();

            // Unique constraint: hanya satu jadwal per hari (tanpa memperhatikan is_active)
            $table->unique('day_of_week', 'unique_schedule_per_day');
            
            // Index untuk query performance
            $table->index(['day_of_week', 'is_active'], 'idx_day_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_schedules');
    }
};
