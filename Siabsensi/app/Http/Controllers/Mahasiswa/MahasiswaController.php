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
        $user = Auth::user();
        if (!$user || !$user->mahasiswa_id) {
            return redirect('/login')->with('error', 'Akses ditolak.');
        }

        $mahasiswa = Mahasiswa::find($user->mahasiswa_id);
        if (!$mahasiswa) {
            return redirect('/login')->with('error', 'Data mahasiswa tidak ditemukan.');
        }

        // Stats
        $totalHadir = $mahasiswa->attendances()->whereIn('status', ['hadir', 'present'])->count();
        $hadirBulanIni = $mahasiswa->attendances()
            ->whereIn('status', ['hadir', 'present'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
        $totalIzin = $mahasiswa->izinSubmissions()->where('status', 'approved')->count();
        $totalAlpha = $mahasiswa->attendances()->where('status', 'alpha')->count();
        
        $totalHariKerja = $totalHadir + $totalIzin + $totalAlpha;
        $persentase = $totalHariKerja > 0 ? round(($totalHadir / $totalHariKerja) * 100) : 0;

        $stats = [
            'totalHadir' => $totalHadir,
            'hadirBulanIni' => $hadirBulanIni,
            'totalIzin' => $totalIzin,
            'tidakHadir' => $totalAlpha,
            'persentaseKehadiran' => $persentase,
            'rataRataDurasi' => '8 jam',
            'streakTerpanjang' => 0, // Placeholder
            'terlambat' => 0, // Placeholder
        ];

        // Recent Activity
        $recentActivities = $mahasiswa->attendances()
            ->orderBy('date', 'desc')
            ->take(5)
            ->get()
            ->map(function ($item) {
                $type = in_array($item->status, ['present', 'hadir']) ? 'checkin' : 'info';
                $title = in_array($item->status, ['present', 'hadir']) ? 'Kehadiran' : 'Status: ' . ucfirst($item->status);
                $desc = 'Tercatat pada tanggal ' . Carbon::parse($item->date)->format('d M Y');
                if ($item->check_in) {
                    $desc .= ' pukul ' . date('H:i', strtotime($item->check_in));
                }
                return (object)[
                    'type' => $type,
                    'title' => $title,
                    'description' => $desc,
                    'timestamp' => Carbon::parse($item->updated_at)->diffForHumans(),
                ];
            });

        return view('mahasiswa.dashboard', compact('mahasiswa', 'stats', 'recentActivities'));
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
    public function profile()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        return view('mahasiswa.profile', compact('mahasiswa'));
    }

    public function updateProfileData(Request $request)
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
        ]);
        
        if ($request->filled('new_password')) {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);
            $user = $mahasiswa->user;
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->with('error', 'Password saat ini salah.');
            }
            $user->update(['password' => Hash::make($request->new_password)]);
        }

        $mahasiswa->update($request->only('name', 'email'));
        $mahasiswa->user()->update($request->only('name', 'email'));

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function riwayat(Request $request)
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        
        $query = $mahasiswa->attendances();
        
        if ($request->filled('bulan')) {
            $query->whereMonth('date', $request->bulan);
        }
        if ($request->filled('tahun')) {
            $query->whereYear('date', $request->tahun);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $riwayat = $query->orderBy('date', 'desc')->get();
        return view('mahasiswa.riwayat', compact('mahasiswa', 'riwayat'));
    }

    public function qrCode()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        $qrData = $mahasiswa->qr_code_id ?? $mahasiswa->id;
        
        // Generate QR Code string from cache (5 minutes)
        $qrImage = \Illuminate\Support\Facades\Cache::remember('qr_svg_' . $mahasiswa->id, 300, function() use ($qrData) {
            if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
                return (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(250)->generate($qrData);
            }
            return '<img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($qrData).'" />';
        });

        return view('mahasiswa.qr-code', compact('mahasiswa', 'qrImage'));
    }

    public function izin()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        $riwayatIzin = $mahasiswa->izinSubmissions()->orderBy('created_at', 'desc')->get();
        return view('mahasiswa.izin', compact('mahasiswa', 'riwayatIzin'));
    }

    public function submitIzin(Request $request)
    {
        $request->validate([
            'type' => 'required|in:izin,sakit',
            'date' => 'required|date',
            'reason' => 'required|string',
            'bukti' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $path = $request->file('bukti')->store('izin_bukti', 'public');

        \App\Models\IzinSubmission::create([
            'mahasiswa_id' => Auth::user()->mahasiswa_id,
            'submission_type' => $request->type,
            'date' => $request->date,
            'keterangan' => $request->reason,
            'bukti_path' => $path,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan ' . $request->type . ' berhasil dikirim.');
    }
    
    public function kehadiran()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        $riwayatKehadiran = $mahasiswa->kehadiranSubmissions()->orderBy('created_at', 'desc')->get();
        return view('mahasiswa.kehadiran', compact('mahasiswa', 'riwayatKehadiran'));
    }

    public function submitKehadiran(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string',
            'bukti' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        $path = null;
        if ($request->hasFile('bukti')) {
            $path = $request->file('bukti')->store('kehadiran_bukti', 'public');
        }

        \App\Models\KehadiranSubmission::create([
            'mahasiswa_id' => Auth::user()->mahasiswa_id,
            'date' => $request->date,
            'check_in_time' => '08:00:00',
            'check_out_time' => '16:00:00',
            'keterangan' => $request->reason,
            'bukti_path' => $path,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan kehadiran manual berhasil dikirim.');
    }

    public function kegiatan()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        
        $kegiatanTersedia = \App\Models\Kegiatan::where('is_active', true)
            ->orderBy('tanggal_pelaksanaan', 'asc')
            ->get();
            
        $riwayatKegiatan = Attendance::where('mahasiswa_id', $mahasiswa->id)
            ->whereNotNull('kegiatan_id')
            ->with('kegiatan')
            ->orderBy('check_in', 'desc')
            ->get();
        
        return view('mahasiswa.kegiatan', compact('mahasiswa', 'kegiatanTersedia', 'riwayatKegiatan'));
    }

    public function absenKegiatan(Request $request)
    {
        $request->validate([
            'kegiatan_id' => 'required|exists:kegiatan,id',
        ]);

        $mahasiswaId = Auth::user()->mahasiswa_id;
        $kegiatan = \App\Models\Kegiatan::findOrFail($request->kegiatan_id);
        $hariIni = Carbon::now()->format('Y-m-d');

        if ($kegiatan->tanggal_pelaksanaan->format('Y-m-d') !== $hariIni) {
            return back()->with('error', 'Kegiatan tidak berlangsung hari ini.');
        }

        $existing = Attendance::where('kegiatan_id', $kegiatan->id)
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereDate('date', $hariIni)
            ->first();

        if ($existing) {
            if ($existing->check_out) {
                return back()->with('error', 'Anda sudah absen masuk dan keluar pada kegiatan ini.');
            }
            
            $existing->update([
                'check_out' => Carbon::now()->toDateTimeString()
            ]);
            
            return back()->with('success', 'Absen Keluar (Check-out) berhasil dicatat.');
        }

        Attendance::create([
            'kegiatan_id' => $kegiatan->id,
            'mahasiswa_id' => $mahasiswaId,
            'date' => $hariIni,
            'status' => 'present',
            'check_in' => Carbon::now()->toDateTimeString()
        ]);

        return back()->with('success', 'Absensi kegiatan berhasil dicatat.');
    }

    public function sertifikat()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        
        $firstKegiatan = \App\Models\Kegiatan::orderBy('tanggal_pelaksanaan', 'asc')->first();
        $lastKegiatan = \App\Models\Kegiatan::orderBy('tanggal_pelaksanaan', 'desc')->first();
        
        if ($firstKegiatan && $lastKegiatan) {
            $startDate = $firstKegiatan->tanggal_pelaksanaan;
            $endDate = $lastKegiatan->tanggal_pelaksanaan;
        } else {
            $startDate = \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = \Carbon\Carbon::now()->endOfWeek()->format('Y-m-d');
        }
        
        $isLulus = $mahasiswa->canGetCertificate($startDate, $endDate);
        
        $mahasiswa->status_kelulusan = $isLulus ? 'LULUS' : 'TIDAK LULUS';
        
        return view('mahasiswa.sertifikat', compact('mahasiswa'));
    }
}
