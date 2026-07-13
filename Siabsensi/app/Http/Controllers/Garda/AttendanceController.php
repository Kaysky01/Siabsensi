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

    public function store(Request $request, $sesiId)
    {
        $user = Auth::user();
        
        if (!$user->assigned_kompi) {
            return redirect()->back()->with('error', 'Kompi belum ditugaskan');
        }

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

        $mahasiswaCheck = Mahasiswa::whereIn('id', $hadirIds)
            ->where('kompi', '!=', $user->assigned_kompi)
            ->exists();

        if ($mahasiswaCheck) {
            return redirect()->back()->with('error', 'Anda hanya bisa mengabsen mahasiswa dari kompi Anda');
        }

        DB::beginTransaction();
        try {
            $now = Carbon::now();
            $date = $sesi->tanggal ? $sesi->tanggal->format('Y-m-d') : Carbon::today()->format('Y-m-d');

            $allMahasiswaIds = Mahasiswa::where('is_active', 1)
                ->where('kompi', $user->assigned_kompi)
                ->pluck('id')
                ->toArray();

            $search = trim((string) ($validated['search'] ?? ''));
            $targetMahasiswaIds = $allMahasiswaIds;
            if ($search !== '') {
                $targetMahasiswaIds = Mahasiswa::where('is_active', 1)
                    ->where('kompi', $user->assigned_kompi)
                    ->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
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
                    ->where('kompi', $user->assigned_kompi)
                    ->whereIn('id', $eligibleDailyAttendances->keys()->all());

                if ($search !== '') {
                    $eligibleMahasiswaQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
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

            AttendanceSesi::where('sesi_id', $sesiId)
                ->whereIn('mahasiswa_id', $targetMahasiswaIds)
                ->delete();

            $attendanceData = [];
            foreach ($hadirIds as $mahasiswaId) {
                $attendanceData[] = [
                    'attendance_id' => $eligibleDailyAttendances[$mahasiswaId]->id,
                    'mahasiswa_id' => $mahasiswaId,
                    'sesi_id' => $sesiId,
                    'status' => 'present',
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
            return redirect()->route('garda.absensi-manual.index', $sesiId)
                ->with('success', "Absensi berhasil disimpan. Total hadir: {$totalHadir} mahasiswa");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('garda.absensi-manual.index', $sesiId)
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
