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
        Schema::create('izin_submissions', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('mahasiswa_id', 50)->index('idx_mahasiswa');
            $table->enum('submission_type', ['izin', 'sakit']);
            $table->date('date')->index('idx_date');
            $table->text('keterangan');
            $table->text('bukti_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->nullable()->default('pending')->index('idx_status');
            $table->string('verified_by', 100)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('created_at')->useCurrent()->index('idx_created');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('izin_submissions');
    }
};