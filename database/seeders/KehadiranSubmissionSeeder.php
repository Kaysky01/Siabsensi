<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KehadiranSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('kehadiran_submissions')->insert([
            [
                'mahasiswa_id' => 'MHS-004',
                'date' => Carbon::today(),
                'check_in_time' => '08:00:00',
                'check_out_time' => '17:00:00',
                'keterangan' => 'Lupa absen masuk tadi pagi karena sistem YOLO sedang offline',
                'bukti_path' => 'public/dummy_bukti.jpg',
                'status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
                'rejection_reason' => null,
                'created_at' => Carbon::now()->subHours(1),
                'updated_at' => Carbon::now(),
            ],
            [
                'mahasiswa_id' => 'MHS-005',
                'date' => Carbon::yesterday(),
                'check_in_time' => '07:45:00',
                'check_out_time' => '16:30:00',
                'keterangan' => 'Tidak terdeteksi CCTV karena mati lampu di area deteksi',
                'bukti_path' => 'public/dummy_cctv.jpg',
                'status' => 'approved',
                'verified_by' => 'Administrator Sistem',
                'verified_at' => Carbon::now()->subHours(5),
                'rejection_reason' => null,
                'created_at' => Carbon::yesterday()->subHours(2),
                'updated_at' => Carbon::now(),
            ],
            [
                'mahasiswa_id' => 'MHS-001',
                'date' => Carbon::today()->subDays(2),
                'check_in_time' => '09:00:00',
                'check_out_time' => '17:00:00',
                'keterangan' => 'Maaf telat banget baru inget buat absen',
                'bukti_path' => '',
                'status' => 'rejected',
                'verified_by' => 'Tim Kedisiplinan',
                'verified_at' => Carbon::now()->subHours(24),
                'rejection_reason' => 'Tidak melampirkan bukti yang kuat / Alasan tidak logis',
                'created_at' => Carbon::today()->subDays(2)->addHours(10),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
