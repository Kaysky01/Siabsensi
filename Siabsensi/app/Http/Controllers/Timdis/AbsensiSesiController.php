<?php

namespace App\Http\Controllers\Timdis;

use App\Http\Controllers\Controller;
use App\Models\KegiatanSesi;
use App\Models\Mahasiswa;
use App\Models\Attendance;

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
        $mahasiswaQuery = Mahasiswa::where('is_active', 1);
        $mahasiswaList = $mahasiswaQuery->orderBy('kompi')->orderBy('name')->get();
        
        // Get attendance records
        $attendances = Attendance::where('sesi_id', $sesiId)
            ->whereIn('mahasiswa_id', $mahasiswaList->pluck('id'))
            ->get()
            ->keyBy('mahasiswa_id');
        
        $totalHadir = $attendances->count();
        $totalMahasiswa = $mahasiswaList->count();
        
        // Pagination
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $mahasiswaPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $mahasiswaList->forPage($currentPage, $perPage),
            $mahasiswaList->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
        
        return view('timdis.monitoring-sesi', compact('sesi', 'mahasiswaPaginated', 'attendances', 'totalHadir', 'totalMahasiswa'));
    }
}
