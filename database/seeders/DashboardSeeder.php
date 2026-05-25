<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        
        // Buat beberapa Mahasiswa tambahan untuk testing dashboard
        $mahasiswas = [
            ['id' => 'MHS-002', 'name' => 'Siti Aisyah', 'kelompok' => 'Kelompok A', 'is_active' => 1],
            ['id' => 'MHS-003', 'name' => 'Andi Wijaya', 'kelompok' => 'Kelompok B', 'is_active' => 1],
            ['id' => 'MHS-004', 'name' => 'Rina Melati', 'kelompok' => 'Kelompok B', 'is_active' => 1],
            ['id' => 'MHS-005', 'name' => 'Eko Prasetyo', 'kelompok' => 'Kelompok C', 'is_active' => 1],
            ['id' => 'MHS-006', 'name' => 'Dwi Handayani', 'kelompok' => 'Kelompok C', 'is_active' => 0],
        ];

        foreach ($mahasiswas as $mhs) {
            DB::table('mahasiswa')->updateOrInsert(
                ['id' => $mhs['id']],
                [
                    'name' => $mhs['name'],
                    'kelompok' => $mhs['kelompok'],
                    'jurusan' => 'Sistem Informasi',
                    'email' => strtolower(str_replace(' ', '', $mhs['name'])) . '@mhs.test',
                    'qr_code_id' => 'QR-' . $mhs['id'],
                    'is_active' => $mhs['is_active'],
                    'created_at' => Carbon::now(),
                ]
            );
        }

        // Ambil semua mahasiswa aktif
        $activeMahasiswaIds = DB::table('mahasiswa')->where('is_active', 1)->pluck('id')->toArray();
        
        // Hapus data attendance agar fresh setiap dis-seed
        DB::table('attendance')->truncate();

        // Buat data absensi 7 hari terakhir (Tren Kehadiran)
        $attendances = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            
            // Randomly pick some students who attended on this day
            foreach ($activeMahasiswaIds as $index => $mhsId) {
                // Buat variasi: tidak semua hadir setiap hari (sekitar 80% hadir)
                if (rand(1, 10) > 2) { 
                    $checkIn = $date->copy()->setTime(rand(7, 8), rand(0, 59), 0);
                    
                    // Untuk hari ini, ada yang belum check out
                    $checkOut = null;
                    if ($i > 0 || rand(0, 1) == 1) { 
                        // Hari sebelumnya pasti check out. Hari ini 50% sudah check out.
                        $checkOut = $checkIn->copy()->addHours(rand(4, 8))->addMinutes(rand(0, 59));
                    }

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
