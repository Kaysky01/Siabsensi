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
        Schema::create('attendance', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('mahasiswa_id', 50)->index();
            $table->dateTime('check_in')->nullable()->index();
            $table->dateTime('check_out')->nullable();
            $table->date('date')->index();
            $table->string('status', 20)->nullable()->default('present');
            $table->string('camera_id', 50)->nullable();
            $table->text('snapshot_path')->nullable();
            $table->float('yolo_confidence')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unsignedBigInteger('kegiatan_id')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();

            $table->index(['date', 'check_in'], 'idx_att_date_checkin');
            $table->index('created_at', 'idx_att_created_at');
            $table->index(['mahasiswa_id', 'date'], 'idx_att_mahasiswa_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
