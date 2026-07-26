<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSesi;
use App\Models\PkkmbSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RiwayatController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (!$user->assigned_kompi) {
            return redirect()->route('garda.dashboard')->with('error', 'Kompi belum ditugaskan');
        }

        $schedules = PkkmbSchedule::where('is_active', 1)->orderBy('tanggal', 'desc')->get();
        $selectedSchedule = $request->get('schedule');

        // Gunakan tabel attendance_sesi (absensi persesi) sebagai riwayat utama
        $query = AttendanceSesi::join('mahasiswa', 'attendance_sesi.mahasiswa_id', '=', 'mahasiswa.id')
            ->join('kegiatan_sesi', 'attendance_sesi.sesi_id', '=', 'kegiatan_sesi.id')
            ->where('mahasiswa.kompi', $user->assigned_kompi)
            ->select(
                'attendance_sesi.*',
                'mahasiswa.name',
                'mahasiswa.kompi',
                'kegiatan_sesi.nama_sesi',
                'kegiatan_sesi.jam_mulai',
                'kegiatan_sesi.jam_selesai',
                'kegiatan_sesi.pkkmb_schedule_id',
                'kegiatan_sesi.created_at as sesi_created_at'
            )
            ->with(['sesi', 'mahasiswa', 'absenBy']);

        if ($selectedSchedule) {
            $schedule = PkkmbSchedule::find($selectedSchedule);
            if ($schedule) {
                $query->where('kegiatan_sesi.pkkmb_schedule_id', $schedule->id);
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('status')) {
            $query->where('attendance_sesi.status', $request->status);
        }

        $riwayat = $query->orderBy('attendance_sesi.created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $statuses = ['present', 'izin', 'sakit', 'alpha'];

        return view('garda.riwayat', compact('riwayat', 'statuses', 'schedules', 'selectedSchedule'));
    }
}
