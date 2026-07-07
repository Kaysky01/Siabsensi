<?php

namespace Database\Seeders;

use App\Models\Mahasiswa;
use App\Models\PkkmbSchedule;
use App\Models\KegiatanSesi;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class GardaTestSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil 2 mahasiswa dari kompi A atau buat jika belum ada
        $mahasiswa = Mahasiswa::where('kompi', 'Kompi A')->limit(2)->get();
        
        if ($mahasiswa->count() < 2) {
            echo "⚠ Belum ada cukup mahasiswa di Kompi A\n";
            return;
        }

        // Create PKKMB Schedule
        $schedule = PkkmbSchedule::firstOrCreate(
            ['tanggal' => Carbon::today()],
            [
                'hari_ke' => 1,
                'check_in_start' => '08:00:00',
                'check_in_end' => '09:00:00',
                'is_active' => 1
            ]
        );

        // Create Sesi
        KegiatanSesi::firstOrCreate(
            ['pkkmb_schedule_id' => $schedule->id, 'nama_sesi' => 'Opening Ceremony'],
            [
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '09:30:00',
                'is_active' => 1
            ]
        );

        echo "✓ Test data untuk Garda berhasil dibuat\n";
    }
}
