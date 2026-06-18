<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->index(['date', 'check_in'], 'idx_att_date_checkin');
            $table->index('created_at', 'idx_att_created_at');
            $table->index(['mahasiswa_id', 'date'], 'idx_att_mahasiswa_date');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('idx_att_date_checkin');
            $table->dropIndex('idx_att_created_at');
            $table->dropIndex('idx_att_mahasiswa_date');
        });
    }
};
