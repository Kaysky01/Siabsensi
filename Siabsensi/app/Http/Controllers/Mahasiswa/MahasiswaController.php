<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Exports\RiwayatAbsensiExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Mahasiswa;
use App\Models\PkkmbSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $totalJadwal = PkkmbSchedule::where('is_active', true)
            ->where('tanggal', '<=', Carbon::today())
            ->count();

        $totalHadir = $mahasiswa->attendances()
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->count();
            
        $totalMasihMasuk = $mahasiswa->attendances()
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->count();

        $hadirBulanIni = $mahasiswa->attendances()
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
        $totalIzin = $mahasiswa->izinSubmissions()->where('status', 'approved')->count();
        $totalAlpha = max(0, $totalJadwal - $totalHadir - $totalIzin);
        
        $persentase = $totalJadwal > 0 ? round(($totalHadir / $totalJadwal) * 100) : 0;

        // Hitung rata-rata durasi kehadiran sesungguhnya
        $completedAttendances = $mahasiswa->attendances()
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->get();

        $rataRataDurasi = '-';
        if ($completedAttendances->count() > 0) {
            $totalMinutes = 0;
            $validCount = 0;
            foreach ($completedAttendances as $att) {
                $in = Carbon::parse($att->check_in);
                $out = Carbon::parse($att->check_out);
                if ($out->greaterThan($in)) {
                    $totalMinutes += $out->diffInMinutes($in);
                    $validCount++;
                }
            }
            if ($validCount > 0) {
                $avgMinutes = round($totalMinutes / $validCount);
                $hours = floor($avgMinutes / 60);
                $mins = $avgMinutes % 60;
                if ($hours > 0 && $mins > 0) {
                    $rataRataDurasi = "{$hours} Jam {$mins} Mnt";
                } elseif ($hours > 0) {
                    $rataRataDurasi = "{$hours} Jam";
                } else {
                    $rataRataDurasi = "{$mins} Mnt";
                }
            }
        }

        $certStats = $mahasiswa->getCertificateStats();

        $stats = [
            'totalJadwal' => $totalJadwal,
            'totalHadir' => $totalHadir,
            'totalMasihMasuk' => $totalMasihMasuk,
            'hadirBulanIni' => $hadirBulanIni,
            'totalIzin' => $totalIzin,
            'tidakHadir' => $totalAlpha,
            'persentaseKehadiran' => $persentase,
            'rataRataDurasi' => $rataRataDurasi,
            'streakTerpanjang' => 0,
            'terlambat' => 0,
            'certStats' => $certStats,
        ];

        // Recent Activity (Urutkan berdasarkan aktivitas terbaru: check-out, check-in, atau updated_at)
        $recentActivities = $mahasiswa->attendances()
            ->orderByRaw("GREATEST(COALESCE(check_out, '1970-01-01'), COALESCE(check_in, '1970-01-01')) DESC")
            ->take(5)
            ->get()
            ->map(function ($item) {
                $statusLower = strtolower($item->status);
                $isManual = !empty($item->absen_by) || $statusLower === 'manual';
                $type = in_array($statusLower, ['present', 'hadir', 'lengkap', 'manual']) || $isManual ? 'checkin' : ($statusLower === 'izin' || $statusLower === 'sakit' ? 'izin' : 'info');
                
                $title = in_array($statusLower, ['present', 'hadir', 'lengkap', 'manual']) || $isManual 
                    ? ($isManual ? 'Kehadiran PKKMB (Absen Manual)' : 'Kehadiran PKKMB') 
                    : 'Status: ' . ucfirst($item->status);
                
                $desc = 'Tercatat pada tanggal ' . Carbon::parse($item->date)->format('d M Y');
                if ($item->check_in) {
                    $desc .= ' (Masuk: ' . date('H:i', strtotime($item->check_in));
                    if ($item->check_out) {
                        $desc .= ' • Keluar: ' . date('H:i', strtotime($item->check_out));
                    }
                    $desc .= ')';
                }
                if ($isManual) {
                    $desc .= ' • Verified Manual ' . ($item->absen_by ? 'oleh ' . $item->absen_by : '');
                }
                
                $diff = Carbon::parse($item->updated_at ?? $item->created_at)->diffForHumans();

                return (object)[
                    'type' => $type,
                    'title' => $title,
                    'description' => $desc,
                    'timestamp' => $diff,
                    'status' => $item->status,
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
        $missingProfileFields = $mahasiswa->getMissingProfileFields();
        $isProfileComplete = $mahasiswa->hasCompleteProfile();

        return view('mahasiswa.profile', compact('mahasiswa', 'missingProfileFields', 'isProfileComplete'));
    }

    public function updateProfileData(Request $request)
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:mahasiswa,email,' . $mahasiswa->id . ',id',
            'no_telp_mahasiswa' => 'nullable|string|max:20',
            'no_telp_ortu' => 'nullable|string|max:20',
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

        $mahasiswa->update($request->only('name', 'email', 'no_telp_mahasiswa', 'no_telp_ortu'));
        $mahasiswa->user()->update([
            'full_name' => $request->name,
            'email' => $request->email,
        ]);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function uploadPhoto(Request $request)
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);

        // Jika menggunakan cropper, data dikirim sebagai string base64 melalui 'cropped_image'
        if ($request->filled('cropped_image')) {
            $base64Image = $request->input('cropped_image');
            
            // Ekstrak base64 string
            // format: data:image/jpeg;base64,iVBORw0K...
            $parts = explode(';', $base64Image);
            if (count($parts) == 2) {
                $imageType = explode('/', $parts[0])[1]; // jpeg, png, dst.
                $imageBase64 = explode(',', $parts[1])[1];
                $imageData = base64_decode($imageBase64);
                
                $ext = $imageType === 'jpeg' ? 'jpg' : $imageType;
                
                // Buat nama folder: {nama_lowercase_underscore}_{mahasiswa_id}
                $nameSafe   = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $mahasiswa->name));
                $folderName = $nameSafe . '_' . $mahasiswa->id;
                
                // Hapus foto lama jika ada
                if ($mahasiswa->photo_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($mahasiswa->photo_path);
                }
                
                $fileName = 'photo_' . time() . '.' . $ext;
                $path = 'berkas/' . $folderName . '/' . $fileName;
                
                // Simpan file
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $imageData);
                
                $mahasiswa->photo_path = $path;
                $mahasiswa->save();
                
                return back()->with('success', 'Foto profil berhasil diunggah.');
            }
            return back()->with('error', 'Gagal memproses foto.');
        }

        // Fallback jika tidak menggunakan base64 (upload normal)
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'photo.required' => 'Foto wajib dipilih.',
            'photo.image'    => 'File harus berupa gambar.',
            'photo.mimes'    => 'Format foto harus JPG, PNG, atau WEBP.',
            'photo.max'      => 'Ukuran foto maksimal 2MB.',
        ]);

        // Buat nama folder: {nama_lowercase_underscore}_{mahasiswa_id}
        $nameSafe   = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $mahasiswa->name));
        $folderName = $nameSafe . '_' . $mahasiswa->id;

        // Hapus foto lama jika ada
        if ($mahasiswa->photo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($mahasiswa->photo_path);
        }

        // Simpan foto ke storage/app/public/berkas/{folderName}/photo.{ext}
        $ext  = $request->file('photo')->getClientOriginalExtension();
        $path = $request->file('photo')->storeAs(
            'berkas/' . $folderName,
            'photo.' . $ext,
            'public'
        );

        $mahasiswa->update(['photo_path' => $path]);

        return back()->with('success', 'Foto profil berhasil diperbarui.');
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
        
        $riwayat = $query->orderBy('date', 'desc')
            ->orderByRaw("GREATEST(COALESCE(check_out, '1970-01-01'), COALESCE(check_in, '1970-01-01')) DESC")
            ->get();
        return view('mahasiswa.riwayat', compact('mahasiswa', 'riwayat'));
    }

    public function qrCode()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        $missingProfileFields = $mahasiswa->getMissingProfileFields();
        $isProfileComplete = $mahasiswa->hasCompleteProfile();
        $hasKompi = !empty(trim($mahasiswa->kompi ?? '')) && trim($mahasiswa->kompi) !== '-';

        if (!$isProfileComplete || !$hasKompi) {
            return view('mahasiswa.qr-code', compact('mahasiswa', 'missingProfileFields', 'isProfileComplete', 'hasKompi'));
        }
        
        $template = $mahasiswa->getIdCardTemplate();
        
        if (!$template) {
            return view('errors.template-not-found', [
                'jurusan' => $mahasiswa->jurusan ?? 'Tidak Diketahui',
                'mahasiswa' => $mahasiswa
            ]);
        }
        
        // Generate QR Code
        $qrData = $mahasiswa->qr_code_id ?? $mahasiswa->id;
        $qrImage = \Illuminate\Support\Facades\Cache::remember('qr_svg_' . $mahasiswa->id, 300, function() use ($qrData) {
            if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
                return (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(250)->generate($qrData);
            }
            return '<img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($qrData).'" />';
        });
        
        $templateDepan = $template['template_depan'];
        $templateBelakang = $template['template_belakang'];
        
        $hasKompi = true;

        return view('mahasiswa.qr-code', compact(
            'mahasiswa',
            'qrImage',
            'templateDepan',
            'templateBelakang',
            'missingProfileFields',
            'isProfileComplete',
            'hasKompi'
        ));
    }

    public function izin()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        $riwayatIzin = $mahasiswa->izinSubmissions()->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();
        $schedules = PkkmbSchedule::where('is_active', true)->orderBy('hari_ke')->orderBy('tanggal')->get();
        return view('mahasiswa.izin', compact('mahasiswa', 'riwayatIzin', 'schedules'));
    }

    public function submitIzin(Request $request)
    {
        $request->validate([
            'type' => 'required|in:izin,sakit',
            'date' => 'required|date',
            'reason' => 'required|string',
            'bukti' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ], [
            'type.required' => 'Jenis pengajuan wajib dipilih.',
            'type.in' => 'Jenis pengajuan tidak valid.',
            'date.required' => 'Tanggal izin/sakit wajib diisi.',
            'date.date' => 'Format tanggal tidak valid.',
            'reason.required' => 'Alasan wajib diisi.',
            'bukti.required' => 'Bukti lampiran wajib diunggah.',
            'bukti.file' => 'Bukti harus berupa file.',
            'bukti.mimes' => 'Format bukti harus PDF, JPG, JPEG, atau PNG.',
            'bukti.max' => 'Ukuran bukti maksimal 2MB.',
        ]);

        try {
            $mhsId = Auth::user()->mahasiswa_id;
            $targetDate = \Carbon\Carbon::parse($request->date)->format('Y-m-d');

            $existingIzin = \App\Models\IzinSubmission::where('mahasiswa_id', $mhsId)
                ->whereDate('date', $targetDate)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            $existingKehadiran = \App\Models\KehadiranSubmission::where('mahasiswa_id', $mhsId)
                ->whereDate('date', $targetDate)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingIzin || $existingKehadiran) {
                return back()->with('error', 'Anda sudah memiliki pengajuan (Izin / Kehadiran) untuk tanggal ' . \Carbon\Carbon::parse($targetDate)->format('d/m/Y') . '. Tidak dapat mengirim pengajuan ganda.')->withInput();
            }

            $path = $request->file('bukti')->store('izin_bukti', 'public');

            \App\Models\IzinSubmission::create([
                'mahasiswa_id' => $mhsId,
                'submission_type' => $request->type,
                'date' => $request->date,
                'keterangan' => $request->reason,
                'bukti_path' => $path,
                'status' => 'pending',
            ]);

            return back()->with('success', 'Pengajuan ' . $request->type . ' berhasil dikirim.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengirim pengajuan: ' . $e->getMessage())->withInput();
        }
    }

    public function deleteIzin($id)
    {
        $izin = \App\Models\IzinSubmission::where('id', $id)
            ->where('mahasiswa_id', Auth::user()->mahasiswa_id)
            ->firstOrFail();

        if ($izin->status !== 'pending') {
            return back()->with('error', 'Hanya pengajuan dengan status Menunggu yang dapat dihapus.');
        }

        try {
            if ($izin->bukti_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($izin->bukti_path);
            }
            $izin->delete();
            return back()->with('success', 'Pengajuan berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus pengajuan.');
        }
    }
    
    public function kehadiran()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        $riwayatKehadiran = $mahasiswa->kehadiranSubmissions()->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();
        $schedules = PkkmbSchedule::where('is_active', true)->orderBy('hari_ke')->orderBy('tanggal')->get();
        return view('mahasiswa.kehadiran', compact('mahasiswa', 'riwayatKehadiran', 'schedules'));
    }

public function submitKehadiran(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string',
            'bukti' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ], [
            'bukti.required' => 'Bukti lampiran wajib diunggah.',
            'bukti.mimes' => 'Format bukti harus JPG, JPEG, PNG, atau PDF.',
            'bukti.max' => 'Ukuran file bukti maksimal 2MB.',
        ]);

        try {
            $mhsId = Auth::user()->mahasiswa_id;
            $targetDate = \Carbon\Carbon::parse($request->date)->format('Y-m-d');

            $existingIzin = \App\Models\IzinSubmission::where('mahasiswa_id', $mhsId)
                ->whereDate('date', $targetDate)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            $existingKehadiran = \App\Models\KehadiranSubmission::where('mahasiswa_id', $mhsId)
                ->whereDate('date', $targetDate)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingIzin || $existingKehadiran) {
                return back()->with('error', 'Anda sudah memiliki pengajuan (Izin / Kehadiran) untuk tanggal ' . \Carbon\Carbon::parse($targetDate)->format('d/m/Y') . '. Tidak dapat mengirim pengajuan ganda.')->withInput();
            }

            $path = $request->file('bukti')->store('kehadiran_bukti', 'public');

            \App\Models\KehadiranSubmission::create([
                'mahasiswa_id' => $mhsId,
                'date' => $request->date,
                'check_in_time' => '08:00:00',
                'check_out_time' => '16:00:00',
                'keterangan' => $request->reason,
                'bukti_path' => $path,
                'status' => 'pending',
            ]);

            return back()->with('success', 'Pengajuan kehadiran manual berhasil dikirim.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengirim pengajuan: ' . $e->getMessage());
        }
    }

    public function deleteKehadiran($id)
    {
        $kehadiran = \App\Models\KehadiranSubmission::where('id', $id)
            ->where('mahasiswa_id', Auth::user()->mahasiswa_id)
            ->firstOrFail();

        if ($kehadiran->status !== 'pending') {
            return back()->with('error', 'Hanya pengajuan dengan status Menunggu yang dapat dibatalkan/dihapus.');
        }

        try {
            if ($kehadiran->bukti_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($kehadiran->bukti_path);
            }
            $kehadiran->delete();
            return back()->with('success', 'Pengajuan kehadiran berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membatalkan pengajuan kehadiran.');
        }
    }

    public function kegiatan()
    {
        $mahasiswa = Mahasiswa::find(Auth::user()->mahasiswa_id);
        
        $schedules = \App\Models\PkkmbSchedule::with(['sesi' => function($query) {
            $query->where('is_active', 1)->orderBy('jam_mulai');
        }])
        ->where('is_active', 1)
        ->orderBy('tanggal')
        ->get();

        $riwayatSesi = \App\Models\AttendanceSesi::where('mahasiswa_id', $mahasiswa->id)
            ->with(['sesi.kegiatan', 'sesi.pkkmbSchedule'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        $attendancesBySesi = $riwayatSesi->keyBy('sesi_id');

        $dailyAttendances = \App\Models\Attendance::daily()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->get()
            ->keyBy(function($a) {
                return \Carbon\Carbon::parse($a->date)->format('Y-m-d');
            });

        return view('mahasiswa.kegiatan', compact('mahasiswa', 'schedules', 'riwayatSesi', 'attendancesBySesi', 'dailyAttendances'));
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
        $certStats = $mahasiswa->getCertificateStats();
        $isLulus   = $certStats['can_get'];
        
        $mahasiswa->status_kelulusan = $isLulus ? 'LULUS' : 'TIDAK LULUS';
        
        return view('mahasiswa.sertifikat', compact('mahasiswa', 'certStats', 'isLulus'));
    }
}

