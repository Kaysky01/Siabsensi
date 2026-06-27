<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\Mahasiswa\IzinController;
use App\Http\Controllers\Mahasiswa\KehadiranController;
use App\Http\Controllers\Mahasiswa\MahasiswaController;
use App\Http\Controllers\SertifikatController;
use App\Models\Attendance;
use App\Models\Mahasiswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ─── ROOT REDIRECT ──────────────────────────────────────────────────────────
Route::get('/', function () {
    if (Auth::check()) {
        $role = Auth::user()->role;
        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'timdis' => redirect()->route('admin.dashboard'),
            'garda' => redirect()->route('admin.mahasiswa-saya'),
            'mahasiswa' => redirect()->route('mahasiswa.dashboard'),
            default => redirect()->route('login'),
        };
    }
    return redirect()->route('login');
});

// ─── AUTH ────────────────────────────────────────────────────────────────────
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/auth/login', [AuthController::class, 'auth'])->name('auth');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
});

// ─── ADMIN PAGES (Server-Side Rendered) ─────────────────────────────────────
Route::middleware(['auth', 'role:admin,timdis,garda'])->prefix('admin')->group(function () {
    // View Pages
    Route::get('/dashboard', [AdminController::class, 'dashboard_admin'])->name('admin.dashboard');
    Route::get('/attendance', [AdminController::class, 'attendance'])->name('admin.attendance');
    
    // Master Data
    Route::get('/master/jurusan-prodi', [MasterDataController::class, 'indexJurusanProdi'])->name('admin.master.jurusan-prodi');
    Route::post('/master/jurusan', [MasterDataController::class, 'storeJurusan'])->name('admin.master.jurusan.store');
    Route::put('/master/jurusan/{id}', [MasterDataController::class, 'updateJurusan'])->name('admin.master.jurusan.update');
    Route::delete('/master/jurusan/{id}', [MasterDataController::class, 'destroyJurusan'])->name('admin.master.jurusan.destroy');
    
    Route::post('/master/prodi', [MasterDataController::class, 'storeProdi'])->name('admin.master.prodi.store');
    Route::put('/master/prodi/{id}', [MasterDataController::class, 'updateProdi'])->name('admin.master.prodi.update');
    Route::delete('/master/prodi/{id}', [MasterDataController::class, 'destroyProdi'])->name('admin.master.prodi.destroy');

    Route::get('/master/kompi', [MasterDataController::class, 'indexKompi'])->name('admin.master.kompi');
    Route::post('/master/kompi', [MasterDataController::class, 'storeKompi'])->name('admin.master.kompi.store');
    Route::put('/master/kompi/{id}', [MasterDataController::class, 'updateKompi'])->name('admin.master.kompi.update');
    Route::delete('/master/kompi/{id}', [MasterDataController::class, 'destroyKompi'])->name('admin.master.kompi.destroy');

    // Mahasiswa
    Route::get('/mahasiswa', [AdminController::class, 'mahasiswa'])->name('admin.mahasiswa');
    Route::get('/mahasiswa/import/template', [AdminController::class, 'downloadTemplateCSV'])->name('admin.mahasiswa.import.template');
    Route::post('/mahasiswa/import', [AdminController::class, 'importMahasiswaCSV'])->name('admin.mahasiswa.import');
    Route::get('/mahasiswa/{id}/qr', [AdminController::class, 'qrCode'])->name('admin.mahasiswa.qr');
    Route::get('/mahasiswa-saya', [AdminController::class, 'mahasiswaSaya'])->name('admin.mahasiswa-saya');
    Route::get('/kompi-management', [AdminController::class, 'kompiManagement'])->name('admin.kompi-management');
    Route::post('/kompi-management/bulk', [AdminController::class, 'bulkUpdateKompi'])->name('admin.kompi.bulkUpdate');
    Route::post('/kompi-management/shuffle', [AdminController::class, 'shuffleKompi'])->name('admin.kompi.shuffle');
    Route::get('/history', [AdminController::class, 'history'])->name('admin.history');
    Route::get('/kegiatan', [AdminController::class, 'kegiatan'])->name('admin.kegiatan');
    Route::get('/monitoring-kegiatan', [AdminController::class, 'monitoringKegiatan'])->name('admin.monitoring-kegiatan');
    Route::get('/monitoring-kegiatan/{id}', [AdminController::class, 'monitoringKegiatanDetail'])->name('admin.monitoring-kegiatan.detail');
    Route::get('/kelulusan', [AdminController::class, 'kelulusan'])->name('admin.kelulusan');
    Route::get('/izin-timdis', [AdminController::class, 'izinTimdis'])->name('admin.izin-timdis');
    Route::get('/kehadiran-timdis', [AdminController::class, 'kehadiranTimdis'])->name('admin.kehadiran-timdis');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');

    // Redirect legacy dashboard URLs
    Route::get('/timdis/dashboard', fn() => redirect()->route('admin.dashboard'));
    Route::get('/garda/dashboard', fn() => redirect()->route('admin.mahasiswa-saya'));

    // Export
    Route::get('/attendance/export', [AdminController::class, 'exportAttendance'])->name('admin.attendance.export');
});

// ─── ADMIN FORM ACTIONS (POST) ──────────────────────────────────────────────
Route::middleware(['auth', 'role:admin,timdis,garda'])->prefix('admin')->group(function () {
    // Verifikasi Izin & Kehadiran
    Route::post('/izin/verify', [AdminController::class, 'verifyIzin'])->name('admin.izin.verify');
    Route::post('/kehadiran/verify', [AdminController::class, 'verifyKehadiran'])->name('admin.kehadiran.verify');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // Mahasiswa CRUD
    Route::post('/mahasiswa', [AdminController::class, 'storeMahasiswa'])->name('admin.mahasiswa.store');
    Route::put('/mahasiswa/{id}', [AdminController::class, 'updateMahasiswa'])->name('admin.mahasiswa.update');
    Route::delete('/mahasiswa/{id}', [AdminController::class, 'deleteMahasiswa'])->name('admin.mahasiswa.destroy');

    // Bulk Update Kompi
    Route::post('/kompi/bulk-update', [AdminController::class, 'bulkUpdateKompi'])->name('admin.kompi.bulkUpdate');

    // Users CRUD
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::post('/users/{id}/activate', [AdminController::class, 'activateUser'])->name('admin.users.activate');
    Route::post('/users/{id}/deactivate', [AdminController::class, 'deactivateUser'])->name('admin.users.deactivate');
    Route::post('/users/{id}/reset-password', [AdminController::class, 'resetUserPassword'])->name('admin.users.resetPassword');

    // Settings
    Route::post('/settings/save', [AdminController::class, 'saveSettings'])->name('admin.settings.save');

    // Kegiatan CRUD
    Route::post('/kegiatan', [KegiatanController::class, 'store'])->name('admin.kegiatan.store');
    Route::put('/kegiatan/{id}', [KegiatanController::class, 'update'])->name('admin.kegiatan.update');
    Route::post('/kegiatan/{id}/toggle', [KegiatanController::class, 'toggleActive'])->name('admin.kegiatan.toggle');
    Route::delete('/kegiatan/{id}', [KegiatanController::class, 'destroy'])->name('admin.kegiatan.destroy');

    // QR Code (JSON response for AJAX in case needed)
    Route::get('/mahasiswa/{id}/qr-json', [AdminController::class, 'getMahasiswaQR'])->name('admin.mahasiswa.qr.json');
});

// ─── MAHASISWA PAGES (Server-Side Rendered) ────────────────────────────────
Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->group(function () {
    Route::get('/dashboard', [MahasiswaController::class, 'dashboard'])->name('mahasiswa.dashboard');
    Route::get('/profile', [MahasiswaController::class, 'profile'])->name('mahasiswa.profile');
    Route::put('/profile', [MahasiswaController::class, 'updateProfileData'])->name('mahasiswa.profile.update');
    Route::get('/riwayat', [MahasiswaController::class, 'riwayat'])->name('mahasiswa.riwayat');
    Route::get('/riwayat/export', [MahasiswaController::class, 'exportRiwayat'])->name('mahasiswa.riwayat.export');
    Route::get('/qr-code', [MahasiswaController::class, 'qrCode'])->name('mahasiswa.qr');

    Route::get('/izin', [MahasiswaController::class, 'izin'])->name('mahasiswa.izin');
    Route::post('/izin', [MahasiswaController::class, 'submitIzin'])->name('mahasiswa.izin.submit');
    
    Route::get('/kehadiran', [MahasiswaController::class, 'kehadiran'])->name('mahasiswa.kehadiran');
    Route::post('/kehadiran', [MahasiswaController::class, 'submitKehadiran'])->name('mahasiswa.kehadiran.submit');

    Route::get('/kegiatan', [MahasiswaController::class, 'kegiatan'])->name('mahasiswa.kegiatan');

    Route::get('/sertifikat', [MahasiswaController::class, 'sertifikat'])->name('mahasiswa.sertifikat');
});

// ─── PUBLIC API (untuk Python Backend — langsung konek ke DB, tapi beberapa mungkin diperlukan) ──
Route::post('/api/sync', function (Request $request) {
    try {
        $data = $request->input('data', []);
        $syncedCount = 0;
        
        foreach ($data as $record) {
            if (!isset($record['mahasiswa_id'])) continue;
            
            $qrOrId = $record['mahasiswa_id'];
            $mahasiswa = Mahasiswa::where('qr_code_id', $qrOrId)->orWhere('id', $qrOrId)->first();
            
            if (!$mahasiswa) {
                $cleanId = str_replace(['QR-', '-'], '', $qrOrId);
                $mahasiswa = Mahasiswa::where('id', $cleanId)->first();
            }
            if (!$mahasiswa) continue;
            
            $mahasiswaId = $mahasiswa->id;
            
            $kegiatanId = $record['kegiatan_id'] ?? null;
            $kegiatanDate = Carbon::today()->format('Y-m-d');
            
            if ($kegiatanId) {
                $kegiatan = \App\Models\Kegiatan::find($kegiatanId);
                if ($kegiatan) {
                    $kegiatanDate = Carbon::parse($kegiatan->tanggal_pelaksanaan)->format('Y-m-d');
                }
                
                $attendance = Attendance::where('mahasiswa_id', $mahasiswaId)
                    ->where('kegiatan_id', $kegiatanId)
                    ->first();
            } else {
                $attendance = Attendance::where('mahasiswa_id', $mahasiswaId)
                    ->where('date', $kegiatanDate)
                    ->first();
            }

            $checkIn = isset($record['check_in']) ? Carbon::parse($record['check_in'])->toDateTimeString() : null;
            $checkOut = isset($record['check_out']) ? Carbon::parse($record['check_out'])->toDateTimeString() : null;

            if ($attendance) {
                $attendance->update([
                    'status' => 'hadir', // Konsisten dengan status python
                    'check_in' => $attendance->check_in ?? $checkIn ?? Carbon::now()->toDateTimeString(),
                    'check_out' => $checkOut ?: $attendance->check_out,
                ]);
            } else if ($checkIn) {
                Attendance::create([
                    'mahasiswa_id' => $mahasiswaId,
                    'kegiatan_id' => $kegiatanId,
                    'date' => $kegiatanDate,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'status' => 'hadir',
                ]);
            }
            $syncedCount++;
        }

        return response()->json(['success' => true, 'message' => "Synced $syncedCount records", 'synced_count' => $syncedCount]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
    }
});

// Auth API (untuk mahasiswa dashboard yang masih pakai JS)
Route::middleware(['auth'])->group(function () {
    Route::get('/api/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
});
