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
        Schema::create('sertifikat_history', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('mahasiswa_id', 50)->index('idx_mahasiswa');
            $table->text('periode');
            $table->string('template', 50);
            $table->integer('total_hadir');
            $table->decimal('persentase', 5, 2);
            $table->timestamp('created_at')->useCurrent()->index('idx_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sertifikat_history');
    }
};