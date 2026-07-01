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
            // Late tracking fields
            $table->boolean('is_late')->default(false)->after('kegiatan_id')->comment('Flag apakah mahasiswa telat check-in');
            $table->integer('late_duration')->default(0)->after('is_late')->comment('Durasi keterlambatan dalam menit');
            
            // Override fields
            $table->boolean('late_overridden')->default(false)->after('late_duration')->comment('Flag apakah status telat sudah di-override admin');
            $table->string('overridden_by', 100)->nullable()->after('late_overridden')->comment('Username admin yang melakukan override');
            $table->text('override_reason')->nullable()->after('overridden_by')->comment('Alasan override status telat');
            $table->timestamp('override_timestamp')->nullable()->after('override_reason')->comment('Waktu override dilakukan');
            
            // Indexes untuk query performance
            $table->index('is_late', 'idx_att_is_late');
            $table->index('late_overridden', 'idx_att_late_overridden');
            $table->index(['date', 'is_late'], 'idx_att_date_late');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_att_is_late');
            $table->dropIndex('idx_att_late_overridden');
            $table->dropIndex('idx_att_date_late');
            
            // Drop columns
            $table->dropColumn([
                'is_late',
                'late_duration',
                'late_overridden',
                'overridden_by',
                'override_reason',
                'override_timestamp'
            ]);
        });
    }
};
