<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Mahasiswa;
use App\Models\IzinSubmission;
use App\Models\KehadiranSubmission;
use App\Models\CameraStream;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; // <-- untuk query builder relasi tabel

class AdminController extends Controller
{
    public function dashboard_admin()
    {
        $today = Carbon::today();

        // =========================
        // STATISTIK DASHBOARD
        // =========================

        $totalMahasiswaAktif = Mahasiswa::where('is_active', true)->count();

        $totalMahasiswaNonAktif = Mahasiswa::where('is_active', false)->count();

        $mahasiswaHadirHariIni = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('mahasiswa.is_active', 1)
            ->whereDate('attendance.created_at', $today)
            ->distinct('attendance.mahasiswa_id')
            ->count('attendance.mahasiswa_id');

        $mahasiswaTidakHadir = $totalMahasiswaAktif - $mahasiswaHadirHariIni;

        $persentaseKehadiran = $totalMahasiswaAktif > 0
            ? round(($mahasiswaHadirHariIni / $totalMahasiswaAktif) * 100)
            : 0;

        $mahasiswaMasihDiKantor = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('mahasiswa.is_active', 1)
            ->whereDate('attendance.created_at', $today)
            ->whereNull('attendance.check_out')
            ->distinct('attendance.mahasiswa_id')
            ->count('attendance.mahasiswa_id');

        // =========================
        // ABSENSI TERKINI
        // =========================

        $absensiTerkini = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->select(
                'mahasiswa.name',
                'attendance.created_at',
                'attendance.check_out',
                'attendance.status'
            )
            ->whereDate('attendance.created_at', $today)
            ->orderBy('attendance.created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {

                $masuk = Carbon::parse($item->created_at);

                $item->jam_masuk = $masuk->format('H:i');

                $item->jam_keluar = $item->check_out
                    ? Carbon::parse($item->check_out)->format('H:i')
                    : '-';

                // STATUS
                $item->status_label = 'Di Kantor';
                $item->status_color = 'var(--warning)';

                if ($item->status === 'izin') {
                    $item->status_label = 'Izin';
                } elseif ($item->status === 'sakit') {
                    $item->status_label = 'Sakit';
                } elseif ($item->check_out) {
                    $item->status_label = 'Selesai';
                    $item->status_color = 'var(--success)';
                }

                return $item;
            });

        // =========================
        // TREN 7 HARI
        // =========================

        $tren7Hari = [];

        for ($i = 6; $i >= 0; $i--) {

            $date = Carbon::today()->subDays($i);

            $count = DB::table('attendance')
                ->whereDate('created_at', $date)
                ->distinct('mahasiswa_id')
                ->count('mahasiswa_id');

            $tren7Hari[] = [
                'tanggal' => $date->format('d M'),
                'jumlah' => $count
            ];
        }

        // =========================
        // PER KELOMPOK
        // =========================

        $perKelompok = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.created_at', $today)
            ->select(
                'mahasiswa.kelompok',
                DB::raw('count(DISTINCT attendance.mahasiswa_id) as total')
            )
            ->groupBy('mahasiswa.kelompok')
            ->orderBy('total', 'desc')
            ->get();

        // =========================
        // ABSENSI HARI INI
        // =========================

        $seluruhAbsensiHariIni = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->select(
                'mahasiswa.name',
                'mahasiswa.kelompok',
                'attendance.created_at',
                'attendance.check_out',
                'attendance.status'
            )
            ->whereDate('attendance.created_at', $today)
            ->orderBy('attendance.created_at', 'desc')
            ->get()
            ->map(function ($item) {

                $masuk = Carbon::parse($item->created_at);

                $keluar = $item->check_out
                    ? Carbon::parse($item->check_out)
                    : null;

                $item->jam_masuk = $masuk->format('H:i:s');

                $item->jam_keluar = $keluar
                    ? $keluar->format('H:i:s')
                    : '-';

                $item->durasi = $keluar
                    ? floor($masuk->diffInHours($keluar)) . ' jam ' .
                    $masuk->diff($keluar)->format('%I') . ' menit'
                    : '-';

                // STATUS
                $item->status_label = 'Di Kantor';
                $item->status_color = 'var(--warning)';

                if ($item->status === 'izin') {
                    $item->status_label = 'Izin';
                } elseif ($item->status === 'sakit') {
                    $item->status_label = 'Sakit';
                } elseif ($item->check_out) {
                    $item->status_label = 'Selesai';
                    $item->status_color = 'var(--success)';
                }

                return $item;
            });

        // =========================
        // DATA MAHASISWA
        // =========================

        $mahasiswas = Mahasiswa::orderBy('name', 'asc')->get();

        // FILTER OPTION
        $kelompokList = Mahasiswa::whereNotNull('kelompok')
            ->distinct()
            ->pluck('kelompok');

        $jurusanList = Mahasiswa::whereNotNull('jurusan')
            ->distinct()
            ->pluck('jurusan');

        // =========================
        // RIWAYAT ABSENSI
        // =========================

        $riwayatAbsensi = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->select(
                'mahasiswa.name',
                'mahasiswa.kelompok',
                'attendance.created_at',
                'attendance.check_out',
                'attendance.status'
            )
            ->orderBy('attendance.created_at', 'desc')
            ->get()
            ->map(function ($item) {

                $masuk = Carbon::parse($item->created_at);

                $keluar = $item->check_out
                    ? Carbon::parse($item->check_out)
                    : null;

                $item->tanggal = $masuk->translatedFormat('d M Y');

                $item->jam_masuk = $masuk->format('H:i:s');

                $item->jam_keluar = $keluar
                    ? $keluar->format('H:i:s')
                    : '-';

                $item->durasi = $keluar
                    ? floor($masuk->diffInHours($keluar)) . ' jam ' .
                    $masuk->diff($keluar)->format('%I') . ' menit'
                    : '-';

                // STATUS
                $item->status_label = 'Di Kantor';
                $item->status_color = 'var(--warning)';
                $item->tanggal_raw = $masuk->format('Y-m-d');

                if ($item->status === 'izin') {
                    $item->status_label = 'Izin';
                } elseif ($item->status === 'sakit') {
                    $item->status_label = 'Sakit';
                } elseif ($item->check_out) {
                    $item->status_label = 'Selesai';
                    $item->status_color = 'var(--success)';
                }

                return $item;
            });

        // =========================
        // IZIN / SAKIT
        // =========================

        $izinSubmissions = IzinSubmission::with('mahasiswa')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalPendingIzin = $izinSubmissions
            ->where('status', 'pending')
            ->count();

        $totalApprovedIzin = $izinSubmissions
            ->where('status', 'approved')
            ->count();

        $totalRejectedIzin = $izinSubmissions
            ->where('status', 'rejected')
            ->count();

        // =========================
        // RETURN VIEW
        // =========================

        return view('admin.dashboard', compact(
            'totalMahasiswaAktif',
            'totalMahasiswaNonAktif',
            'mahasiswaHadirHariIni',
            'mahasiswaTidakHadir',
            'persentaseKehadiran',
            'mahasiswaMasihDiKantor',
            'absensiTerkini',
            'tren7Hari',
            'perKelompok',
            'seluruhAbsensiHariIni',
            'mahasiswas',
            'riwayatAbsensi',
            'izinSubmissions',
            'totalPendingIzin',
            'totalApprovedIzin',
            'totalRejectedIzin',
            'kelompokList',
            'jurusanList'
        ));
    }

    public function uploadVideo(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4|max:102400', // Maks 100MB
            'action' => 'required|in:check_in,check_out'
        ]);

        try {
            if ($request->hasFile('video')) {
                $file = $request->file('video');
                $filename = time() . '_' . $file->getClientOriginalName();
                
                // Simpan file ke storage/app/public/videos
                $path = $file->storeAs('public/videos', $filename);

                // NOTE: Anda bisa menambahkan interaksi dengan Python API atau Flask di bagian ini

                return response()->json([
                    'success' => true,
                    'message' => 'Video berhasil diupload',
                    'file_path' => $path,
                    'action' => $request->action
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Tidak ada file yang diupload.'], 400);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function getIzinSubmissions(Request $request)
    {
        $query = IzinSubmission::join('mahasiswa', 'izin_submissions.mahasiswa_id', '=', 'mahasiswa.id')
            ->select('izin_submissions.*', 'mahasiswa.name as mahasiswa_name', 'mahasiswa.kelompok');

        if ($request->filled('status')) {
            $query->where('izin_submissions.status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('mahasiswa.name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('kelompok')) {
            $query->where('mahasiswa.kelompok', $request->kelompok);
        }

        $submissions = $query->orderBy('izin_submissions.created_at', 'desc')->get();

        $stats = [
            'pending' => IzinSubmission::where('status', 'pending')->count(),
            'approved' => IzinSubmission::where('status', 'approved')->count(),
            'rejected' => IzinSubmission::where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $submissions,
            'stats' => $stats
        ]);
    }

    public function verifyIzin(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:status,rejected'
        ]);

        try {
            $izin = IzinSubmission::findOrFail($id);
            $izin->status = $request->status;
            $izin->verified_by = auth()->user()->full_name ?? auth()->user()->username;
            $izin->verified_at = now();
            
            if ($request->status === 'rejected') {
                $izin->rejection_reason = $request->reject_reason;
            }

            $izin->save();

            if ($request->status === 'approved') {
                Attendance::updateOrCreate(
                    [
                        'mahasiswa_id' => $izin->mahasiswa_id,
                        'date' => $izin->date,
                    ],
                    [
                        'status' => $izin->submission_type,
                        'notes' => 'Diinput via Pengajuan Izin/Sakit'
                    ]
                );
            }

            return response()->json(['success' => true, 'message' => 'Status pengajuan berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function getKehadiranSubmissions(Request $request)
    {
        $query = KehadiranSubmission::join('mahasiswa', 'kehadiran_submissions.mahasiswa_id', '=', 'mahasiswa.id')
            ->select('kehadiran_submissions.*', 'mahasiswa.name as mahasiswa_name', 'mahasiswa.kelompok');

        if ($request->filled('status')) {
            $query->where('kehadiran_submissions.status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('mahasiswa.name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('kelompok')) {
            $query->where('mahasiswa.kelompok', $request->kelompok);
        }

        $submissions = $query->orderBy('kehadiran_submissions.created_at', 'desc')->get();

        $stats = [
            'pending' => KehadiranSubmission::where('status', 'pending')->count(),
            'approved' => KehadiranSubmission::where('status', 'approved')->count(),
            'rejected' => KehadiranSubmission::where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $submissions,
            'stats' => $stats
        ]);
    }

    public function verifyKehadiran(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:status,rejected'
        ]);

        try {
            $kehadiran = KehadiranSubmission::findOrFail($id);
            $kehadiran->status = $request->status;
            $kehadiran->verified_by = auth()->user()->full_name ?? auth()->user()->username;
            $kehadiran->verified_at = now();
            
            if ($request->status === 'rejected') {
                $kehadiran->rejection_reason = $request->reject_reason;
            }

            $kehadiran->save();

            if ($request->status === 'approved') {
                Attendance::updateOrCreate(
                    [
                        'mahasiswa_id' => $kehadiran->mahasiswa_id,
                        'date' => $kehadiran->date,
                    ],
                    [
                        'check_in' => $kehadiran->date . ' ' . $kehadiran->check_in_time,
                        'check_out' => $kehadiran->check_out_time ? $kehadiran->date . ' ' . $kehadiran->check_out_time : null,
                        'status' => 'hadir',
                        'notes' => 'Diinput via Pengajuan Kehadiran'
                    ]
                );
            }

            return response()->json(['success' => true, 'message' => 'Status pengajuan kehadiran berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function getUsers(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('username', 'like', '%' . $request->search . '%')
                  ->orWhere('full_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        $stats = [
            'admin' => User::where('role', 'admin')->count(),
            'timdis' => User::where('role', 'timdis')->count(),
            'mahasiswa' => User::where('role', 'mahasiswa')->count(),
            'total' => User::count(),
        ];

        return response()->json(['success' => true, 'data' => $users, 'stats' => $stats]);
    }

    public function getMahasiswaOptions()
    {
        $usedIds = User::whereNotNull('mahasiswa_id')->pluck('mahasiswa_id');
        $mahasiswas = Mahasiswa::whereNotIn('id', $usedIds)->select('id', 'name', 'kelompok')->get();
        
        return response()->json(['success' => true, 'data' => $mahasiswas]);
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
            'full_name' => 'required|string',
            'role' => 'required|in:admin,timdis,mahasiswa',
            'mahasiswa_id' => 'required_if:role,mahasiswa',
        ]);

        try {
            User::create([
                'username' => $request->username,
                'password_hash' => Hash::make($request->password), // model User Anda menggunakan password_hash
                'full_name' => $request->full_name,
                'email' => $request->email,
                'role' => $request->role,
                'mahasiswa_id' => $request->role === 'mahasiswa' ? $request->mahasiswa_id : null,
                'is_active' => true,
            ]);
            return response()->json(['success' => true, 'message' => 'User berhasil ditambahkan.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
        }
    }

    public function updateUser(Request $request, $id)
    {
        $request->validate([
            'username' => 'required|string|unique:users,username,' . $id,
            'full_name' => 'required|string',
            'role' => 'required|in:admin,timdis,mahasiswa',
            'mahasiswa_id' => 'required_if:role,mahasiswa',
        ]);

        try {
            $user = User::findOrFail($id);
            $user->update([
                'username' => $request->username,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'role' => $request->role,
                'mahasiswa_id' => $request->role === 'mahasiswa' ? $request->mahasiswa_id : null,
            ]);
            return response()->json(['success' => true, 'message' => 'User berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
        }
    }

    public function resetUserPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed'
        ]);

        try {
            $user = User::findOrFail($id);
            $user->update([
                'password_hash' => Hash::make($request->password)
            ]);
            return response()->json(['success' => true, 'message' => 'Password berhasil direset.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
        }
    }

    public function toggleUserStatus(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->is_active = !$user->is_active;
            $user->save();
            return response()->json(['success' => true, 'message' => 'Status user berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
        }
    }

    public function destroyUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();
            return response()->json(['success' => true, 'message' => 'User berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
        }
    }
}