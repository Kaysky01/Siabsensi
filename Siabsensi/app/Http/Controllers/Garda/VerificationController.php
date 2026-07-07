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

        $filterStatus = $request->get('status', '');
        $query = IzinSubmission::with('mahasiswa')
            ->whereHas('mahasiswa', fn($q) => $q->where('kompi', $user->assigned_kompi))
            ->orderBy('created_at', 'desc');

        if ($filterStatus) {
            $query->where('status', $filterStatus);
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
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = IzinSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        if ($submission->mahasiswa->kompi !== $user->assigned_kompi) {
            return redirect()->back()->with('error', 'Anda hanya bisa memverifikasi pengajuan dari kompi Anda.');
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

        $filterStatus = $request->get('status', '');
        $query = KehadiranSubmission::with('mahasiswa')
            ->whereHas('mahasiswa', fn($q) => $q->where('kompi', $user->assigned_kompi))
            ->orderBy('created_at', 'desc');

        if ($filterStatus) {
            $query->where('status', $filterStatus);
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
            'action' => 'required|in:approve,reject',
            'reject_reason' => 'nullable|string',
        ]);

        $submission = KehadiranSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        if ($submission->mahasiswa->kompi !== $user->assigned_kompi) {
            return redirect()->back()->with('error', 'Anda hanya bisa memverifikasi kehadiran dari kompi Anda.');
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
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
        return redirect()->route('garda.kehadiran-manual')->with('success', $msg);
    }
}
