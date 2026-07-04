<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KegiatanSesi;
use App\Models\Mahasiswa;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AbsensiManualController extends Controller
{
    /**
     * Display manual attendance page for a specific sesi
     * Only accessible by garda for their assigned kompi
     */
    public function index($sesiId)
    {
        $user = Auth::user();
        $sesi = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->findOrFail($sesiId);
        
        // Get tanggal from PKKMB schedule or kegiatan
        $tanggal = $sesi->tanggal;
        
        if (!$tanggal) {
            abort(500, 'Tanggal tidak ditemukan untuk sesi ini');
        }
        
        // Check authorization
        if ($user->role === 'garda') {
            // Garda can only access if they have assigned_kompi
            if (!$user->assigned_kompi) {
                abort(403, 'Anda belum memiliki kompi yang ditugaskan');
            }
            
            // Get mahasiswa from assigned kompi only
            $mahasiswaQuery = Mahasiswa::where('is_active', 1)
                ->where('kompi', $user->assigned_kompi)
                ->orderBy('name');
        } else if (in_array($user->role, ['admin', 'timdis'])) {
            // Admin and timdis can access all mahasiswa
            $mahasiswaQuery = Mahasiswa::where('is_active', 1)
                ->orderBy('kompi')
                ->orderBy('name');
        } else {
            abort(403, 'Akses ditolak');
        }

        $mahasiswaList = $mahasiswaQuery->get();

        // Get existing attendance records for this sesi
        $attendances = Attendance::where('sesi_id', $sesiId)
            ->whereIn('mahasiswa_id', $mahasiswaList->pluck('id'))
            ->get()
            ->keyBy('mahasiswa_id');

        return view('admin.absensi-manual', compact('sesi', 'mahasiswaList', 'attendances'));
    }

    /**
     * Save manual attendance (bulk)
     */
    public function store(Request $request, $sesiId)
    {
        $user = Auth::user();
        $sesi = KegiatanSesi::with('kegiatan')->findOrFail($sesiId);
        
        $validated = $request->validate([
            'hadir' => 'nullable|array',
            'hadir.*' => 'exists:mahasiswa,id',
        ]);

        $hadirIds = $validated['hadir'] ?? [];
        
        // Verify authorization for each mahasiswa
        if ($user->role === 'garda') {
            if (!$user->assigned_kompi) {
                return redirect()->back()->with('error', 'Anda belum memiliki kompi yang ditugaskan');
            }
            
            // Verify all mahasiswa belong to garda's kompi
            $mahasiswaCheck = Mahasiswa::whereIn('id', $hadirIds)
                ->where('kompi', '!=', $user->assigned_kompi)
                ->exists();
            
            if ($mahasiswaCheck) {
                return redirect()->back()->with('error', 'Anda hanya bisa mengabsen mahasiswa dari kompi Anda');
            }
        }

        DB::beginTransaction();
        try {
            $now = Carbon::now();
            
            // Get date from sesi
            $date = $sesi->tanggal ? $sesi->tanggal->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            
            // Get all eligible mahasiswa for this user
            if ($user->role === 'garda') {
                $allMahasiswaIds = Mahasiswa::where('is_active', 1)
                    ->where('kompi', $user->assigned_kompi)
                    ->pluck('id')
                    ->toArray();
            } else {
                $allMahasiswaIds = Mahasiswa::where('is_active', 1)
                    ->pluck('id')
                    ->toArray();
            }
            
            // Delete existing attendance for this sesi (for eligible mahasiswa only)
            Attendance::where('sesi_id', $sesiId)
                ->whereIn('mahasiswa_id', $allMahasiswaIds)
                ->delete();
            
            // Create attendance records for hadir mahasiswa
            $attendanceData = [];
            foreach ($hadirIds as $mahasiswaId) {
                $attendanceData[] = [
                    'mahasiswa_id' => $mahasiswaId,
                    'kegiatan_id' => $sesi->kegiatan_id, // nullable
                    'sesi_id' => $sesiId,
                    'date' => $date,
                    'status' => 'present',
                    'check_in' => $now,
                    'absen_by' => $user->username,
                    'absen_at' => $now,
                    'created_at' => $now,
                ];
            }
            
            if (!empty($attendanceData)) {
                Attendance::insert($attendanceData);
            }
            
            DB::commit();
            
            $totalHadir = count($hadirIds);
            return redirect()->route('admin.absensi-manual.index', $sesiId)
                ->with('success', "Absensi berhasil disimpan. Total hadir: {$totalHadir} mahasiswa");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.absensi-manual.index', $sesiId)
                ->with('error', 'Gagal menyimpan absensi: ' . $e->getMessage());
        }
    }

    /**
     * View monitoring/rekap for a specific sesi
     */
    public function monitoring($sesiId)
    {
        $user = Auth::user();
        $sesi = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->findOrFail($sesiId);
        
        // Get mahasiswa based on role
        if ($user->role === 'garda' && $user->assigned_kompi) {
            $mahasiswaQuery = Mahasiswa::where('is_active', 1)
                ->where('kompi', $user->assigned_kompi);
        } else {
            $mahasiswaQuery = Mahasiswa::where('is_active', 1);
        }
        
        $mahasiswaList = $mahasiswaQuery->orderBy('kompi')->orderBy('name')->get();
        
        // Get attendance records
        $attendances = Attendance::where('sesi_id', $sesiId)
            ->whereIn('mahasiswa_id', $mahasiswaList->pluck('id'))
            ->get()
            ->keyBy('mahasiswa_id');
        
        $totalHadir = $attendances->count();
        $totalMahasiswa = $mahasiswaList->count();
        
        return view('admin.monitoring-sesi', compact('sesi', 'mahasiswaList', 'attendances', 'totalHadir', 'totalMahasiswa'));
    }
}
