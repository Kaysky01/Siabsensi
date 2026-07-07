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

        $query = Attendance::join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('mahasiswa.kompi', $user->assigned_kompi)
            ->select('attendance.*', 'mahasiswa.name', 'mahasiswa.kompi');

        if ($selectedSchedule) {
            $schedule = PkkmbSchedule::find($selectedSchedule);
            if ($schedule) {
                $query->whereDate('attendance.date', $schedule->tanggal);
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('status')) {
            $query->where('attendance.status', $request->status);
        }

        $riwayat = $query->orderBy('attendance.date', 'desc')
            ->orderBy('attendance.check_in', 'desc')
            ->paginate(20)
            ->withQueryString();

        $statuses = ['present', 'izin', 'sakit', 'alpha'];

        return view('garda.riwayat', compact('riwayat', 'statuses', 'schedules', 'selectedSchedule'));
    }
}
