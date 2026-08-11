<?php

namespace App\Http\Controllers\Timdis;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mahasiswa;
use App\Models\Attendance;
use App\Models\IzinSubmission;
use App\Models\KehadiranSubmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TimdisController extends Controller
{
    /**
     * Tampilkan Dashboard Tim Disiplin.
     * Filter data sesuai dengan kompi yang diampu (assigned_kompi).
     */
    public function dashboard()
    {
        $today = Carbon::today()->toDateString();
        $assignedKompi = Auth::user()->assigned_kompi;

        $mhsQuery = Mahasiswa::where('is_active', 1);
        if ($assignedKompi) {
            $mhsQuery->where('kompi', $assignedKompi);
        }
        $totalMahasiswa = $mhsQuery->count();
        
        $attQuery = Attendance::join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('attendance.date', $today);
        if ($assignedKompi) {
            $attQuery->where('mahasiswa.kompi', $assignedKompi);
        }
        $attendancesToday = $attQuery->select('attendance.*', 'mahasiswa.kompi')->get();

        $presentToday = $attendancesToday->whereIn('status', ['hadir', 'present'])->count();
        $izinSakit = $attendancesToday->whereIn('status', ['izin', 'sakit'])->count();
        $absent = max(0, $totalMahasiswa - $presentToday - $izinSakit);
        
        $stillIn = $attendancesToday->whereIn('status', ['hadir', 'present'])
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->count();
            
        $pct = $totalMahasiswa > 0 ? round(($presentToday / $totalMahasiswa) * 100, 1) : 0;

        $pendingKehadiranQuery = KehadiranSubmission::join('mahasiswa', 'kehadiran_submissions.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('kehadiran_submissions.status', 'pending');
        $pendingIzinQuery = IzinSubmission::join('mahasiswa', 'izin_submissions.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('izin_submissions.status', 'pending');

        if ($assignedKompi) {
            $pendingKehadiranQuery->where('mahasiswa.kompi', $assignedKompi);
            $pendingIzinQuery->where('mahasiswa.kompi', $assignedKompi);
        }

        $pendingKehadiranCount = $pendingKehadiranQuery->count();
        $pendingIzinCount = $pendingIzinQuery->count();
        $totalPending = $pendingKehadiranCount + $pendingIzinCount;
        
        // Data untuk tabel recent (8 terakhir check-in hari ini)
        $recentQuery = Attendance::with('sesi')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.date', $today);
        if ($assignedKompi) {
            $recentQuery->where('mahasiswa.kompi', $assignedKompi);
        }
        $recent = $recentQuery->orderByRaw("GREATEST(COALESCE(attendance.check_out, '1970-01-01'), COALESCE(attendance.check_in, '1970-01-01')) DESC")
            ->select('attendance.*', 'mahasiswa.name', 'mahasiswa.kompi', 'mahasiswa.photo_path')
            ->take(8)
            ->get();
            
        // Tren 7 Hari terakhir
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $tQuery = Attendance::join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
                ->where('attendance.date', $d->toDateString())
                ->whereIn('attendance.status', ['hadir', 'present']);
            if ($assignedKompi) {
                $tQuery->where('mahasiswa.kompi', $assignedKompi);
            }
            $count = $tQuery->count();
            $trend[] = [
                'date' => $d->format('d/m'),
                'count' => $count
            ];
        }

        // Kehadiran by Kompi
        $byKompiQuery = Mahasiswa::select('kompi', DB::raw('count(*) as count'));
        if ($assignedKompi) {
            $byKompiQuery->where('kompi', $assignedKompi);
        }
        $byKompi = $byKompiQuery->groupBy('kompi')->get()
            ->sortBy(function ($item) {
                return (int) preg_replace('/[^0-9]/', '', $item->kompi ?? '');
            }, SORT_NUMERIC)
            ->values();
        $maxKompi = $byKompi->max('count') ?: 1;

        return view('timdis.dashboard', compact(
            'totalMahasiswa', 'presentToday', 'absent', 'stillIn', 'pct',
            'recent', 'trend', 'byKompi', 'maxKompi', 'assignedKompi',
            'pendingKehadiranCount', 'pendingIzinCount', 'totalPending'
        ));
    }

    /**
     * Halaman Izin & Sakit (Timdis)
     */
    public function izinTimdis(Request $request)
    {
        $izinTable = (new IzinSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();
        $assignedKompi = Auth::user()->assigned_kompi;
        $filterStatus = $request->get('status', '');
        $searchQuery = $request->get('search', '');

        $query = IzinSubmission::join($mhsTable, "$izinTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$izinTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$izinTable.created_at", 'desc');

        if ($assignedKompi) {
            $query->where("$mhsTable.kompi", $assignedKompi);
        }

        if ($filterStatus) {
            $query->where("$izinTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery, $mhsTable) {
                $q->where("$mhsTable.name", 'like', "%{$searchQuery}%")
                  ->orWhere("$mhsTable.id", 'like', "%{$searchQuery}%");
            });
        }

        $submissions = $query->paginate(20)->withQueryString();

        $statsQuery = IzinSubmission::join($mhsTable, "$izinTable.mahasiswa_id", '=', "$mhsTable.id");
        if ($assignedKompi) {
            $statsQuery->where("$mhsTable.kompi", $assignedKompi);
        }

        $stats = [
            'pending' => (clone $statsQuery)->where("$izinTable.status", 'pending')->count(),
            'approved' => (clone $statsQuery)->where("$izinTable.status", 'approved')->count(),
            'rejected' => (clone $statsQuery)->where("$izinTable.status", 'rejected')->count(),
        ];

        return view('timdis.izin-timdis', compact('submissions', 'stats', 'filterStatus'));
    }

    /**
     * Verifikasi Izin & Sakit (Timdis)
     */
    public function verifyIzin(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject,cancel',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = IzinSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        // Check if timdis is restricted to assigned_kompi
        $assignedKompi = Auth::user()->assigned_kompi;
        if ($assignedKompi && $submission->mahasiswa && $submission->mahasiswa->kompi !== $assignedKompi) {
            return redirect()->route('timdis.izin-timdis')->with('error', 'Anda tidak memiliki hak akses verifikasi untuk mahasiswa luar kompi.');
        }

        if ($validated['action'] === 'cancel') {
            $submission->status = 'pending';
            $submission->verified_by = null;
            $submission->verified_at = null;
            $submission->rejection_reason = null;
            $submission->save();

            // Jika dibatalkan, hapus dari tabel attendance bila sebelumnya disetujui
            Attendance::where('mahasiswa_id', $submission->mahasiswa_id)
                ->where('date', $submission->date)
                ->where('status', $submission->submission_type)
                ->delete();

            return redirect()->route('timdis.izin-timdis')->with('success', 'Verifikasi pengajuan berhasil dibatalkan (dikembalikan ke Menunggu).');
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $submission->verified_by = $user->username;
        $submission->verified_at = Carbon::now();
        if ($validated['action'] === 'reject') {
            $submission->rejection_reason = $validated['rejection_reason'];
        }
        $submission->save();

        if ($validated['action'] === 'approve') {
            Attendance::updateOrCreate(
                ['mahasiswa_id' => $submission->mahasiswa_id, 'date' => $submission->date],
                ['status' => $submission->submission_type]
            );
        }

        $msg = $validated['action'] === 'approve' ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.';
        return redirect()->route('timdis.izin-timdis')->with('success', $msg);
    }

    /**
     * Halaman Kehadiran Manual (Timdis)
     */
    public function kehadiranTimdis(Request $request)
    {
        $khdTable = (new KehadiranSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();
        $assignedKompi = Auth::user()->assigned_kompi;
        $filterStatus = $request->get('status', '');
        $searchQuery = $request->get('search', '');

        $query = KehadiranSubmission::join($mhsTable, "$khdTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$khdTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$khdTable.created_at", 'desc');

        if ($assignedKompi) {
            $query->where("$mhsTable.kompi", $assignedKompi);
        }

        if ($filterStatus) {
            $query->where("$khdTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery, $mhsTable) {
                $q->where("$mhsTable.name", 'like', "%{$searchQuery}%")
                  ->orWhere("$mhsTable.id", 'like', "%{$searchQuery}%");
            });
        }

        $submissions = $query->paginate(20)->withQueryString();

        $statsQuery = KehadiranSubmission::join($mhsTable, "$khdTable.mahasiswa_id", '=', "$mhsTable.id");
        if ($assignedKompi) {
            $statsQuery->where("$mhsTable.kompi", $assignedKompi);
        }

        $stats = [
            'pending' => (clone $statsQuery)->where("$khdTable.status", 'pending')->count(),
            'approved' => (clone $statsQuery)->where("$khdTable.status", 'approved')->count(),
            'rejected' => (clone $statsQuery)->where("$khdTable.status", 'rejected')->count(),
        ];

        return view('timdis.kehadiran-timdis', compact('submissions', 'stats', 'filterStatus'));
    }

    /**
     * Verifikasi Kehadiran Manual (Timdis)
     */
    public function verifyKehadiran(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject,cancel',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = KehadiranSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        // Check if timdis is restricted to assigned_kompi
        $assignedKompi = Auth::user()->assigned_kompi;
        if ($assignedKompi && $submission->mahasiswa && $submission->mahasiswa->kompi !== $assignedKompi) {
            return redirect()->route('timdis.kehadiran-timdis')->with('error', 'Anda tidak memiliki hak akses verifikasi untuk mahasiswa luar kompi.');
        }

        if ($validated['action'] === 'cancel') {
            $submission->status = 'pending';
            $submission->verified_by = null;
            $submission->verified_at = null;
            $submission->rejection_reason = null;
            $submission->save();

            // Hapus dari attendance jika sebelumnya disetujui
            $dateOnly = Carbon::parse($submission->date)->format('Y-m-d');
            Attendance::where('mahasiswa_id', $submission->mahasiswa_id)
                ->where('date', $dateOnly)
                ->where('status', 'present')
                ->delete();

            return redirect()->route('timdis.kehadiran-timdis')->with('success', 'Verifikasi kehadiran berhasil dibatalkan (dikembalikan ke Menunggu).');
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $submission->verified_by = $user->username;
        $submission->verified_at = Carbon::now();
        if ($validated['action'] === 'reject') {
            $submission->rejection_reason = $validated['rejection_reason'];
        }
        $submission->save();

        if ($validated['action'] === 'approve') {
            $dateOnly = Carbon::parse($submission->date)->format('Y-m-d');
            Attendance::updateOrCreate(
                ['mahasiswa_id' => $submission->mahasiswa_id, 'date' => $dateOnly],
                [
                    'check_in' => $dateOnly . ' ' . $submission->check_in_time,
                    'check_out' => $submission->check_out_time ? $dateOnly . ' ' . $submission->check_out_time : null,
                    'status' => 'present',
                ]
            );
        }

        $msg = $validated['action'] === 'approve' ? 'Kehadiran disetujui.' : 'Kehadiran ditolak.';
        return redirect()->route('timdis.kehadiran-timdis')->with('success', $msg);
    }
}

