<?php

namespace App\Http\Controllers\Timdis;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSesi;
use App\Models\KegiatanSesi;
use App\Models\Mahasiswa;

class AbsensiSesiController extends Controller
{
    /**
     * List all sesi for absensi persesi
     */
    public function listSesi()
    {
        // Get all active schedules with their sesi
        $schedules = \App\Models\PkkmbSchedule::with(['sesi' => function($query) {
            $query->where('is_active', 1)->orderBy('jam_mulai');
        }])
        ->where('is_active', 1)
        ->orderBy('tanggal')
        ->get();
        
        return view('timdis.absensi-persesi', compact('schedules'));
    }

    /**
     * View monitoring/rekap for a specific sesi
     *
     * @param int|string $sesiId
     */
    public function monitoring($sesiId)
    {
        $sesi = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->findOrFail($sesiId);
        $search = trim((string) request('search', ''));
        $status = trim((string) request('status', ''));
        $kompi = trim((string) request('kompi', ''));
        $assignedKompi = auth()->user()->assigned_kompi;
        $mahasiswaQuery = Mahasiswa::where('is_active', 1);
        if ($assignedKompi) {
            $mahasiswaQuery->where('kompi', $assignedKompi);
        }
        $kompiOptions = (clone $mahasiswaQuery)
            ->select('kompi')
            ->distinct()
            ->orderBy('kompi')
            ->pluck('kompi');
        $tanggal = $sesi->tanggal ? $sesi->tanggal->format('Y-m-d') : now()->format('Y-m-d');
        $dailyAttendances = Attendance::daily()
            ->where('date', $tanggal)
            ->whereNotNull('check_in')
            ->get()
            ->keyBy('mahasiswa_id');
        
        // Get attendance records
        $attendances = AttendanceSesi::where('sesi_id', $sesiId)
            ->get()
            ->keyBy('mahasiswa_id');

        if ($search !== '') {
            $mahasiswaQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhere('kompi', 'like', "%{$search}%")
                    ->orWhere('prodi', 'like', "%{$search}%");
            });
        }

        if ($kompi !== '') {
            $mahasiswaQuery->where('kompi', $kompi);
        }

        $filteredMahasiswaIds = (clone $mahasiswaQuery)->pluck('id');

        if ($status === 'hadir') {
            $mahasiswaQuery->whereIn('id', $attendances->keys()->all());
        } elseif ($status === 'belum_sesi') {
            $mahasiswaQuery->whereIn('id', $filteredMahasiswaIds->intersect($dailyAttendances->keys())->diff($attendances->keys())->all());
        } elseif ($status === 'belum_masuk') {
            $mahasiswaQuery->whereNotIn('id', $dailyAttendances->keys()->all());
        }

        $totalMahasiswa = $filteredMahasiswaIds->count();
        $eligibleTotal = $filteredMahasiswaIds->intersect($dailyAttendances->keys())->count();
        $totalHadir = $filteredMahasiswaIds->intersect($attendances->keys())->count();
        
        $mahasiswaPaginated = (clone $mahasiswaQuery)
            ->orderBy('kompi')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
        
        return view('timdis.monitoring-sesi', compact(
            'sesi',
            'mahasiswaPaginated',
            'attendances',
            'totalHadir',
            'totalMahasiswa',
            'dailyAttendances',
            'eligibleTotal',
            'kompiOptions',
            'search',
            'status',
            'kompi'
        ));
    }
}
