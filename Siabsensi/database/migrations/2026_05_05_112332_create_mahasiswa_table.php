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
            // Definisi Kolom
            $table->string('id', 50)->primary();
            $table->string('name', 255);
            $table->string('kompi', 100);
            $table->string('jurusan', 100);
            $table->string('prodi', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('no_telp_mahasiswa', 20)->nullable();
            $table->string('no_telp_ortu', 20)->nullable();
            $table->string('qr_code_id', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->tinyInteger('is_active')->nullable()->default(1);

            // ---------------------------------------------------------
            // DEFINISI INDEKS (Sesuai tabel indeks di bagian bawah)
            // ---------------------------------------------------------

            // Unik: Ya, Nama Kunci: 'qr_code_id'
            $table->unique('qr_code_id', 'qr_code_id');

            // Unik: Tidak, Nama Kunci: 'idx_qr_code'
            $table->index('qr_code_id', 'idx_qr_code');

            // Unik: Tidak, Nama Kunci: 'idx_active'
            $table->index('is_active', 'idx_active');
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
