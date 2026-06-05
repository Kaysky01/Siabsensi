<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mahasiswa; // Pastikan Model di-import
use Carbon\Carbon;        // Import Carbon untuk manipulasi tanggal
use Illuminate\Support\Facades\Auth; // Import Auth untuk otorisasi
use App\Exports\RiwayatAbsensiExport;
use Maatwebsite\Excel\Facades\Excel;

class MahasiswaController extends Controller
{
    // Menampilkan halaman HTML/Blade
    public function dashboard()
    {
        return view('mahasiswa.mahasiswa');
    }

    // --- API ENDPOINTS ---

    // Mengambil Data Statistik
    public function getStatistics($id)
    {
        $mahasiswa = Mahasiswa::find($id);

        if (!$mahasiswa) {
            return response()->json(['success' => false, 'message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        // Hitung data langsung via Relasi Model
        $totalHadir = $mahasiswa->attendances()->count();
        
        $hadirBulanIni = $mahasiswa->attendances()
                            ->whereMonth('created_at', Carbon::now()->month)
                            ->whereYear('created_at', Carbon::now()->year)
                            ->count();

        $totalIzin = $mahasiswa->izinSubmissions()
                            ->where('status', 'approved')
                            ->count();

        $totalHariKerja = $totalHadir + $totalIzin; 
        $persentase = $totalHariKerja > 0 ? round(($totalHadir / $totalHariKerja) * 100) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'totalHadir' => $totalHadir,
                'hadirBulanIni' => $hadirBulanIni,
                'totalIzin' => $totalIzin,
                'tidakHadir' => 0, 
                'persentaseKehadiran' => $persentase,
                'rataRataDurasi' => '8 jam',
                'streakTerpanjang' => 0,
                'terlambat' => 0 
            ]
        ]);
    }

    // Mengambil Data Grafik Mingguan (Sementara kembalikan array statis, bisa dikembangkan)
    public function getWeeklyChart($id)
    {
        return response()->json([
            'success' => true,
            'data' => ['attendance' => [8, 8, 8, 0, 8, 4, 0]]
        ]);
    }

    // Mengambil Data Grafik Bulanan
    public function getMonthlyChart($id)
    {
        return response()->json([
            'success' => true,
            'data' => ['monthly' => [20, 22, 19, 21, 18, 0]]
        ]);
    }

    // Mengambil Data Aktivitas Terbaru
    public function getRecentActivity($id)
    {
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'type' => 'checkin',
                    'title' => 'Check-In Tepat Waktu',
                    'description' => 'Sistem mencatat kehadiran di gerbang.',
                    'timestamp' => Carbon::now()
                ]
            ]
        ]);
    }

    // Mengambil Data Profil Mahasiswa
    public function getProfile($id)
    {
        $user = Auth::user();
        // Pastikan hanya admin atau pemilik profil yang bisa mengambil data
        if ($user->role !== 'admin' && $user->mahasiswa_id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Anda tidak memiliki akses ke data ini'
            ], 403);
        }

        // Cari data mahasiswa di tabel Mahasiswa berdasarkan ID
        $mahasiswa = Mahasiswa:: find($id);
        $mahasiswa = Mahasiswa::find($id);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $mahasiswa
        ]);
    }

    // Mengambil riwayat kehadiran mahasiswa yang sedang login
    public function getRiwayat(Request $request, $id)
    {
        // Mencari Mahasiswa menggunakan ID
        $mahasiswa = Mahasiswa::find($id);
        
        if (!$mahasiswa) {
            return response()->json(['success' => false, 'message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        // Query untuk mengambil data
        $query = $mahasiswa->attendances();

        // --- PROSES FILTER ---
        if ($request->filled('bulan')) {
            $query->whereMonth('date', $request->query('bulan')); 
        }
        if ($request->filled('tahun')) {
            $query->whereYear('date', $request->query('tahun'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $riwayat = $query->orderBy('date', 'desc')->get();

        // Format data
        $formattedData = $riwayat->map(function ($item) {
            $namaHari = [
                'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
                'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
            ];
            
            // AMBIL TANGGAL SAJA (substr 0,10) untuk menghindari timezone jam 00:00:00
            $tgl = substr($item->date, 0, 10); 
            $hariInggris = date('l', strtotime($tgl)); 

            return [
                'date_str' => date('d/m/Y', strtotime($item->date)),
                'hari'           => $namaHari[$hariInggris] ?? $hariInggris,
                // AMBIL JAM DARI KOLOM check_in BUKAN check_in_time (karena datamu di check_in)
                'check_in_time'  => $item->check_in ? date('H:i', strtotime($item->check_in)) : '-',
                'check_out_time' => $item->check_out ? date('H:i', strtotime($item->check_out)) : '-',
                'status'         => $item->status, // Ini akan mengirim 'hadir'
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $formattedData
        ]);
    }

    public function exportRiwayat(Request $request, $id)
    {
        // Panggil Excel::download, library akan mengurus semuanya
        return Excel::download(new RiwayatAbsensiExport($id), 'riwayat_kehadiran.xlsx');
    }

    public function updateProfile(Request $request, $id)
    {
        $user = Auth::user();
        // Pastikan hanya admin atau pemilik profil yang bisa mengupdate
        if ($user->role !== 'admin' && $user->mahasiswa_id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Anda tidak diizinkan mengubah profil ini'
            ], 403);
        }

        // Mencari mahasiswa dahulu
        $mahasiswa = Mahasiswa::find($id);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        // Validasi input
        $request->validate([
            'name'     => 'required|string|max:50',
            'kelompok' => 'required|string|max:20',
            'jurusan'  => 'required|string|max:100',
            'email'    => 'nullable|email',
        ]);

        // Update data mahasiswa
        $mahasiswa->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui'
        ]);

    }

    public function getTodayAttendanceStatus($id)
    {
        $mahasiswa = Mahasiswa::find($id);

        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'
            ], 404);
        }

        $status = $mahasiswa->getTodayAttendanceStatus();

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }
}