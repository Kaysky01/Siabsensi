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
        $totalMahasiswaAktif = Mahasiswa::where('is_active', true)->count();
        $totalMahasiswaNonAktif = Mahasiswa::where('is_active', false)->count();

        // 2. Data Mahasiswa Hadir Hari Ini (Aktif & Sudah Absen)
        // (Catatan: Sesuaikan kolom 'created_at' menjadi 'check_in' jika nama kolom timestamp absen Anda berbeda)
        $mahasiswaHadirHariIni = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('mahasiswa.is_active', 1)
            ->whereDate('attendance.created_at', $today)
            ->distinct('attendance.mahasiswa_id')
            ->count('attendance.mahasiswa_id');
            
        $mahasiswaTidakHadir = $totalMahasiswaAktif - $mahasiswaHadirHariIni;

        // 3. Persentase Kehadiran
        $persentaseKehadiran = $totalMahasiswaAktif > 0 ? round(($mahasiswaHadirHariIni / $totalMahasiswaAktif) * 100) : 0;

        // 4. Mahasiswa Masih di Kantor (Belum absen keluar)
        $mahasiswaMasihDiKantor = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('mahasiswa.is_active', 1)
            ->whereDate('attendance.created_at', $today)
            ->whereNull('attendance.check_out') // Mencari yang belum absen keluar
            ->distinct('attendance.mahasiswa_id')
            ->count('attendance.mahasiswa_id');

        // 5. Absensi Terkini (5 data terbaru hari ini)
        $absensiTerkini = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->select('mahasiswa.name', 'attendance.created_at', 'attendance.check_out')
            ->whereDate('attendance.created_at', $today)
            ->orderBy('attendance.created_at', 'desc')
            ->limit(5)
            ->get();

        // 6. Tren Kehadiran 7 Hari Terakhir
        $tren7Hari = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = DB::table('attendance')
                ->whereDate('created_at', $date)
                ->distinct('mahasiswa_id')
                ->count('mahasiswa_id');
            $tren7Hari[] = [
                'tanggal' => $date->format('d M'),
                'jumlah' => $count
            ];
        }

        // 7. Kehadiran Per Kelompok (Hari Ini)
        $perKelompok = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.created_at', $today)
            ->select('mahasiswa.kelompok', DB::raw('count(DISTINCT attendance.mahasiswa_id) as total'))
            ->groupBy('mahasiswa.kelompok')
            ->orderBy('total', 'desc')
            ->get();

        // 8. Seluruh Absensi Hari Ini (untuk halaman "Absensi Hari Ini")
        $seluruhAbsensiHariIni = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->select(
                'mahasiswa.name',
                'mahasiswa.kelompok',
                'attendance.created_at',
                'attendance.check_out'
            )
            ->whereDate('attendance.created_at', $today)
            ->orderBy('attendance.created_at', 'desc')
            ->get();

        // 9. Seluruh Data Mahasiswa
        $mahasiswas = Mahasiswa::orderBy('name', 'asc')->get();

        // 10. Riwayat Absensi
        $riwayatAbsensi = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->select(
                'mahasiswa.name',
                'mahasiswa.kelompok',
                'attendance.created_at',
                'attendance.check_out'
            )
            ->orderBy('attendance.created_at', 'desc')
            ->get();

        // Mengirim seluruh data ke view
        return view('admin.dashboard', compact(
            'totalMahasiswaAktif',
            'totalMahasiswaNonAktif',
            'mahasiswaHadirHariIni',
            'mahasiswaTidakHadir',
            'persentaseKehadiran',
            'mahasiswaMasihDiKantor',
            'absensiTerkini',
            'tren7Hari',
            'perKelompok',
            'seluruhAbsensiHariIni',
            'mahasiswas',
            'riwayatAbsensi'
        ));
    }
}