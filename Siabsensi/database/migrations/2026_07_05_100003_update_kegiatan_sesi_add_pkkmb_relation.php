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
        // Update kegiatan_sesi table structure
        Schema::table('kegiatan_sesi', function (Blueprint $table) {
            // Drop kegiatan_id foreign key temporarily
            $table->dropForeign(['kegiatan_id']);
            
            // Make kegiatan_id nullable and rename concept
            $table->unsignedBigInteger('kegiatan_id')->nullable()->change();
            
            // Add pkkmb_schedule_id
            $table->foreignId('pkkmb_schedule_id')->nullable()->after('id')->constrained('pkkmb_schedules')->onDelete('cascade');
            
            // Re-add foreign key for kegiatan_id
            $table->foreign('kegiatan_id')->references('id')->on('kegiatan')->onDelete('cascade');
            
            // Add index
            $table->index('pkkmb_schedule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kegiatan_sesi', function (Blueprint $table) {
            $table->dropForeign(['pkkmb_schedule_id']);
            $table->dropIndex(['pkkmb_schedule_id']);
            $table->dropColumn('pkkmb_schedule_id');
            
            // Restore kegiatan_id to not nullable
            $table->unsignedBigInteger('kegiatan_id')->nullable(false)->change();
        });
    }
};
