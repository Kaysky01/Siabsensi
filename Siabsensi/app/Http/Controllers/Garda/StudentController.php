<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Kegiatan;
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

        $allKegiatan = Kegiatan::orderBy('tanggal_pelaksanaan')->get();
        $allSesi = \App\Models\KegiatanSesi::with(['kegiatan', 'pkkmbSchedule'])->orderBy('created_at', 'asc')->get();
        $mahasiswaList = $query->with(['attendances', 'sessionAttendances'])->orderBy('name')->paginate(20)->withQueryString();

        return view('garda.mahasiswa-saya', compact('mahasiswaList', 'allKegiatan', 'allSesi'));
    }
}
