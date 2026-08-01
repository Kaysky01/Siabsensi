<?php

namespace Database\Seeders;

use App\Models\Kompi;
use App\Models\Mahasiswa;
use App\Models\PkkmbSchedule;
use App\Models\KegiatanSesi;
use App\Models\Attendance;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AdminTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Create Kompi: "Kompi 1", "Kompi 2", "Kompi 10" (to test natural sorting).
        $kompi1 = Kompi::firstOrCreate(['nama' => 'Kompi 1']);
        $kompi2 = Kompi::firstOrCreate(['nama' => 'Kompi 2']);
        $kompi10 = Kompi::firstOrCreate(['nama' => 'Kompi 10']);

        // 2. Get 3 random active Mahasiswa (or the first 3). Assign them to these Kompis.
        $mahasiswa1 = Mahasiswa::where('is_active', true)->first();
        $mahasiswa2 = Mahasiswa::where('is_active', true)->skip(1)->first();
        $mahasiswa3 = Mahasiswa::where('is_active', true)->skip(2)->first();

        if ($mahasiswa1) {
            $mahasiswa1->update(['kompi' => $kompi1->id]);
        }
        if ($mahasiswa2) {
            $mahasiswa2->update(['kompi' => $kompi2->id]);
        }
        if ($mahasiswa3) {
            $mahasiswa3->update(['kompi' => $kompi10->id]);
        }

        // 3. Create a 3-day PkkmbSchedule (Hari 1, Hari 2, Hari 3).
        $today = Carbon::today();
        $schedule1 = PkkmbSchedule::firstOrCreate(
            ['hari_ke' => 1],
            ['tanggal' => $today->copy()->subDays(2), 'check_in_start' => '07:00:00', 'check_in_end' => '08:00:00', 'check_out_start' => '13:00:00', 'check_out_end' => '14:00:00']
        );
        $schedule2 = PkkmbSchedule::firstOrCreate(
            ['hari_ke' => 2],
            ['tanggal' => $today->copy()->subDays(1), 'check_in_start' => '07:00:00', 'check_in_end' => '08:00:00', 'check_out_start' => '13:00:00', 'check_out_end' => '14:00:00']
        );
        $schedule3 = PkkmbSchedule::firstOrCreate(
            ['hari_ke' => 3],
            ['tanggal' => $today->copy(), 'check_in_start' => '07:00:00', 'check_in_end' => '08:00:00', 'check_out_start' => '13:00:00', 'check_out_end' => '14:00:00']
        );

        // 4. Create a few KegiatanSesi for these schedules.
        // Day 1 Sesi
        $sesi1Day1 = KegiatanSesi::firstOrCreate(
            ['pkkmb_schedule_id' => $schedule1->id, 'nama_sesi' => 'Sesi Pagi Hari 1'],
            ['jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00']
        );
        $sesi2Day1 = KegiatanSesi::firstOrCreate(
            ['pkkmb_schedule_id' => $schedule1->id, 'nama_sesi' => 'Sesi Siang Hari 1'],
            ['jam_mulai' => '13:00:00', 'jam_selesai' => '14:00:00']
        );

        // Day 2 Sesi
        $sesi1Day2 = KegiatanSesi::firstOrCreate(
            ['pkkmb_schedule_id' => $schedule2->id, 'nama_sesi' => 'Sesi Pagi Hari 2'],
            ['jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00']
        );
        $sesi2Day2 = KegiatanSesi::firstOrCreate(
            ['pkkmb_schedule_id' => $schedule2->id, 'nama_sesi' => 'Sesi Siang Hari 2'],
            ['jam_mulai' => '13:00:00', 'jam_selesai' => '14:00:00']
        );

        // Day 3 Sesi
        $sesi1Day3 = KegiatanSesi::firstOrCreate(
            ['pkkmb_schedule_id' => $schedule3->id, 'nama_sesi' => 'Sesi Pagi Hari 3'],
            ['jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00']
        );
        $sesi2Day3 = KegiatanSesi::firstOrCreate(
            ['pkkmb_schedule_id' => $schedule3->id, 'nama_sesi' => 'Sesi Siang Hari 3'],
            ['jam_mulai' => '13:00:00', 'jam_selesai' => '14:00:00']
        );

        // 5. Attendance
        $allSesi = [$sesi1Day1, $sesi2Day1, $sesi1Day2, $sesi2Day2, $sesi1Day3, $sesi2Day3];

        // For Student 1: Create 100% attendance across all 3 days, but make them LATE on Day 1 (is_late = true, late_duration = 20)
        if ($mahasiswa1) {
            foreach ($allSesi as $sesi) {
                $isLate = false;
                $lateDuration = 0;
                
                // LATE on Day 1, Sesi Pagi
                if ($sesi->id == $sesi1Day1->id) {
                    $isLate = true;
                    $lateDuration = 20;
                }

                Attendance::updateOrCreate(
                    ['mahasiswa_id' => $mahasiswa1->id, 'sesi_id' => $sesi->id],
                    [
                        'check_in_time' => Carbon::parse($sesi->jam_mulai)->addMinutes($lateDuration)->format('H:i:s'),
                        'date' => $today->format('Y-m-d'),
                        'status' => 'hadir',
                        'is_late' => $isLate,
                        'late_duration' => $lateDuration,
                        'absen_by' => 'system',
                        'absen_at' => now(),
                        'snapshot_path' => null
                    ]
                );
            }
        }

        // For Student 2: Create 50% attendance (present on day 1 and 2, absent on day 3) to test Kelulusan (< 80% = Tidak Lulus).
        if ($mahasiswa2) {
            $day1Day2Sesi = [$sesi1Day1, $sesi2Day1, $sesi1Day2, $sesi2Day2];
            foreach ($day1Day2Sesi as $sesi) {
                Attendance::updateOrCreate(
                    ['mahasiswa_id' => $mahasiswa2->id, 'sesi_id' => $sesi->id],
                    [
                        'check_in_time' => $sesi->jam_mulai,
                        'date' => $today->format('Y-m-d'),
                        'status' => 'hadir',
                        'is_late' => false,
                        'late_duration' => 0,
                        'absen_by' => 'system',
                        'absen_at' => now(),
                        'snapshot_path' => null
                    ]
                );
            }
            
            // Absent on day 3
            $day3Sesi = [$sesi1Day3, $sesi2Day3];
            foreach ($day3Sesi as $sesi) {
                Attendance::updateOrCreate(
                    ['mahasiswa_id' => $mahasiswa2->id, 'sesi_id' => $sesi->id],
                    [
                        'check_in_time' => null,
                        'date' => $today->format('Y-m-d'),
                        'status' => 'alfa',
                        'is_late' => false,
                        'late_duration' => 0,
                        'absen_by' => 'system',
                        'absen_at' => now(),
                        'snapshot_path' => null
                    ]
                );
            }
        }

        // For Student 3: Create 1 attendance (sakit/izin) to test other statuses.
        if ($mahasiswa3) {
            Attendance::updateOrCreate(
                ['mahasiswa_id' => $mahasiswa3->id, 'sesi_id' => $sesi1Day1->id],
                [
                    'check_in_time' => null,
                    'date' => $today->format('Y-m-d'),
                    'status' => 'sakit',
                    'is_late' => false,
                    'late_duration' => 0,
                    'absen_by' => 'system',
                    'absen_at' => now(),
                    'snapshot_path' => 'surat_sakit.jpg'
                ]
            );
        }
    }
}
