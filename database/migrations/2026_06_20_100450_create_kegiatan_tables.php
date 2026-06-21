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
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255);
            $table->date('tanggal_pelaksanaan');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->boolean('wajib_hadir')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kegiatan_absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->onDelete('cascade');
            $table->string('mahasiswa_id', 50);
            $table->string('status', 20)->default('hadir');
            $table->timestamp('absen_at')->useCurrent();
            $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('cascade');
            $table->unique(['kegiatan_id', 'mahasiswa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kegiatan_absensi');
        Schema::dropIfExists('kegiatan');
    }
};
