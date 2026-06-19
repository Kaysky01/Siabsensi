<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        // Buat beberapa Mahasiswa tambahan untuk testing dashboard
        $mahasiswas = [
            ['id' => 'MHS-002', 'name' => 'Siti Aisyah', 'kompi' => 'Kompi A', 'prodi' => 'D3 Teknik Informatika', 'is_active' => 1],
            ['id' => 'MHS-003', 'name' => 'Andi Wijaya', 'kompi' => 'Kompi B', 'prodi' => 'S1 Sistem Informasi', 'is_active' => 1],
            ['id' => 'MHS-004', 'name' => 'Rina Melati', 'kompi' => 'Kompi B', 'prodi' => 'D3 Teknik Informatika', 'is_active' => 1],
            ['id' => 'MHS-005', 'name' => 'Eko Prasetyo', 'kompi' => 'Kompi C', 'prodi' => 'S1 Teknik Informatika', 'is_active' => 1],
            ['id' => 'MHS-006', 'name' => 'Dwi Handayani', 'kompi' => 'Kompi C', 'prodi' => 'D3 Sistem Informasi', 'is_active' => 1],
        ];

        foreach ($mahasiswas as $mhs) {
            DB::table('mahasiswa')->updateOrInsert(
                ['id' => $mhs['id']],
                [
                    'name' => $mhs['name'],
                    'kompi' => $mhs['kompi'],
                    'jurusan' => 'Sistem Informasi',
                    'prodi' => $mhs['prodi'],
                    'email' => strtolower(str_replace(' ', '', $mhs['name'])).'@mhs.test',
                    'qr_code_id' => 'QR-'.$mhs['id'],
                    'is_active' => $mhs['is_active'],
                    'created_at' => Carbon::now(),
                ]
            );
        }

        // Ambil semua mahasiswa aktif
        $activeMahasiswaIds = DB::table('mahasiswa')->where('is_active', 1)->pluck('id')->toArray();

        // Hapus data attendance agar fresh setiap dis-seed
        DB::table('attendance')->truncate();

        // Buat data absensi 7 hari sebelum hari ini (hari ini sengaja dikosongkan)
        $attendances = [];
        for ($i = 7; $i >= 1; $i--) {
            $date = Carbon::today()->subDays($i);

            // Randomly pick some students who attended on this day
            foreach ($activeMahasiswaIds as $index => $mhsId) {
                // Buat variasi: tidak semua hadir setiap hari (sekitar 80% hadir)
                if (rand(1, 10) > 2) {
                    $checkIn = $date->copy()->setTime(rand(7, 8), rand(0, 59), 0);

                    $checkOut = $checkIn->copy()->addHours(rand(4, 8))->addMinutes(rand(0, 59));

                    $attendances[] = [
                        'mahasiswa_id' => $mhsId,
                        'date' => $date->format('Y-m-d'),
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'status' => 'hadir',
                        'created_at' => $checkIn,
                    ];
                }
            }
        }

        // Insert semua ke tabel attendance
        DB::table('attendance')->insert($attendances);
    }
}
