<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('attendance_sesi')) {
            Schema::drop('attendance_sesi');
        }

        Schema::create('attendance_sesi', function (Blueprint $table) {
            $table->id();
            $table->integer('attendance_id');
            $table->unsignedBigInteger('sesi_id');
            $table->string('mahasiswa_id', 50);
            $table->string('status', 20)->default('present');
            $table->string('absen_by', 255)->nullable();
            $table->timestamp('absen_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['sesi_id', 'mahasiswa_id'], 'attendance_sesi_unique_sesi_mahasiswa');
            $table->index(['attendance_id', 'sesi_id'], 'attendance_sesi_idx_attendance_sesi');

            $table->foreign('attendance_id')->references('id')->on('attendance')->onDelete('cascade');
            $table->foreign('sesi_id')->references('id')->on('kegiatan_sesi')->onDelete('cascade');
            $table->foreign('mahasiswa_id')->references('id')->on('mahasiswa')->onDelete('cascade');
        });

        $legacyRows = DB::table('attendance')
            ->whereNotNull('sesi_id')
            ->orderBy('id')
            ->get();

        foreach ($legacyRows as $legacyRow) {
            $parentAttendance = DB::table('attendance')
                ->where('mahasiswa_id', $legacyRow->mahasiswa_id)
                ->where('date', $legacyRow->date)
                ->whereNull('sesi_id')
                ->whereNull('kegiatan_id')
                ->orderBy('id')
                ->first();

            if (!$parentAttendance) {
                $parentAttendanceId = DB::table('attendance')->insertGetId([
                    'mahasiswa_id' => $legacyRow->mahasiswa_id,
                    'check_in' => $legacyRow->check_in,
                    'check_out' => $legacyRow->check_out,
                    'date' => $legacyRow->date,
                    'status' => $legacyRow->status ?: 'hadir',
                    'camera_id' => $legacyRow->camera_id,
                    'snapshot_path' => $legacyRow->snapshot_path,
                    'yolo_confidence' => $legacyRow->yolo_confidence,
                    'notes' => $legacyRow->notes,
                    'created_at' => $legacyRow->created_at,
                    'kegiatan_id' => null,
                    'check_in_time' => $legacyRow->check_in_time,
                    'check_out_time' => $legacyRow->check_out_time,
                    'is_late' => $legacyRow->is_late ?? false,
                    'late_duration' => $legacyRow->late_duration ?? 0,
                    'late_overridden' => $legacyRow->late_overridden ?? false,
                    'overridden_by' => $legacyRow->overridden_by ?? null,
                    'override_reason' => $legacyRow->override_reason ?? null,
                    'override_timestamp' => $legacyRow->override_timestamp ?? null,
                ]);
            } else {
                $parentAttendanceId = $parentAttendance->id;
            }

            DB::table('attendance_sesi')->updateOrInsert(
                [
                    'sesi_id' => $legacyRow->sesi_id,
                    'mahasiswa_id' => $legacyRow->mahasiswa_id,
                ],
                [
                    'attendance_id' => $parentAttendanceId,
                    'status' => $legacyRow->status ?: 'present',
                    'absen_by' => $legacyRow->absen_by,
                    'absen_at' => $legacyRow->absen_at,
                    'created_at' => $legacyRow->created_at,
                ]
            );
        }

        DB::table('attendance')
            ->whereNotNull('sesi_id')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sesi');
    }
};
