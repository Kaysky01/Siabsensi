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
        $totalMahasiswaCount = count($filteredMahasiswaIds);

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
        
        // Calculate total summary counts across ALL students in scope
        $totalHadirCount = 0;
        $totalAlphaCount = 0;
        $totalIzinCount = 0;
        $totalSakitCount = 0;

        foreach ($filteredMahasiswaIds as $mId) {
            $isEligible = isset($dailyAttendances[$mId]);
            $rec = $attendances[$mId] ?? null;
            $st = $rec ? $rec->status : ($isEligible ? 'present' : 'alpha');
            if ($st === 'present' || $st === 'hadir') $totalHadirCount++;
            elseif ($st === 'alpha') $totalAlphaCount++;
            elseif ($st === 'izin') $totalIzinCount++;
            elseif ($st === 'sakit') $totalSakitCount++;
        }

        // Pagination / Per Page handling
        $perPageReq = request('per_page', '20');
        if ($perPageReq === 'all') {
            $perPage = max($totalMahasiswaCount, 1);
        } else {
            $perPage = (int) $perPageReq > 0 ? (int) $perPageReq : 20;
        }

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
            'search',
            'perPageReq',
            'totalMahasiswaCount',
            'totalHadirCount',
            'totalAlphaCount',
            'totalIzinCount',
            'totalSakitCount'
        ));
    }

    /**
     * Save manual attendance (bulk across all scope)
     */
    public function store(Request $request, $sesiId)
    {
        $user = Auth::user();
        $sesi = KegiatanSesi::with('kegiatan')->findOrFail($sesiId);
        
        $validated = $request->validate([
            'status' => 'nullable|array',
            'status.*' => 'required|string|in:present,alpha,izin,sakit',
            'bulk_action' => 'nullable|string|in:present,alpha,izin,sakit',
            'search' => 'nullable|string',
        ]);

        $statuses = $request->input('status', []);
        $bulkAction = $request->input('bulk_action');
        $search = trim((string) $request->input('search', ''));

        // Determine scope of mahasiswa to process
        if ($user->role === 'garda') {
            if (!$user->assigned_kompi) {
                return redirect()->back()->with('error', 'Anda belum memiliki kompi yang ditugaskan');
            }
            $mahasiswaQuery = Mahasiswa::where('is_active', 1)
                ->where('kompi', $user->assigned_kompi);
        } else if (in_array($user->role, ['admin', 'timdis'])) {
            $mahasiswaQuery = Mahasiswa::where('is_active', 1);
        } else {
            return redirect()->back()->with('error', 'Akses ditolak');
        }

        if ($search !== '') {
            $mahasiswaQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhere('kompi', 'like', "%{$search}%")
                    ->orWhere('prodi', 'like', "%{$search}%");
            });
        }

        $allMahasiswaIds = $mahasiswaQuery->pluck('id');

        DB::beginTransaction();
        try {
            $now = Carbon::now();
            $date = $sesi->tanggal ? $sesi->tanggal->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            
            $dailyAttendances = Attendance::daily()
                ->where('date', $date)
                ->whereNotNull('check_in')
                ->whereIn('mahasiswa_id', $allMahasiswaIds)
                ->get()
                ->keyBy('mahasiswa_id');

            $existingSesiRecords = AttendanceSesi::where('sesi_id', $sesiId)
                ->whereIn('mahasiswa_id', $allMahasiswaIds)
                ->get()
                ->keyBy('mahasiswa_id');

            $countHadir = 0;
            $countAlpha = 0;
            $countIzin = 0;
            $countSakit = 0;

            foreach ($allMahasiswaIds as $mahasiswaId) {
                $isEligible = isset($dailyAttendances[$mahasiswaId]);

                if (array_key_exists($mahasiswaId, $statuses)) {
                    // Explicitly submitted in form for this student
                    $statusVal = $statuses[$mahasiswaId];
                } elseif ($bulkAction && in_array($bulkAction, ['present', 'alpha', 'izin', 'sakit'])) {
                    // Bulk action applied for unsubmitted students
                    $statusVal = $bulkAction;
                } elseif (isset($existingSesiRecords[$mahasiswaId])) {
                    // Preserve existing record
                    $statusVal = $existingSesiRecords[$mahasiswaId]->status;
                } else {
                    // Default for new records
                    $statusVal = $isEligible ? 'present' : 'alpha';
                }

                // Rule: If student has no daily check-in, MUST be alpha
                if (!$isEligible) {
                    $statusVal = 'alpha';
                }

                if (!in_array($statusVal, ['present', 'alpha', 'izin', 'sakit'])) {
                    $statusVal = 'alpha';
                }

                if ($isEligible) {
                    $parentAttendanceId = $dailyAttendances[$mahasiswaId]->id;
                } else {
                    $parentAttendance = Attendance::firstOrCreate(
                        [
                            'mahasiswa_id' => $mahasiswaId,
                            'date'         => $date,
                        ],
                        [
                            'status' => 'alpha',
                        ]
                    );
                    $parentAttendanceId = $parentAttendance->id;
                }

                AttendanceSesi::updateOrCreate(
                    [
                        'sesi_id'      => $sesiId,
                        'mahasiswa_id' => $mahasiswaId,
                    ],
                    [
                        'attendance_id' => $parentAttendanceId,
                        'status'        => $statusVal,
                        'absen_by'      => $user->username,
                        'absen_at'      => $now,
                    ]
                );

                if ($statusVal === 'present') $countHadir++;
                elseif ($statusVal === 'alpha') $countAlpha++;
                elseif ($statusVal === 'izin') $countIzin++;
                elseif ($statusVal === 'sakit') $countSakit++;
            }
            
            DB::commit();
            
            $msg = "Absensi berhasil disimpan untuk total " . count($allMahasiswaIds) . " mahasiswa. (Hadir: {$countHadir}, Alpha: {$countAlpha}, Izin: {$countIzin}, Sakit: {$countSakit})";
            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
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
