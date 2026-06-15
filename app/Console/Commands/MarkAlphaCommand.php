<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mahasiswa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarkAlphaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:mark-alpha';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mencatat status alpha untuk mahasiswa yang tidak absen di hari sebelumnya';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $kemarin = Carbon::yesterday()->format('Y-m-d');
        
        // Opsional: Abaikan jika kemarin adalah akhir pekan (Sabtu/Minggu)
        if (Carbon::yesterday()->isWeekend()) {
            $this->info("Kemarin adalah hari libur (akhir pekan). Tidak ada pencatatan alpha.");
            return;
        }

        $mahasiswas = Mahasiswa::where('is_active', 1)->get();

        foreach ($mahasiswas as $mhs) {
            // Cek apakah ada record hadir kemarin
            $hasAttendance = DB::table('attendance')->where('mahasiswa_id', $mhs->id)->whereDate('date', $kemarin)->exists();
            // Cek apakah ada record izin/sakit kemarin
            $hasIzin = $mhs->izinSubmissions()->whereDate('date', $kemarin)->where('status', 'approved')->exists();

            if (!$hasAttendance && !$hasIzin) {
                DB::table('attendance')->insert([
                    'mahasiswa_id' => $mhs->id,
                    'date' => $kemarin,
                    'check_in' => null,
                    'check_out' => null,
                    'status' => 'alpha',
                    'created_at' => now(),
                ]);
            }
        }

        $this->info("Pengecekan alpha untuk tanggal {$kemarin} selesai.");
    }
}