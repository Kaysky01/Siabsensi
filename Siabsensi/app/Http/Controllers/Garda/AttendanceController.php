<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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
        
        if (!$user->assigned_kompi) {
            return redirect()->route('garda.dashboard')->with('error', 'Kompi belum ditugaskan');
        }

        $sesi = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->findOrFail($sesiId);
        
        $tanggal = $sesi->tanggal;
        if (!$tanggal) {
            abort(500, 'Tanggal tidak ditemukan untuk sesi ini');
        }

        $mahasiswaList = Mahasiswa::where('is_active', 1)
            ->where('kompi', $user->assigned_kompi)
            ->orderBy('name')
            ->get();

        $attendances = Attendance::where('sesi_id', $sesiId)
            ->whereIn('mahasiswa_id', $mahasiswaList->pluck('id'))
            ->get()
            ->keyBy('mahasiswa_id');
        
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $mahasiswaPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $mahasiswaList->forPage($currentPage, $perPage),
            $mahasiswaList->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('garda.absen-kegiatan', compact('sesi', 'mahasiswaPaginated', 'attendances'));
    }

    public function absenKegiatan($sesiId)
    {
        $user = Auth::user();
        
        if (!$user->assigned_kompi) {
            return redirect()->route('garda.dashboard')->with('error', 'Kompi belum ditugaskan');
        }

        $sesi = KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->findOrFail($sesiId);

        $tanggal = $sesi->tanggal;
        if (!$tanggal) {
            abort(500, 'Tanggal tidak ditemukan untuk sesi ini');
        }

        $mahasiswaList = Mahasiswa::where('is_active', 1)
            ->where('kompi', $user->assigned_kompi)
            ->orderBy('name')
            ->get();

        $attendances = Attendance::where('sesi_id', $sesiId)
            ->whereIn('mahasiswa_id', $mahasiswaList->pluck('id'))
            ->get()
            ->keyBy('mahasiswa_id');

        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $mahasiswaPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $mahasiswaList->forPage($currentPage, $perPage),
            $mahasiswaList->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('garda.absen-kegiatan', compact('sesi', 'mahasiswaPaginated', 'attendances'));
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

            Attendance::where('sesi_id', $sesiId)
                ->whereIn('mahasiswa_id', $allMahasiswaIds)
                ->delete();

            $attendanceData = [];
            foreach ($hadirIds as $mahasiswaId) {
                $attendanceData[] = [
                    'mahasiswa_id' => $mahasiswaId,
                    'kegiatan_id' => $sesi->kegiatan_id,
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
