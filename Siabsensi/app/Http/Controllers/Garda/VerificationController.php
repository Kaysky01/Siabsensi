<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\IzinSubmission;
use App\Models\KehadiranSubmission;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerificationController extends Controller
{
    public function izin(Request $request)
    {
        $user = Auth::user();

        if (!$user->assigned_kompi) {
            return redirect()->route('garda.dashboard')->with('error', 'Kompi belum ditugaskan');
        }

        $izinTable = (new IzinSubmission)->getTable();
        $mhsTable = (new \App\Models\Mahasiswa)->getTable();
        
        $filterStatus = $request->get('status', '');
        $searchQuery = $request->get('search', '');
        
        $query = IzinSubmission::join($mhsTable, "$izinTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$izinTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->where("$mhsTable.kompi", $user->assigned_kompi)
            ->orderBy("$izinTable.created_at", 'desc');

        if ($filterStatus) {
            $query->where("$izinTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where("$mhsTable.name", 'like', "%{$searchQuery}%");
        }

        $submissions = $query->paginate(20)->withQueryString();

        $statsQuery = IzinSubmission::whereHas('mahasiswa', fn($q) => $q->where('kompi', $user->assigned_kompi));
        $stats = [
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
        ];

        return view('garda.izin', compact('submissions', 'stats', 'filterStatus'));
    }

    public function verifyIzin(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject,cancel',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = IzinSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        if ($submission->mahasiswa->kompi !== $user->assigned_kompi) {
            return redirect()->back()->with('error', 'Anda hanya bisa memverifikasi pengajuan dari kompi Anda.');
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

            return redirect()->route('garda.izin')->with('success', 'Verifikasi pengajuan berhasil dibatalkan (dikembalikan ke Menunggu).');
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
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
        return redirect()->route('garda.izin')->with('success', $msg);
    }

    public function kehadiranManual(Request $request)
    {
        $user = Auth::user();

        if (!$user->assigned_kompi) {
            return redirect()->route('garda.dashboard')->with('error', 'Kompi belum ditugaskan');
        }

        $khdTable = (new KehadiranSubmission)->getTable();
        $mhsTable = (new \App\Models\Mahasiswa)->getTable();

        $filterStatus = $request->get('status', '');
        $searchQuery = $request->get('search', '');
        
        $query = KehadiranSubmission::join($mhsTable, "$khdTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$khdTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->where("$mhsTable.kompi", $user->assigned_kompi)
            ->orderBy("$khdTable.created_at", 'desc');

        if ($filterStatus) {
            $query->where("$khdTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where("$mhsTable.name", 'like', "%{$searchQuery}%");
        }

        $submissions = $query->paginate(20)->withQueryString();

        $statsQuery = KehadiranSubmission::whereHas('mahasiswa', fn($q) => $q->where('kompi', $user->assigned_kompi));
        $stats = [
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
        ];

        return view('garda.kehadiran-manual', compact('submissions', 'stats', 'filterStatus'));
    }

    public function verifyKehadiran(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject,cancel',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = KehadiranSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        if ($submission->mahasiswa->kompi !== $user->assigned_kompi) {
            return redirect()->back()->with('error', 'Anda hanya bisa memverifikasi kehadiran dari kompi Anda.');
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

            return redirect()->route('garda.kehadiran-manual')->with('success', 'Verifikasi kehadiran berhasil dibatalkan (dikembalikan ke Menunggu).');
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
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
        return redirect()->route('garda.kehadiran-manual')->with('success', $msg);
    }
}
