<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSesi;
use App\Models\KegiatanSesi;
use App\Models\Mahasiswa;
use App\Models\PkkmbSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

    
class AttendanceController extends Controller
{
    public function listSessions()
    {
        $user = Auth::user();
        $assignedKompi = $user->assigned_kompi;

        $schedules = \App\Models\PkkmbSchedule::with(['sesi' => function($query) {
            $query->where('is_active', 1)->orderBy('jam_mulai');
        }])
        ->where('is_active', 1)
        ->orderBy('tanggal')
        ->get();

        $kompiOptions = \App\Models\Kompi::pluck('nama')->sort()->values();
        $filterKompi = request()->query('kompi', $assignedKompi ?? 'all');
        
        return view('garda.absensi-persesi', compact('schedules', 'kompiOptions', 'filterKompi'));
    }

    public function manualAttendance($sesiId)
    {
        $user = Auth::user();
        $search = trim((string) request('search', ''));
        
        if (!$user->assigned_kompi) {
            return redirect()->route('garda.dashboard')->with('error', 'Kompi belum ditugaskan');
        }

        $sesi = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->findOrFail($sesiId);
        
        $tanggal = $sesi->tanggal;
        if (!$tanggal) {
            abort(500, 'Tanggal tidak ditemukan untuk sesi ini');
        }

        $mahasiswaQuery = Mahasiswa::where('is_active', 1)
            ->where('kompi', $user->assigned_kompi)
            ->orderBy('name');

        if ($search !== '') {
            $mahasiswaQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
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

        $attendances = AttendanceSesi::where('sesi_id', $sesiId)
            ->whereIn('mahasiswa_id', $filteredMahasiswaIds)
            ->get()
            ->keyBy('mahasiswa_id');
        
        $mahasiswaPaginated = (clone $mahasiswaQuery)
            ->paginate(20)
            ->withQueryString();
        $eligibleMahasiswaIds = $dailyAttendances->keys()->values()->all();

        return view('garda.absen-kegiatan', compact(
            'sesi',
            'mahasiswaPaginated',
            'attendances',
            'dailyAttendances',
            'eligibleMahasiswaIds',
            'search'
        ));
    }

    public function absenKegiatan($sesiId)
    {
        $user = Auth::user();
        $search = trim((string) request('search', ''));
        
        if (!$user->assigned_kompi) {
            return redirect()->route('garda.dashboard')->with('error', 'Kompi belum ditugaskan');
        }

        $sesi = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->findOrFail($sesiId);

        $tanggal = $sesi->tanggal;
        if (!$tanggal) {
            abort(500, 'Tanggal tidak ditemukan untuk sesi ini');
        }

        $mahasiswaQuery = Mahasiswa::where('is_active', 1)
            ->where('kompi', $user->assigned_kompi)
            ->orderBy('name');

        if ($search !== '') {
            $mahasiswaQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
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

        return view('garda.absen-kegiatan', compact(
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

    public function store(Request $request, $sesiId)
    {
        $user = Auth::user();
        
        if (!$user->assigned_kompi) {
            return redirect()->back()->with('error', 'Kompi belum ditugaskan');
        }

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

        $mahasiswaQuery = Mahasiswa::where('is_active', 1)
            ->where('kompi', $user->assigned_kompi);

        if ($search !== '') {
            $mahasiswaQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
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
                    $statusVal = $statuses[$mahasiswaId];
                } elseif ($bulkAction && in_array($bulkAction, ['present', 'alpha', 'izin', 'sakit'])) {
                    $statusVal = $bulkAction;
                } elseif (isset($existingSesiRecords[$mahasiswaId])) {
                    $statusVal = $existingSesiRecords[$mahasiswaId]->status;
                } else {
                    $statusVal = $isEligible ? 'present' : 'alpha';
                }

                // If student has no daily check-in, force status to alpha
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

            $msg = "Absensi berhasil disimpan untuk total " . count($allMahasiswaIds) . " mahasiswa kompi Anda. (Hadir: {$countHadir}, Alpha: {$countAlpha}, Izin: {$countIzin}, Sakit: {$countSakit})";
            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Gagal menyimpan absensi: ' . $e->getMessage());
        }
    }

    /**
     * Garda dapat menambahkan sesi baru ke jadwal PKKMB yang aktif
     */
    public function tambahSesi(Request $request)
    {
        $request->validate([
            'pkkmb_schedule_id' => 'required|exists:pkkmb_schedules,id',
            'nama_sesi'         => 'required|string|max:255',
            'jam_mulai'         => 'nullable|date_format:H:i',
            'jam_selesai'       => 'nullable|date_format:H:i|after:jam_mulai',
        ], [
            'pkkmb_schedule_id.required' => 'Jadwal PKKMB wajib dipilih',
            'pkkmb_schedule_id.exists'   => 'Jadwal PKKMB tidak valid',
            'nama_sesi.required'         => 'Nama sesi wajib diisi',
            'jam_selesai.after'          => 'Jam selesai harus setelah jam mulai',
        ]);

        try {
            KegiatanSesi::create([
                'pkkmb_schedule_id' => $request->pkkmb_schedule_id,
                'nama_sesi'         => $request->nama_sesi,
                'jam_mulai'         => $request->jam_mulai,
                'jam_selesai'       => $request->jam_selesai,
                'is_active'         => 1,
                'kegiatan_id'       => null,
            ]);

            return redirect()->route('garda.absensi-persesi')
                ->with('success', 'Sesi "' . $request->nama_sesi . '" berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->route('garda.absensi-persesi')
                ->with('error', 'Gagal menambahkan sesi: ' . $e->getMessage());
        }
    }
}
