<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Exports\RiwayatAbsensiExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Mahasiswa; // Pastikan Model di-import
use Carbon\Carbon;        // Import Carbon untuk manipulasi tanggal
use Illuminate\Http\Request; // Import Auth untuk otorisasi
use Illuminate\Support\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

        if (! $mahasiswa) {
            return response()->json(['success' => false, 'message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        // Hitung data langsung via Relasi Model
        $totalHadir = $mahasiswa->attendances()->where('status', 'hadir')->count();

        $hadirBulanIni = $mahasiswa->attendances()
            ->where('status', 'hadir')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $totalIzin = $mahasiswa->izinSubmissions()
            ->where('status', 'approved')
            ->count();

        $totalAlpha = $mahasiswa->attendances()->where('status', 'alpha')->count();

        $totalHariKerja = $totalHadir + $totalIzin + $totalAlpha;
        $persentase = $totalHariKerja > 0 ? round(($totalHadir / $totalHariKerja) * 100) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'totalHadir' => $totalHadir,
                'hadirBulanIni' => $hadirBulanIni,
                'totalIzin' => $totalIzin,
                'tidakHadir' => $totalAlpha,
                'persentaseKehadiran' => $persentase,
                'rataRataDurasi' => '8 jam',
                'streakTerpanjang' => 0,
                'terlambat' => 0,
            ],
        ]);
    }

    // Mengambil Data Grafik Mingguan (Sementara kembalikan array statis, bisa dikembangkan)
    public function getWeeklyChart($id)
    {
        return response()->json([
            'success' => true,
            'data' => ['attendance' => [8, 8, 8, 0, 8, 4, 0]],
        ]);
    }

    // Mengambil Data Grafik Bulanan
    public function getMonthlyChart($id)
    {
        return response()->json([
            'success' => true,
            'data' => ['monthly' => [20, 22, 19, 21, 18, 0]],
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
                    'timestamp' => Carbon::now(),
                ],
            ],
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
                'message' => 'Akses ditolak: Anda tidak memiliki akses ke data ini',
            ], 403);
        }

        // Cari data mahasiswa di tabel Mahasiswa berdasarkan ID
        $mahasiswa = Mahasiswa::find($id);
        $mahasiswa = Mahasiswa::find($id);

        if (! $mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $mahasiswa,
        ]);
    }

    // Mengambil riwayat kehadiran mahasiswa yang sedang login
    public function getRiwayat(Request $request, $id)
    {
        // Mencari Mahasiswa menggunakan ID
        $mahasiswa = Mahasiswa::find($id);

        if (! $mahasiswa) {
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
                'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
            ];

            // AMBIL TANGGAL SAJA (substr 0,10) untuk menghindari timezone jam 00:00:00
            $tgl = substr($item->date, 0, 10);
            $hariInggris = date('l', strtotime($tgl));

            return [
                'date_str' => date('d/m/Y', strtotime($item->date)),
                'hari' => $namaHari[$hariInggris] ?? $hariInggris,
                // AMBIL JAM DARI KOLOM check_in BUKAN check_in_time (karena datamu di check_in)
                'check_in_time' => $item->check_in ? date('H:i', strtotime($item->check_in)) : '-',
                'check_out_time' => $item->check_out ? date('H:i', strtotime($item->check_out)) : '-',
                'status' => $item->status, // Ini akan mengirim 'hadir'
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedData,
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
                'message' => 'Akses ditolak: Anda tidak diizinkan mengubah profil ini',
            ], 403);
        }

        // Mencari mahasiswa dahulu
        $mahasiswa = Mahasiswa::find($id);

        if (! $mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        // Validasi input
        $rules = [
            'email' => 'nullable|email',
        ];

        if ($request->filled('new_password')) {
            $rules['current_password'] = 'required|string';
            $rules['new_password'] = 'required|string|min:6|confirmed';
        }

        $request->validate($rules);

        // Update data mahasiswa (Hanya email yang bisa diubah oleh mahasiswa)
        $mahasiswa->update([
            'email' => $request->email,
        ]);

        $targetUser = $mahasiswa->user;
        if ($targetUser) {
            $targetUser->update([
                'email' => $request->email,
            ]);
        }

        if ($request->filled('new_password')) {
            if (! $targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun User tidak ditemukan untuk mahasiswa ini',
                ], 404);
            }

            if (! Hash::check($request->current_password, $targetUser->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password saat ini salah',
                ], 422);
            }

            $targetUser->update([
                'password_hash' => Hash::make($request->new_password),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
        ]);

    }

    public function getTodayAttendanceStatus($id)
    {
        $mahasiswa = Mahasiswa::find($id);

        if (! $mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan',
            ], 404);
        }

        $status = $mahasiswa->getTodayAttendanceStatus();

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function getAttendanceReminder(Request $request)
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'mahasiswa' || ! $user->mahasiswa_id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak',
            ], 403);
        }

        if ($request->session()->get('attendance_reminder_shown', false)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'should_show' => false,
                    'missing_dates' => [],
                ],
            ]);
        }

        $mahasiswa = Mahasiswa::find($user->mahasiswa_id);

        if (! $mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan',
            ], 404);
        }

        $yesterday = Carbon::yesterday()->startOfDay();
        $startDate = Carbon::parse($mahasiswa->created_at)->startOfDay();

        if ($startDate->greaterThan($yesterday)) {
            $request->session()->put('attendance_reminder_shown', true);

            return response()->json([
                'success' => true,
                'data' => [
                    'should_show' => false,
                    'missing_dates' => [],
                ],
            ]);
        }

        $attendanceDates = Attendance::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereBetween('date', [$startDate->toDateString(), $yesterday->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $missingDates = [];
        foreach (CarbonPeriod::create($startDate, $yesterday) as $date) {
            $dateString = $date->toDateString();
            if (! $attendanceDates->has($dateString)) {
                $missingDates[] = $dateString;
            }
        }

        $request->session()->put('attendance_reminder_shown', true);

        return response()->json([
            'success' => true,
            'data' => [
                'should_show' => ! empty($missingDates),
                'missing_dates' => $missingDates,
            ],
        ]);
    }

    public function getQRCode($id)
    {
        $mahasiswa = Mahasiswa::find($id);

        if (! $mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan',
            ], 404);
        }

        // Generate QR code dynamically using the same method as admin
        $qrData = $mahasiswa->qr_code_id ?? $mahasiswa->id;
        $qrImage = $this->generateQrImage($qrData, 300);

        if (! $qrImage) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate QR Code',
            ], 500);
        }

        return response($qrImage)->header('Content-Type', 'image/png');
    }

    private function generateQrImage($data, $size)
    {
        // Gunakan package Laravel QR jika ada
        if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
            return QrCode::format('png')->size($size)->generate($data);
        }

        // Fallback URL jika package tidak ada (menggunakan API eksternal)
        try {
            $img = @file_get_contents("https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=".urlencode($data));
            if ($img) {
                return $img;
            }
        } catch (\Exception $e) {
        }

        return null;
    }
}
