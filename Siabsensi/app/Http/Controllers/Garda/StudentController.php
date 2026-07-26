<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Attendance;
use App\Models\PkkmbSchedule;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    public function myStudents()
    {
        $user = Auth::user();
        $query = Mahasiswa::query();

        if ($user->assigned_kompi) {
            $query->where('kompi', $user->assigned_kompi);
        }

        // Ambil jadwal PKKMB aktif (per hari) sebagai acuan titik status
        $allSchedules = PkkmbSchedule::where('is_active', 1)
            ->orderBy('tanggal', 'asc')
            ->get();

        // Ambil semua mahasiswa beserta absensi hariannya
        $mahasiswaList = $query
            ->with(['attendances'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('garda.mahasiswa-saya', compact('mahasiswaList', 'allSchedules'));
    }
}
