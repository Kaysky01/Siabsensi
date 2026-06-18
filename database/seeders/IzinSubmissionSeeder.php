<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IzinSubmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('izin_submissions')->insert([
            [
                'mahasiswa_id' => 'MHS-001',
                'submission_type' => 'izin',
                'date' => Carbon::tomorrow(),
                'keterangan' => 'Izin ada keperluan keluarga di luar kota',
                'bukti_path' => '',
                'status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
                'rejection_reason' => null,
                'created_at' => Carbon::now()->subHours(2),
                'updated_at' => Carbon::now(),
            ],
            [
                'mahasiswa_id' => 'MHS-002',
                'submission_type' => 'sakit',
                'date' => Carbon::yesterday(),
                'keterangan' => 'Sakit demam tinggi, surat dokter terlampir',
                'bukti_path' => 'public/dummy_bukti.jpg',
                'status' => 'approved',
                'verified_by' => 'Tim Kedisiplinan',
                'verified_at' => Carbon::now()->subHours(1),
                'rejection_reason' => null,
                'created_at' => Carbon::yesterday()->subDay(),
                'updated_at' => Carbon::now(),
            ],
            [
                'mahasiswa_id' => 'MHS-003',
                'submission_type' => 'izin',
                'date' => Carbon::today(),
                'keterangan' => 'Tidak ada kendaraan untuk ke kantor',
                'bukti_path' => '',
                'status' => 'rejected',
                'verified_by' => 'Administrator Sistem',
                'verified_at' => Carbon::now(),
                'rejection_reason' => 'Alasan tidak memenuhi syarat perizinan (Tidak Darurat)',
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
