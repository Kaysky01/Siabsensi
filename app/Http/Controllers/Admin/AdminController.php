<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Mahasiswa;
use App\Models\IzinSubmission;
use App\Models\KehadiranSubmission;
use App\Models\CameraStream;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // <-- untuk query builder relasi tabel

class AdminController extends Controller
{
    public function dashboard_admin()
    {
        $today = Carbon::today();

        // 1. Data Statistik Mahasiswa & Kehadiran
        $totalMahasiswas = Mahasiswa::where('is_active', true)->count();
        
        // Menghitung jumlah data absensi hari ini
        $hadirHariIni = Attendance::whereDate('date', $today)->whereNotNull('check_in')->count();
        $totalIzinSakit = IzinSubmission::whereDate('date', $today)->where('status', 'approved')->count();

        // Tidak Hadir (Belum Absen / Bolos)
        $tidakHadir = $totalMahasiswas - ($hadirHariIni + $totalIzinSakit);

        // Masih di Kantor (Sudah check-in, tapi belum check-out) 
        $masihDiKantor = Attendance::whereDate('date', $today)
                            ->whereNotNull('check_in')
                            ->whereNull('check_out')
                            ->count();

        // 2. Data Notifikasi / Pending Tugas Admin
        $totalPending = IzinSubmission::where('status', 'pending')->count() + 
                        KehadiranSubmission::where('status', 'pending')->count();
                        
        $activeCameras = CameraStream::where('is_active', true)->count();

        // 3. Aktivitas Terkini (Absensi Terbaru)
        // Eager loading tabel mahasiswa dan camera, sorting menggunakan created_at 
        $recentAttendances = Attendance::with(['mahasiswa', 'camera'])
                                    ->whereDate('date', $today)
                                    ->orderBy('created_at', 'desc') 
                                    ->take(5)
                                    ->get();

        // Mengambil koleksi data mahasiswa aktif jika dibutuhkan di form/view
        $mahasiswas = Mahasiswa::where('is_active', true)->get();

        // 4. Data Tren 7 Hari Terakhir
        $tujuhHariLalu = Carbon::today()->subDays(6);
        $kehadiran7Hari = Attendance::selectRaw('DATE(date) as tanggal, COUNT(*) as total')
            ->where('date', '>=', $tujuhHariLalu)
            ->whereNotNull('check_in')
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'asc')
            ->pluck('total', 'tanggal');

        // Format data tren agar harinya selalu urut & lengkap (walau ada hari yg 0 kehadiran)
        $labelTren = [];
        $dataTren = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateStr = Carbon::today()->subDays($i)->format('Y-m-d');
            $labelTren[] = Carbon::today()->subDays($i)->isoFormat('dddd'); // Output: Senin, Selasa, dst
            $dataTren[] = $kehadiran7Hari[$dateStr] ?? 0;
        }

        // 5. Data Kehadiran Per Kelompok (Hari Ini)
        $perKelompok = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.date', $today)
            ->whereNotNull('attendance.check_in')
            ->selectRaw('mahasiswa.kelompok, COUNT(*) as total')
            ->groupBy('mahasiswa.kelompok')
            ->orderBy('total', 'desc')
            ->get();

        // Mengirim seluruh data ke view
        return view('admin.dashboard', compact(
            'totalMahasiswas', 
            'hadirHariIni', 
            'totalIzinSakit', 
            'tidakHadir', 
            'masihDiKantor',
            'totalPending', 
            'activeCameras', 
            'recentAttendances',
            'mahasiswas',
            'labelTren',
            'dataTren',
            'perKelompok'
        ));
    }
}