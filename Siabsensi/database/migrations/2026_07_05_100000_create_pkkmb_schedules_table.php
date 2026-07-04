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
        Schema::create('pkkmb_schedules', function (Blueprint $table) {
            $table->id();
            $table->integer('hari_ke')->comment('PKKMB Hari ke-1, 2, 3, dst');
            $table->date('tanggal')->unique();
            $table->time('check_in_start')->comment('Waktu mulai check-in');
            $table->time('check_in_end')->comment('Batas check-in');
            $table->time('check_out_start')->comment('Waktu mulai check-out');
            $table->time('check_out_end')->comment('Batas akhir check-out');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('tanggal');
            $table->index('hari_ke');
            $table->index(['tanggal', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pkkmb_schedules');
    }
};
