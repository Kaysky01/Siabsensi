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
        Schema::table('attendance', function (Blueprint $table) {
            $table->foreignId('sesi_id')->nullable()->after('kegiatan_id')->constrained('kegiatan_sesi')->onDelete('cascade');
            $table->string('absen_by', 255)->nullable()->after('sesi_id')->comment('Username yang mengabsen (untuk manual attendance)');
            $table->timestamp('absen_at')->nullable()->after('absen_by')->comment('Waktu diabsen manual');
            
            $table->index('sesi_id');
            $table->index(['kegiatan_id', 'sesi_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['sesi_id']);
            $table->dropIndex(['sesi_id']);
            $table->dropIndex(['kegiatan_id', 'sesi_id']);
            $table->dropColumn(['sesi_id', 'absen_by', 'absen_at']);
        });
    }
};
