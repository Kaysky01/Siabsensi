<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSesi;
use App\Models\KegiatanSesi;
use App\Models\KompiAnnouncement;
use App\Models\Mahasiswa;
use App\Models\PkkmbSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class KompiSayaController extends Controller
{
    private function ensureTableExists()
    {
        if (!Schema::hasTable('kompi_announcements')) {
            Schema::create('kompi_announcements', function (Blueprint $table) {
                $table->id();
                $table->string('kompi')->index();
                $table->string('judul')->default('Pengumuman Garda Kompi');
                $table->text('pesan')->nullable();
                $table->string('link_wa')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function index(Request $request)
    {
        $this->ensureTableExists();

        $user = Auth::user();
        if (!$user->assigned_kompi) {
            return redirect()->route('garda.dashboard')->with('error', 'Anda belum memiliki kompi yang ditugaskan.');
        }

        $kompi = $user->assigned_kompi;
        $search = trim((string) $request->input('search', ''));

        // Query Mahasiswa in this Kompi
        $mahasiswaQuery = Mahasiswa::where('is_active', 1)
            ->where('kompi', $kompi)
            ->orderBy('name');

        if ($search !== '') {
            $mahasiswaQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('prodi', 'like', "%{$search}%");
            });
        }

        $allMahasiswaIds = (clone $mahasiswaQuery)->pluck('id');
        $totalMahasiswa = count($allMahasiswaIds);

        // Daily Attendance stats today
        $today = Carbon::today()->format('Y-m-d');
        $dailyCheckInsToday = Attendance::daily()
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->whereIn('mahasiswa_id', $allMahasiswaIds)
            ->pluck('mahasiswa_id')
            ->toArray();

        $sudahAbsenMasukTodayCount = count($dailyCheckInsToday);
        $belumAbsenMasukTodayCount = max(0, $totalMahasiswa - $sudahAbsenMasukTodayCount);

        // Total Session Attendances Summary across ALL sessions for students in this Kompi
        $sessionAttendances = AttendanceSesi::whereIn('mahasiswa_id', $allMahasiswaIds)->get();

        $totalSesiHadir = 0;
        $totalSesiAlpha = 0;
        $totalSesiIzin  = 0;
        $totalSesiSakit = 0;

        foreach ($sessionAttendances as $sa) {
            if (in_array($sa->status, ['present', 'hadir'])) $totalSesiHadir++;
            elseif ($sa->status === 'alpha') $totalSesiAlpha++;
            elseif ($sa->status === 'izin') $totalSesiIzin++;
            elseif ($sa->status === 'sakit') $totalSesiSakit++;
        }

        // Per-Mahasiswa Summary Calculations
        $attendancesByMhs = $sessionAttendances->groupBy('mahasiswa_id');

        $perPageReq = request('per_page', '20');
        if ($perPageReq === 'all') {
            $perPage = max($totalMahasiswa, 1);
        } else {
            $perPage = (int) $perPageReq > 0 ? (int) $perPageReq : 20;
        }

        $mahasiswaPaginated = (clone $mahasiswaQuery)
            ->paginate($perPage)
            ->withQueryString();

        $allSchedules = PkkmbSchedule::where('is_active', 1)->orderBy('tanggal')->get();
        $allSesi = KegiatanSesi::with('kegiatan')->where('is_active', 1)->get();

        // Get or Create Announcement / WA Link Popup settings for this Kompi
        $announcement = KompiAnnouncement::where('kompi', $kompi)->first();

        return view('garda.kompi-saya', compact(
            'kompi',
            'totalMahasiswa',
            'sudahAbsenMasukTodayCount',
            'belumAbsenMasukTodayCount',
            'totalSesiHadir',
            'totalSesiAlpha',
            'totalSesiIzin',
            'totalSesiSakit',
            'mahasiswaPaginated',
            'dailyCheckInsToday',
            'attendancesByMhs',
            'search',
            'perPageReq',
            'allSchedules',
            'allSesi',
            'announcement'
        ));
    }

    public function saveAnnouncement(Request $request)
    {
        $this->ensureTableExists();

        $user = Auth::user();
        if (!$user->assigned_kompi) {
            return redirect()->back()->with('error', 'Kompi belum ditugaskan.');
        }

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'pesan' => 'nullable|string',
            'link_wa' => 'nullable|url|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $announcement = KompiAnnouncement::firstOrNew(['kompi' => $user->assigned_kompi]);
        $announcement->judul = $validated['judul'];
        $announcement->pesan = $validated['pesan'] ?? null;
        $announcement->link_wa = $validated['link_wa'] ?? null;
        $announcement->is_active = $request->has('is_active') ? 1 : 0;
        $announcement->updated_by = $user->username;
        $announcement->save();

        return redirect()->back()->with('success', 'Pesan Pop-up & Link Group WA Kompi berhasil disimpan.');
    }
}
