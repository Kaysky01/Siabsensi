<?php

namespace App\Http\Controllers\Garda;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSesi;
use App\Models\KompiAnnouncement;
use App\Models\Mahasiswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $statusFilter = trim((string) $request->input('status', ''));

        // 1. Full Unfiltered Kompi Students (include is_active NULL and 1, exclude only 0)
        $allMhsInKompi = Mahasiswa::where('kompi', $kompi)
            ->where(function ($q) {
                $q->where('is_active', '!=', 0)->orWhereNull('is_active');
            })
            ->get();

        $allMahasiswaIds = $allMhsInKompi->pluck('id')->toArray();
        $totalMahasiswa = count($allMahasiswaIds);

        // 2. Breakdown stats per Jurusan & Prodi
        $jurusanSummary = Mahasiswa::where('kompi', $kompi)
            ->where(function ($q) { $q->where('is_active', '!=', 0)->orWhereNull('is_active'); })
            ->whereNotNull('jurusan')
            ->where('jurusan', '!=', '')
            ->select('jurusan', DB::raw('count(*) as count'))
            ->groupBy('jurusan')
            ->orderBy('jurusan')
            ->get();

        $prodiSummary = Mahasiswa::where('kompi', $kompi)
            ->where(function ($q) { $q->where('is_active', '!=', 0)->orWhereNull('is_active'); })
            ->whereNotNull('prodi')
            ->where('prodi', '!=', '')
            ->select('prodi', DB::raw('count(*) as count'))
            ->groupBy('prodi')
            ->orderBy('prodi')
            ->get();

        // 3. Daily Attendance stats today (any attendance today, including kegiatan-based)
        $today = Carbon::today()->format('Y-m-d');
        $dailyCheckInsToday = Attendance::where('date', $today)
            ->whereNotNull('check_in')
            ->whereIn('mahasiswa_id', $allMahasiswaIds)
            ->distinct()
            ->pluck('mahasiswa_id')
            ->toArray();

        $sudahAbsenMasukTodayCount = count(array_unique($dailyCheckInsToday));
        $belumAbsenMasukTodayCount = max(0, $totalMahasiswa - $sudahAbsenMasukTodayCount);

        // 4. Total Session Attendances Summary
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

        // 5. Query Mahasiswa for Paginated List
        $mahasiswaQuery = Mahasiswa::where('kompi', $kompi)
            ->where(function ($q) { $q->where('is_active', '!=', 0)->orWhereNull('is_active'); })
            ->orderBy('name');

        if ($search !== '') {
            $mahasiswaQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('prodi', 'like', "%{$search}%")
                  ->orWhere('jurusan', 'like', "%{$search}%");
            });
        }

        if ($statusFilter === 'absen') {
            $mahasiswaQuery->whereIn('id', $dailyCheckInsToday);
        } elseif ($statusFilter === 'belum_absen') {
            $mahasiswaQuery->whereNotIn('id', $dailyCheckInsToday);
        }

        $attendancesByMhs = $sessionAttendances->groupBy('mahasiswa_id');

        $perPageReq = request('per_page', '20');
        if ($perPageReq === 'all') {
            $perPage = max($mahasiswaQuery->count(), 1);
        } else {
            $perPage = (int) $perPageReq > 0 ? (int) $perPageReq : 20;
        }

        $mahasiswaPaginated = $mahasiswaQuery->paginate($perPage)->withQueryString();

        $announcement = KompiAnnouncement::where('kompi', $kompi)->first();
        $isReadOnly = false;

        return view('garda.kompi-saya', compact(
            'kompi',
            'totalMahasiswa',
            'sudahAbsenMasukTodayCount',
            'belumAbsenMasukTodayCount',
            'jurusanSummary',
            'prodiSummary',
            'totalSesiHadir',
            'totalSesiAlpha',
            'totalSesiIzin',
            'totalSesiSakit',
            'mahasiswaPaginated',
            'dailyCheckInsToday',
            'attendancesByMhs',
            'search',
            'statusFilter',
            'perPageReq',
            'announcement',
            'isReadOnly'
        ));
    }

    public function saveAnnouncement(Request $request)
    {
        $this->ensureTableExists();

        $user = Auth::user();
        if (!$user->assigned_kompi || $user->role !== 'garda') {
            return redirect()->back()->with('error', 'Hanya role Garda yang dapat mengubah pengumuman Kompi.');
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
