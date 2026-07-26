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
     * Menggunakan data yang sama dengan admin (absensi hari ini).
     */
    public function dashboard()
    {
        $today = Carbon::today()->toDateString();
        $totalMahasiswa = Mahasiswa::where('is_active', 1)->count();
        
        $attendancesToday = Attendance::where('date', $today)->get();
        $presentToday = $attendancesToday->whereIn('status', ['hadir', 'present'])->count();
        $izinSakit = $attendancesToday->whereIn('status', ['izin', 'sakit'])->count();
        $absent = $totalMahasiswa - $presentToday - $izinSakit;
        
        $stillIn = $attendancesToday->whereIn('status', ['hadir', 'present'])
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->count();
            
        $pct = $totalMahasiswa > 0 ? round(($presentToday / $totalMahasiswa) * 100, 1) : 0;
        
        // Data untuk tabel recent (8 terakhir check-in hari ini, disamakan dengan Garda)
        $recent = Attendance::with('sesi')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.date', $today)
            ->orderBy('attendance.check_in', 'desc')
            ->select('attendance.*', 'mahasiswa.name', 'mahasiswa.kompi')
            ->take(8)
            ->get();
            
        // Dummy trend data untuk chart
        // Tren 7 Hari terakhir
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $count = Attendance::where('date', $d->toDateString())
                ->whereIn('status', ['hadir', 'present'])
                ->count();
            $trend[] = [
                'date' => $d->format('d/m'),
                'count' => $count
            ];
        }

        // Kehadiran by Kompi
        $byKompi = Mahasiswa::select('kompi', DB::raw('count(*) as count'))
            ->groupBy('kompi')
            ->get();
        $maxKompi = $byKompi->max('count') ?: 1;

        return view('timdis.dashboard', compact(
            'totalMahasiswa', 'presentToday', 'absent', 'stillIn', 'pct',
            'recent', 'trend', 'byKompi', 'maxKompi'
        ));
    }

    /**
     * Halaman Izin & Sakit (Timdis)
     */
    public function izinTimdis(Request $request)
    {
        $izinTable = (new IzinSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();
        $filterStatus = $request->get('status', '');
        $searchQuery = $request->get('search', '');

        $query = IzinSubmission::join($mhsTable, "$izinTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$izinTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$izinTable.created_at", 'desc');

        if ($filterStatus) {
            $query->where("$izinTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where("$mhsTable.name", 'like', "%{$searchQuery}%");
        }

        $submissions = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => IzinSubmission::where('status', 'pending')->count(),
            'approved' => IzinSubmission::where('status', 'approved')->count(),
            'rejected' => IzinSubmission::where('status', 'rejected')->count(),
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
        $filterStatus = $request->get('status', '');
        $searchQuery = $request->get('search', '');

        $query = KehadiranSubmission::join($mhsTable, "$khdTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$khdTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$khdTable.created_at", 'desc');

        if ($filterStatus) {
            $query->where("$khdTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where("$mhsTable.name", 'like', "%{$searchQuery}%");
        }

        $submissions = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => KehadiranSubmission::where('status', 'pending')->count(),
            'approved' => KehadiranSubmission::where('status', 'approved')->count(),
            'rejected' => KehadiranSubmission::where('status', 'rejected')->count(),
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
            'reject_reason' => 'nullable|string',
        ]);

        $submission = KehadiranSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

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
            $submission->rejection_reason = $validated['reject_reason'];
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
