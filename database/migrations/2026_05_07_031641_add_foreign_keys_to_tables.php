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
        // 1. Relasi tabel Users ke Mahasiswa
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('set null');
        });

        // 2. Relasi tabel Attendance ke Mahasiswa dan Camera Streams
        Schema::table('attendance', function (Blueprint $table) {
            $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('cascade');
            $table->foreign('camera_id')->references('id')->on('camera_streams')->onDelete('set null');
        });

        // 3. Relasi tabel Izin Submissions ke Mahasiswa
        Schema::table('izin_submissions', function (Blueprint $table) {
            $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('cascade');
        });

        // 4. Relasi tabel Kehadiran Submissions ke Mahasiswa
        Schema::table('kehadiran_submissions', function (Blueprint $table) {
            $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('cascade');
        });

        // 5. Relasi tabel Sertifikat History ke Mahasiswa
        Schema::table('sertifikat_history', function (Blueprint $table) {
            $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('cascade');
        });

        // 6. Relasi tabel System Logs ke Camera Streams
        Schema::table('system_logs', function (Blueprint $table) {
            $table->foreign('camera_id')->references('id')->on('camera_streams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Menghapus relasi jika dilakukan rollback (php artisan migrate:rollback)
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['mahasiswa_id']);
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['mahasiswa_id']);
            $table->dropForeign(['camera_id']);
        });

        Schema::table('izin_submissions', function (Blueprint $table) {
            $table->dropForeign(['mahasiswa_id']);
        });

        Schema::table('kehadiran_submissions', function (Blueprint $table) {
            $table->dropForeign(['mahasiswa_id']);
        });

        Schema::table('sertifikat_history', function (Blueprint $table) {
            $table->dropForeign(['mahasiswa_id']);
        });

        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropForeign(['camera_id']);
        });
    }
};