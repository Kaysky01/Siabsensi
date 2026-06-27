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
        Schema::create('kehadiran_submissions', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('mahasiswa_id', 50);
            $table->date('date')->index('idx_kh_date');
            $table->time('check_in_time');
            $table->time('check_out_time');
            $table->text('keterangan');
            $table->text('bukti_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->nullable()->default('pending')->index('idx_kh_status');
            $table->string('verified_by', 100)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('created_at')->useCurrent()->index('idx_kh_created');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kehadiran_submissions');
    }
};
