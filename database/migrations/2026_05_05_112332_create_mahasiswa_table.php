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
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->string('id', 50)->primary(); 
            $table->string('name', 255);
            $table->string('kelompok', 100);
            $table->string('jurusan', 100);
            $table->string('email', 255)->nullable();
            $table->string('no_telp_mahasiswa', 20)->nullable();
            $table->string('no_telp_ortu', 20)->nullable();
            $table->string('qr_code_id', 100)->index();
            $table->timestamp('created_at')->useCurrent();
            $table->tinyInteger('is_active')->nullable()->default(1)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswa');
    }
};