<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\KegiatanSesi;
use App\Models\Mahasiswa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();
        
        if (!$user->assigned_kompi) {
            return view('garda.dashboard-empty')->with('message', 'Kompi belum ditugaskan');
        }

        $today = Carbon::today()->toDateString();
        $kompi = $user->assigned_kompi;

        $totalMahasiswa = Mahasiswa::where('kompi', $kompi)
            ->where('is_active', 1)
            ->count();

        $presentToday = Attendance::join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.date', $today)
            ->where('mahasiswa.kompi', $kompi)
            ->where('attendance.status', '!=', 'alpha')
            ->distinct()
            ->count('attendance.mahasiswa_id');

        $izinToday = Attendance::join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.date', $today)
            ->where('mahasiswa.kompi', $kompi)
            ->where('attendance.status', 'izin')
            ->distinct()
            ->count('attendance.mahasiswa_id');

        $absentToday = max(0, $totalMahasiswa - $presentToday - $izinToday);
        $presentPct = $totalMahasiswa > 0 ? round(($presentToday / $totalMahasiswa) * 100) : 0;

        $recentAttendances = Attendance::with('sesi')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.date', $today)
            ->where('mahasiswa.kompi', $kompi)
            ->orderBy('attendance.check_in', 'desc')
            ->select('attendance.*', 'mahasiswa.name')
            ->take(8)
            ->get();

        $activeKegiatan = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])
            ->where('is_active', 1)
            ->orderBy('jam_mulai')
            ->take(5)
            ->get();

        $mahasiswaList = Mahasiswa::where('kompi', $kompi)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('garda.dashboard', compact(
            'kompi',
            'totalMahasiswa',
            'presentToday',
            'izinToday',
            'absentToday',
            'presentPct',
            'recentAttendances',
            'activeKegiatan',
            'mahasiswaList'
        ));
    }
}
