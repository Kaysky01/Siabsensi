<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KegiatanSesi;
use App\Models\Mahasiswa;
use App\Models\Attendance;
use App\Models\AttendanceSesi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AbsensiManualController extends Controller
{
    /**
     * List all sesi for absensi persesi
     */
    public function listSesi()
    {
        $user = Auth::user();
        
        // Get all active schedules with their sesi
        $schedules = \App\Models\PkkmbSchedule::with(['sesi' => function($query) {
            $query->where('is_active', 1)->orderBy('jam_mulai');
        }])
        ->where('is_active', 1)
        ->orderBy('tanggal')
        ->get();
        
        return view('admin.absensi-persesi', compact('schedules'));
    }
    
    /**
     * Display manual attendance page for a specific sesi
     * Only accessible by garda for their assigned kompi
     */
    public function index($sesiId)
    {
        $user = Auth::user();
        $sesi = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->findOrFail($sesiId);
        $search = trim((string) request('search', ''));
        
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

        if ($search !== '') {
            $mahasiswaQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhere('kompi', 'like', "%{$search}%")
                    ->orWhere('prodi', 'like', "%{$search}%");
            });
        }

        $filteredMahasiswaIds = (clone $mahasiswaQuery)->pluck('id');
        $dailyAttendances = Attendance::daily()
            ->where('date', $tanggal->format('Y-m-d'))
            ->whereNotNull('check_in')
            ->whereIn('mahasiswa_id', $filteredMahasiswaIds)
            ->get()
            ->keyBy('mahasiswa_id');

        // Get existing attendance records for this sesi
        $attendances = AttendanceSesi::where('sesi_id', $sesiId)
            ->whereIn('mahasiswa_id', $filteredMahasiswaIds)
            ->get()
            ->keyBy('mahasiswa_id');
        
        // Pagination
        $perPage = 20;
        $mahasiswaPaginated = (clone $mahasiswaQuery)
            ->paginate($perPage)
            ->withQueryString();
        $eligibleMahasiswaIds = $dailyAttendances->keys()->values()->all();

        return view('admin.absensi-manual', compact(
            'sesi',
            'mahasiswaPaginated',
            'attendances',
            'dailyAttendances',
            'eligibleMahasiswaIds',
            'search'
        ));
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
            'search' => 'nullable|string',
            'select_all_eligible' => 'nullable|boolean',
            'excluded_ids' => 'nullable|array',
            'excluded_ids.*' => 'exists:mahasiswa,id',
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

            $search = trim((string) ($validated['search'] ?? ''));
            $targetMahasiswaIds = $allMahasiswaIds;
            if ($search !== '') {
                $targetMahasiswaIds = Mahasiswa::where('is_active', 1)
                    ->when($user->role === 'garda', function ($query) use ($user) {
                        $query->where('kompi', $user->assigned_kompi);
                    })
                    ->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
                            ->orWhere('kompi', 'like', "%{$search}%")
                            ->orWhere('prodi', 'like', "%{$search}%");
                    })
                    ->pluck('id')
                    ->toArray();
            }

            $eligibleDailyAttendances = Attendance::daily()
                ->where('date', $date)
                ->whereNotNull('check_in')
                ->whereIn('mahasiswa_id', $targetMahasiswaIds)
                ->get()
                ->keyBy('mahasiswa_id');

            if ($request->boolean('select_all_eligible')) {
                $eligibleMahasiswaQuery = Mahasiswa::where('is_active', 1)
                    ->whereIn('id', $eligibleDailyAttendances->keys()->all());

                if ($user->role === 'garda') {
                    $eligibleMahasiswaQuery->where('kompi', $user->assigned_kompi);
                }

                if ($search !== '') {
                    $eligibleMahasiswaQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
                            ->orWhere('kompi', 'like', "%{$search}%")
                            ->orWhere('prodi', 'like', "%{$search}%");
                    });
                }

                $excludedIds = collect($validated['excluded_ids'] ?? []);
                $hadirIds = $eligibleMahasiswaQuery->pluck('id')
                    ->reject(fn ($id) => $excludedIds->contains($id))
                    ->values()
                    ->all();
            }

            $invalidMahasiswaIds = collect($hadirIds)
                ->reject(fn ($mahasiswaId) => $eligibleDailyAttendances->has($mahasiswaId))
                ->values();

            if ($invalidMahasiswaIds->isNotEmpty()) {
                throw new \RuntimeException('Terdapat mahasiswa yang belum absen masuk harian sehingga tidak dapat diabsen per sesi.');
            }
            
            // Delete existing attendance for this sesi (for eligible mahasiswa only)
            AttendanceSesi::where('sesi_id', $sesiId)
                ->whereIn('mahasiswa_id', $targetMahasiswaIds)
                ->delete();
            
            // Create attendance records for hadir mahasiswa
            $attendanceData = [];
            foreach ($hadirIds as $mahasiswaId) {
                $attendanceData[] = [
                    'attendance_id' => $eligibleDailyAttendances[$mahasiswaId]->id,
                    'mahasiswa_id' => $mahasiswaId,
                    'status' => 'present',
                    'sesi_id' => $sesiId,
                    'absen_by' => $user->username,
                    'absen_at' => $now,
                    'created_at' => $now,
                ];
            }
            
            if (!empty($attendanceData)) {
                AttendanceSesi::insert($attendanceData);
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
        $search = trim((string) request('search', ''));
        $status = trim((string) request('status', ''));
        $kompi = trim((string) request('kompi', ''));
        
        // Get mahasiswa based on role
        if ($user->role === 'garda' && $user->assigned_kompi) {
            $mahasiswaQuery = Mahasiswa::where('is_active', 1)
                ->where('kompi', $user->assigned_kompi);
        } else {
            $mahasiswaQuery = Mahasiswa::where('is_active', 1);
        }

        $kompiOptions = (clone $mahasiswaQuery)
            ->select('kompi')
            ->distinct()
            ->orderBy('kompi')
            ->pluck('kompi');

        $tanggal = $sesi->tanggal ? $sesi->tanggal->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $dailyAttendances = Attendance::daily()
            ->where('date', $tanggal)
            ->whereNotNull('check_in')
            ->get()
            ->keyBy('mahasiswa_id');

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
            $mahasiswaQuery->whereIn('id', $dailyAttendances->keys()->diff($attendances->keys())->all());
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
        
        return view('admin.monitoring-sesi', compact(
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
