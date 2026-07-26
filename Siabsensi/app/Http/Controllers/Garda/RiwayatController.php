<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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

        // Query tabel attendance (absensi harian via QR scan / kehadiran manual)
        $query = Attendance::join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('mahasiswa.kompi', $user->assigned_kompi)
            ->select(
                'attendance.*',
                'mahasiswa.name',
                'mahasiswa.kompi'
            );

        if ($selectedSchedule) {
            $schedule = PkkmbSchedule::find($selectedSchedule);
            if ($schedule) {
                // Filter berdasarkan tanggal jadwal PKKMB
                $query->whereDate('attendance.date', $schedule->tanggal->format('Y-m-d'));
            }
        } else {
            // Belum pilih jadwal: tampilkan kosong
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('status')) {
            $statusFilter = $request->status;
            // Status dari QR scan adalah 'hadir', form filter menggunakan 'present'
            if ($statusFilter === 'present') {
                $query->where('attendance.status', 'hadir');
            } else {
                $query->where('attendance.status', $statusFilter);
            }
        }

        $riwayat = $query->orderBy('attendance.check_in', 'desc')
            ->paginate(20)
            ->withQueryString();

        $statuses = ['present', 'izin', 'sakit', 'alpha'];

        return view('garda.riwayat', compact('riwayat', 'statuses', 'schedules', 'selectedSchedule'));
    }
}
