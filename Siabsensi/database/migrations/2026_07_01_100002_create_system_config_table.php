<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_config', function (Blueprint $table) {
            $table->id();
            $table->string('config_key', 100)->unique()->comment('Nama config key');
            $table->text('config_value')->comment('Nilai config');
            $table->string('description', 255)->nullable()->comment('Deskripsi config');
            $table->timestamps();
            
            $table->index('config_key', 'idx_config_key');
        });

        // Insert default config untuk grace period
        DB::table('system_config')->insert([
            'config_key' => 'attendance_grace_period_minutes',
            'config_value' => '40',
            'description' => 'Waktu toleransi (dalam menit) setelah batas check-in dimana mahasiswa masih bisa absen tetapi dianggap telat',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_config');
    }
};
