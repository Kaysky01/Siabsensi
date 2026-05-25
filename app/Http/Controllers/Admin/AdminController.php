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

        // 1. Data Statistik Mahasiswa & Kehadiran
        $totalMahasiswaAktif = Mahasiswa::where('is_active', true)->count();
        $totalMahasiswaNonAktif = Mahasiswa::where('is_active', false)->count();

        // 2. Data Mahasiswa Hadir Hari Ini (Aktif & Sudah Absen)
        // (Catatan: Sesuaikan kolom 'created_at' menjadi 'check_in' jika nama kolom timestamp absen Anda berbeda)
        $mahasiswaHadirHariIni = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('mahasiswa.is_active', 1)
            ->whereDate('attendance.created_at', $today)
            ->distinct('attendance.mahasiswa_id')
            ->count('attendance.mahasiswa_id');
            
        $mahasiswaTidakHadir = $totalMahasiswaAktif - $mahasiswaHadirHariIni;

        // 3. Persentase Kehadiran
        $persentaseKehadiran = $totalMahasiswaAktif > 0 ? round(($mahasiswaHadirHariIni / $totalMahasiswaAktif) * 100) : 0;

        // 4. Mahasiswa Masih di Kantor (Belum absen keluar)
        $mahasiswaMasihDiKantor = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->where('mahasiswa.is_active', 1)
            ->whereDate('attendance.created_at', $today)
            ->whereNull('attendance.check_out') // Mencari yang belum absen keluar
            ->distinct('attendance.mahasiswa_id')
            ->count('attendance.mahasiswa_id');

        // 5. Absensi Terkini (5 data terbaru hari ini)
        $absensiTerkini = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->select('mahasiswa.name', 'attendance.created_at', 'attendance.check_out', 'attendance.status')
            ->whereDate('attendance.created_at', $today)
            ->orderBy('attendance.created_at', 'desc')
            ->limit(5)
            ->get();

        // 6. Tren Kehadiran 7 Hari Terakhir
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

        // 7. Kehadiran Per Kelompok (Hari Ini)
        $perKelompok = DB::table('attendance')
            ->join('mahasiswa', 'attendance.mahasiswa_id', '=', 'mahasiswa.id')
            ->whereDate('attendance.created_at', $today)
            ->select('mahasiswa.kelompok', DB::raw('count(DISTINCT attendance.mahasiswa_id) as total'))
            ->groupBy('mahasiswa.kelompok')
            ->orderBy('total', 'desc')
            ->get();

        // 8. Seluruh Absensi Hari Ini (untuk halaman "Absensi Hari Ini")
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
            ->get();

        // 9. Seluruh Data Mahasiswa
        $mahasiswas = Mahasiswa::orderBy('name', 'asc')->get();

        // 10. Riwayat Absensi
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
            ->get();
        
        // 11. Data Pengajuan Izin/Sakit
        // Mengambil semua data izin beserta relasi mahasiswanya, diurutkan dari yang terbaru
        $izinSubmissions = \App\Models\IzinSubmission::with('mahasiswa')->orderBy('created_at', 'desc')->get();
        
        $totalPendingIzin = $izinSubmissions->where('status', 'pending')->count();
        $totalApprovedIzin = $izinSubmissions->where('status', 'approved')->count();
        $totalRejectedIzin = $izinSubmissions->where('status', 'rejected')->count();

        // Mengirim seluruh data ke view
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
            'totalRejectedIzin'
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