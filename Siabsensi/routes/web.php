<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AttendanceScheduleController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Garda\AttendanceController;
use App\Http\Controllers\Garda\DashboardController;
use App\Http\Controllers\Garda\ProfileController;
use App\Http\Controllers\Garda\RiwayatController;
use App\Http\Controllers\Garda\StudentController;
use App\Http\Controllers\Garda\VerificationController;
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
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\Timdis\TimdisController;

// ?????? ROOT REDIRECT ????????????????????????????????????????????????????????????????????????????????????????????????????????????????
Route::get('/', function () {
    if (Auth::check()) {
        $role = Auth::user()->role;
        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'timdis' => redirect()->route('timdis.dashboard'),
            'garda' => redirect()->route('garda.dashboard'),
            'mahasiswa' => redirect()->route('mahasiswa.dashboard'),
            default => redirect()->route('login'),
        };
    }
    return redirect()->route('login');
});

// ─── AUTH ────────────────────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'auth'])->name('auth');

Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
});

    // DEBUG ROUTE
    Route::get('/debug-login/{username}/{password}', function ($username, $password) {
        $user = \App\Models\User::where('username', $username)->with('mahasiswa')->first();
        if (!$user) return 'User not found';
        
        $m = $user->mahasiswa;
        $dbTglLahir = $m ? $m->tanggal_lahir : 'no_mahasiswa';
        $tglLahirFormat = $m && $m->tanggal_lahir ? \Carbon\Carbon::parse($m->tanggal_lahir)->format('dmY') : 'null';
        
        $authAttempt = \Illuminate\Support\Facades\Auth::validate([
            'username' => $username,
            'password' => $password
        ]);

        $hashCheck = \Illuminate\Support\Facades\Hash::check($password, $user->password);
        
        return [
            'username' => $username,
            'input_password' => $password,
            'tgl_lahir_db' => $dbTglLahir,
            'tgl_lahir_formatted' => $tglLahirFormat,
            'db_hash' => $user->password,
            'auth_attempt_result' => $authAttempt,
            'hash_check_result' => $hashCheck,
            'fallback_match' => ($password === $tglLahirFormat)
        ];
    });

// ─── GARDA PAGES ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:garda'])->prefix('garda')->group(function () {
    Route::get('/dashboard', [DashboardController::class, '__invoke'])->name('garda.dashboard');
    Route::get('/absensi-persesi', [AttendanceController::class, 'listSessions'])->name('garda.absensi-persesi');
    Route::get('/absensi-manual/{sesi}', [AttendanceController::class, 'manualAttendance'])->name('garda.absensi-manual.index');
    Route::post('/absensi-manual/{sesi}', [AttendanceController::class, 'store'])->name('garda.absensi-manual.store');
    Route::get('/absen-kegiatan/{sesi}', [AttendanceController::class, 'absenKegiatan'])->name('garda.absen-kegiatan');
    Route::post('/sesi/tambah', [AttendanceController::class, 'tambahSesi'])->name('garda.sesi.tambah');
    Route::get('/mahasiswa-saya', [StudentController::class, 'myStudents'])->name('garda.mahasiswa-saya');
    Route::get('/riwayat', [RiwayatController::class, '__invoke'])->name('garda.riwayat');
    Route::get('/profile', [ProfileController::class, 'profile'])->name('garda.profile');
    Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('garda.profile.update');
    Route::get('/izin', [VerificationController::class, 'izin'])->name('garda.izin');
    Route::post('/izin/verify', [VerificationController::class, 'verifyIzin'])->name('garda.izin.verify');
    Route::get('/kehadiran-manual', [VerificationController::class, 'kehadiranManual'])->name('garda.kehadiran-manual');
    Route::post('/kehadiran/verify', [VerificationController::class, 'verifyKehadiran'])->name('garda.kehadiran.verify');
});

// ?????? TIMDIS PAGES ??????
Route::middleware(['auth', 'role:timdis'])->prefix('timdis')->group(function () {
    Route::get('/dashboard', [TimdisController::class, 'dashboard'])->name('timdis.dashboard');
    Route::get('/attendance', [\App\Http\Controllers\Timdis\AttendanceController::class, 'attendance'])->name('timdis.attendance');
    Route::get('/pkkmb-schedule', [\App\Http\Controllers\Timdis\PkkmbScheduleController::class, 'index'])->name('timdis.pkkmb-schedule.index');
    Route::post('/pkkmb-schedule', [\App\Http\Controllers\Timdis\PkkmbScheduleController::class, 'store'])->name('timdis.pkkmb-schedule.store');
    Route::put('/pkkmb-schedule/{id}', [\App\Http\Controllers\Timdis\PkkmbScheduleController::class, 'update'])->name('timdis.pkkmb-schedule.update');
    Route::post('/pkkmb-schedule/{id}/toggle', [\App\Http\Controllers\Timdis\PkkmbScheduleController::class, 'toggleActive'])->name('timdis.pkkmb-schedule.toggle');
    Route::delete('/pkkmb-schedule/{id}', [\App\Http\Controllers\Timdis\PkkmbScheduleController::class, 'destroy'])->name('timdis.pkkmb-schedule.destroy');
    Route::post('/pkkmb-schedule/grace-period', [\App\Http\Controllers\Timdis\PkkmbScheduleController::class, 'updateGracePeriod'])->name('timdis.pkkmb-schedule.gracePeriod');
    Route::get('/kegiatan', [\App\Http\Controllers\Timdis\KegiatanController::class, 'index'])->name('timdis.kegiatan');
    Route::post('/kegiatan', [\App\Http\Controllers\Timdis\KegiatanController::class, 'store'])->name('timdis.kegiatan.store');
    Route::put('/kegiatan/{sesi}', [\App\Http\Controllers\Timdis\KegiatanController::class, 'update'])->name('timdis.kegiatan.update');
    Route::post('/kegiatan/{sesi}/toggle', [\App\Http\Controllers\Timdis\KegiatanController::class, 'toggleActive'])->name('timdis.kegiatan.toggle');
    Route::delete('/kegiatan/{sesi}', [\App\Http\Controllers\Timdis\KegiatanController::class, 'destroy'])->name('timdis.kegiatan.destroy');
    Route::get('/absensi-persesi', [\App\Http\Controllers\Timdis\AbsensiSesiController::class, 'listSesi'])->name('timdis.absensi-persesi');
    Route::get('/monitoring-kegiatan', [\App\Http\Controllers\Timdis\AttendanceController::class, 'monitoringKegiatan'])->name('timdis.monitoring-kegiatan');
    Route::get('/monitoring-kegiatan/{id}', [\App\Http\Controllers\Timdis\AttendanceController::class, 'monitoringKegiatanDetail'])->name('timdis.monitoring-kegiatan.detail');
    Route::get('/monitoring-sesi/{sesi}', [\App\Http\Controllers\Timdis\AbsensiSesiController::class, 'monitoring'])->name('timdis.monitoring-sesi');
    Route::get('/izin-timdis', [TimdisController::class, 'izinTimdis'])->name('timdis.izin-timdis');
    Route::post('/izin/verify', [TimdisController::class, 'verifyIzin'])->name('timdis.izin.verify');
    Route::get('/kehadiran-timdis', [TimdisController::class, 'kehadiranTimdis'])->name('timdis.kehadiran-timdis');
    Route::post('/kehadiran/verify', [TimdisController::class, 'verifyKehadiran'])->name('timdis.kehadiran.verify');

    // Mahasiswa Management
    Route::get('/mahasiswa', [AdminController::class, 'mahasiswa'])->name('timdis.mahasiswa');
    Route::post('/mahasiswa', [AdminController::class, 'storeMahasiswa'])->name('timdis.mahasiswa.store');
    Route::put('/mahasiswa/{id}', [AdminController::class, 'updateMahasiswa'])->name('timdis.mahasiswa.update');
    Route::delete('/mahasiswa/{id}', [AdminController::class, 'deleteMahasiswa'])->name('timdis.mahasiswa.destroy');
    Route::get('/mahasiswa/import/template', [AdminController::class, 'downloadTemplateCSV'])->name('timdis.mahasiswa.import.template');
    Route::post('/mahasiswa/import', [AdminController::class, 'importMahasiswaCSV'])->name('timdis.mahasiswa.import');
    Route::get('/mahasiswa/{id}/qr', [AdminController::class, 'qrCode'])->name('timdis.mahasiswa.qr');
    Route::get('/mahasiswa/{id}/qr-json', [AdminController::class, 'getMahasiswaQR'])->name('timdis.mahasiswa.qr.json');
});

// ?????? ADMIN PAGES (Server-Side Rendered) ??????
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
    // Route::get('/kegiatan', [AdminController::class, 'kegiatan'])->name('admin.kegiatan'); // DISABLED - Using PKKMB Sesi now
    Route::get('/monitoring-kegiatan', [AdminController::class, 'monitoringKegiatan'])->name('admin.monitoring-kegiatan');
    Route::get('/monitoring-kegiatan/{id}', [AdminController::class, 'monitoringKegiatanDetail'])->name('admin.monitoring-kegiatan.detail');
    Route::get('/kelulusan', [AdminController::class, 'kelulusan'])->name('admin.kelulusan');
    Route::post('/sertifikat/toggle-lock/{id}', [AdminController::class, 'toggleSertifikatLock'])->name('admin.sertifikat.toggle-lock');
    Route::post('/sertifikat/bulk-toggle', [AdminController::class, 'bulkToggleSertifikatLock'])->name('admin.sertifikat.bulk-toggle');
    Route::get('/izin', [AdminController::class, 'izin'])->name('admin.izin');
    Route::get('/kehadiran', [AdminController::class, 'kehadiran'])->name('admin.kehadiran');

    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
    
    // PKKMB Schedule
    Route::get('/pkkmb-schedule', [\App\Http\Controllers\Admin\PkkmbScheduleController::class, 'index'])->name('admin.pkkmb-schedule.index');
    
    // Legacy: Jadwal Mingguan (DISABLED)
    // Route::get('/schedule', [AttendanceScheduleController::class, 'index'])->name('admin.schedule.index');
    
    // PKKMB Sesi Management (Kelola Kegiatan = Kelola Sesi PKKMB)
    Route::get('/kegiatan', [\App\Http\Controllers\Admin\PkkmbSesiController::class, 'index'])->name('admin.kegiatan');
    
    // Kegiatan Sesi Management (Legacy - for old kegiatan)
    Route::get('/kegiatan-legacy/{kegiatan}/sesi', [\App\Http\Controllers\Admin\KegiatanSesiController::class, 'index'])->name('admin.kegiatan-sesi.index');
    
    // Absensi Manual (for Garda)
    Route::get('/absensi-persesi', [\App\Http\Controllers\Admin\AbsensiManualController::class, 'listSesi'])->name('admin.absensi-persesi');
    Route::get('/absensi-manual/{sesi}', [\App\Http\Controllers\Admin\AbsensiManualController::class, 'index'])->name('admin.absensi-manual.index');
    Route::get('/monitoring-sesi/{sesi}', [\App\Http\Controllers\Admin\AbsensiManualController::class, 'monitoring'])->name('admin.monitoring-sesi');
    
    // Debug route
    Route::any('/schedule/test-route', function(\Illuminate\Http\Request $request) {
        return response()->json([
            'status' => 'Route works!',
            'method' => $request->method(),
            'auth' => Auth::check(),
            'user' => Auth::user()->username ?? 'guest',
            'role' => Auth::user()->role ?? 'none',
        ]);
    })->name('admin.schedule.test');

     // Redirect legacy dashboard URLs
     Route::get('/timdis/dashboard', fn() => redirect()->route('timdis.dashboard'));
     Route::get('/garda/dashboard', fn() => redirect()->route('garda.dashboard'));

    // Export
    Route::get('/attendance/export', [AdminController::class, 'exportAttendance'])->name('admin.attendance.export');
});

// ?????? ADMIN FORM ACTIONS (POST) ??????
Route::middleware(['auth', 'role:admin,timdis,garda'])->prefix('admin')->group(function () {

    
    // PKKMB Schedule CRUD
    Route::post('/pkkmb-schedule', [\App\Http\Controllers\Admin\PkkmbScheduleController::class, 'store'])->name('admin.pkkmb-schedule.store');
    Route::put('/pkkmb-schedule/{id}', [\App\Http\Controllers\Admin\PkkmbScheduleController::class, 'update'])->name('admin.pkkmb-schedule.update');
    Route::post('/pkkmb-schedule/{id}/toggle', [\App\Http\Controllers\Admin\PkkmbScheduleController::class, 'toggleActive'])->name('admin.pkkmb-schedule.toggle');
    Route::delete('/pkkmb-schedule/{id}', [\App\Http\Controllers\Admin\PkkmbScheduleController::class, 'destroy'])->name('admin.pkkmb-schedule.destroy');
    Route::post('/pkkmb-schedule/grace-period', [\App\Http\Controllers\Admin\PkkmbScheduleController::class, 'updateGracePeriod'])->name('admin.pkkmb-schedule.gracePeriod');
    
    // Keep this for backward compatibility (used by PKKMB schedule form)
    Route::post('/schedule/grace-period', [AttendanceScheduleController::class, 'updateGracePeriod'])->name('admin.schedule.gracePeriod');
    
    // PKKMB Sesi CRUD (Kelola Kegiatan)
    Route::post('/kegiatan', [\App\Http\Controllers\Admin\PkkmbSesiController::class, 'store'])->name('admin.kegiatan.store');
    Route::put('/kegiatan/{sesi}', [\App\Http\Controllers\Admin\PkkmbSesiController::class, 'update'])->name('admin.kegiatan.update');
    Route::post('/kegiatan/{sesi}/toggle', [\App\Http\Controllers\Admin\PkkmbSesiController::class, 'toggleActive'])->name('admin.kegiatan.toggle');
    Route::delete('/kegiatan/{sesi}', [\App\Http\Controllers\Admin\PkkmbSesiController::class, 'destroy'])->name('admin.kegiatan.destroy');
    
    // Legacy Kegiatan Sesi CRUD
    Route::post('/kegiatan-legacy/{kegiatan}/sesi', [\App\Http\Controllers\Admin\KegiatanSesiController::class, 'store'])->name('admin.kegiatan-sesi.store');
    Route::put('/kegiatan-legacy/{kegiatan}/sesi/{sesi}', [\App\Http\Controllers\Admin\KegiatanSesiController::class, 'update'])->name('admin.kegiatan-sesi.update');
    Route::post('/kegiatan-legacy/{kegiatan}/sesi/{sesi}/toggle', [\App\Http\Controllers\Admin\KegiatanSesiController::class, 'toggleActive'])->name('admin.kegiatan-sesi.toggle');
    Route::delete('/kegiatan-legacy/{kegiatan}/sesi/{sesi}', [\App\Http\Controllers\Admin\KegiatanSesiController::class, 'destroy'])->name('admin.kegiatan-sesi.destroy');
    
    // Absensi Manual Save
    Route::post('/absensi-manual/{sesi}', [\App\Http\Controllers\Admin\AbsensiManualController::class, 'store'])->name('admin.absensi-manual.store');
    
    // Test route untuk debugging
    Route::post('/test-post', function(\Illuminate\Http\Request $request) {
        Log::info('Test POST route called', [
            'user' => Auth::user()->username ?? 'guest',
            'all_input' => $request->all()
        ]);
        return response()->json(['success' => true, 'message' => 'POST route works!', 'user' => Auth::user()->username]);
    })->name('admin.test.post');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // Mahasiswa CRUD
    Route::post('/mahasiswa', [AdminController::class, 'storeMahasiswa'])->name('admin.mahasiswa.store');
    Route::put('/mahasiswa/{id}', [AdminController::class, 'updateMahasiswa'])->name('admin.mahasiswa.update');
    Route::delete('/mahasiswa/{id}', [AdminController::class, 'deleteMahasiswa'])->name('admin.mahasiswa.destroy');

    // Users CRUD
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::post('/users/{id}/activate', [AdminController::class, 'activateUser'])->name('admin.users.activate');
    Route::post('/users/{id}/deactivate', [AdminController::class, 'deactivateUser'])->name('admin.users.deactivate');
    Route::post('/users/{id}/reset-password', [AdminController::class, 'resetUserPassword'])->name('admin.users.resetPassword');

    // Settings
    Route::post('/settings/save', [AdminController::class, 'saveSettings'])->name('admin.settings.save');
    
    // Late Status Override
    Route::post('/attendance/override-late', [AdminController::class, 'overrideLateStatus'])->name('admin.attendance.overrideLate');
    Route::post('/attendance/{attendanceId}/cancel-override', [AdminController::class, 'cancelOverrideLateStatus'])->name('admin.attendance.cancelOverride');
    
    // Late Attendance Report
    Route::get('/late-report', [AdminController::class, 'lateAttendanceReport'])->name('admin.late-report');
    Route::get('/late-report/export', [AdminController::class, 'exportLateAttendanceReport'])->name('admin.late-report.export');

    // Legacy Kegiatan CRUD (DISABLED - Using PKKMB Sesi now)
    // Route::post('/kegiatan', [KegiatanController::class, 'store'])->name('admin.kegiatan.store');
    // Route::put('/kegiatan/{id}', [KegiatanController::class, 'update'])->name('admin.kegiatan.update');
    // Route::post('/kegiatan/{id}/toggle', [KegiatanController::class, 'toggleActive'])->name('admin.kegiatan.toggle');
    // Route::delete('/kegiatan/{id}', [KegiatanController::class, 'destroy'])->name('admin.kegiatan.destroy');

    // QR Code (JSON response for AJAX in case needed)
    Route::get('/mahasiswa/{id}/qr-json', [AdminController::class, 'getMahasiswaQR'])->name('admin.mahasiswa.qr.json');
});

// ─── MAHASISWA PAGES (Server-Side Rendered) ────────────────────────────────
Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->group(function () {
    Route::get('/dashboard', [MahasiswaController::class, 'dashboard'])->name('mahasiswa.dashboard');
    Route::get('/profile', [MahasiswaController::class, 'profile'])->name('mahasiswa.profile');
    Route::put('/profile', [MahasiswaController::class, 'updateProfileData'])->name('mahasiswa.profile.update');
    Route::post('/profile/photo', [MahasiswaController::class, 'uploadPhoto'])->name('mahasiswa.profile.photo');
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

// ─── MAHASISWA API (Sertifikat) ────────────────────────────────
Route::middleware(['auth', 'role:mahasiswa,admin'])->group(function () {
    Route::post('/api/mahasiswa/{id}/sertifikat/generate', [SertifikatController::class, 'generate']);
    Route::get('/api/mahasiswa/{id}/sertifikat/preview/pdf', [SertifikatController::class, 'previewPdf']);
    Route::get('/api/sertifikat/download/{historyId}', [SertifikatController::class, 'download']);
});

// ─── PUBLIC API (untuk Python Backend — langsung konek ke DB, tapi beberapa mungkin diperlukan) ──
Route::get('/api/kegiatan', function () {
    try {
        $kegiatans = \App\Models\Kegiatan::where('is_active', 1)->orderBy('tanggal_pelaksanaan', 'asc')->get(['id', 'nama', 'tanggal_pelaksanaan']);
        return response()->json([
            'success' => true,
            'data' => $kegiatans
        ]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// Handle CORS preflight for /api/sync
Route::options('/api/sync', function () {
    return response()->json([], 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');
});

Route::post('/api/sync', function (Request $request) {
    try {
        $data = $request->input('data', []);
        $syncedCount = 0;
        $rejectedCount = 0;
        $rejectionReasons = [];
        
        foreach ($data as $record) {
            if (!isset($record['mahasiswa_id'])) continue;
            
            $qrOrId = $record['mahasiswa_id'];
            $mahasiswa = Mahasiswa::where('qr_code_id', $qrOrId)->orWhere('id', $qrOrId)->first();
            
            if (!$mahasiswa) {
                $cleanId = str_replace(['QR-', '-'], '', $qrOrId);
                $mahasiswa = Mahasiswa::where('id', $cleanId)->first();
            }
            if (!$mahasiswa) {
                $rejectedCount++;
                $rejectionReasons[] = "Mahasiswa tidak ditemukan: {$qrOrId}";
                continue;
            }
            
            $mahasiswaId = $mahasiswa->id;
            
            $kegiatanId = $record['kegiatan_id'] ?? null;
            
            // Validate kegiatan_id if provided
            if ($kegiatanId) {
                $kegiatan = \App\Models\Kegiatan::find($kegiatanId);
                if (!$kegiatan) {
                    $rejectedCount++;
                    $rejectionReasons[] = "Kegiatan ID {$kegiatanId} tidak ditemukan (Mahasiswa: {$mahasiswa->name})";
                    Log::warning("Attendance sync rejected - Kegiatan not found", [
                        'mahasiswa_id' => $mahasiswaId,
                        'mahasiswa_name' => $mahasiswa->name,
                        'kegiatan_id' => $kegiatanId
                    ]);
                    continue;
                }
            }
            
            $kegiatanDate = Carbon::today()->format('Y-m-d');
            
            // For kegiatan-based attendance, bypass schedule validation
            if ($kegiatanId) {
                $kegiatan = \App\Models\Kegiatan::find($kegiatanId);
                if ($kegiatan) {
                    $kegiatanDate = Carbon::parse($kegiatan->tanggal_pelaksanaan)->format('Y-m-d');
                }
                
                $attendance = Attendance::where('mahasiswa_id', $mahasiswaId)
                    ->where('kegiatan_id', $kegiatanId)
                    ->first();
            } else {
                // For daily attendance, VALIDATE AGAINST PKKMB SCHEDULE
                $today = Carbon::today()->format('Y-m-d');
                $schedule = \App\Models\PkkmbSchedule::where('tanggal', $today)
                    ->where('is_active', 1)
                    ->first();
                
                // Reject if no schedule for today
                if (!$schedule) {
                    $rejectedCount++;
                    $rejectionReasons[] = "Tidak ada jadwal PKKMB untuk hari ini (Mahasiswa: {$mahasiswa->name})";
                    Log::warning("Attendance sync rejected - No PKKMB schedule for today", [
                        'mahasiswa_id' => $mahasiswaId,
                        'mahasiswa_name' => $mahasiswa->name,
                        'date' => $today
                    ]);
                    continue;
                }
                
                // Get grace period
                $gracePeriod = \App\Models\SystemConfig::getGracePeriodMinutes();
                
                // Validate check-in time if this is a check-in
                $checkIn = isset($record['check_in']) ? Carbon::parse($record['check_in']) : Carbon::now();
                $checkInTime = $checkIn->format('H:i:s');
                
                // Parse schedule times
                $checkInStart = Carbon::parse($schedule->check_in_start)->format('H:i:s');
                $checkInEnd = Carbon::parse($schedule->check_in_end)->format('H:i:s');
                $graceEndTime = Carbon::parse($schedule->check_in_end)->addMinutes($gracePeriod)->format('H:i:s');
                
                // Check if too early
                if ($checkInTime < $checkInStart) {
                    $rejectedCount++;
                    $rejectionReasons[] = "Check-in terlalu awal (Mahasiswa: {$mahasiswa->name}, Waktu: {$checkInTime}, Batas mulai: {$checkInStart})";
                    Log::warning("Attendance sync rejected - Too early", [
                        'mahasiswa_id' => $mahasiswaId,
                        'mahasiswa_name' => $mahasiswa->name,
                        'check_in_time' => $checkInTime,
                        'schedule_start' => $checkInStart
                    ]);
                    continue;
                }
                
                // Check if too late (after grace period)
                if ($checkInTime > $graceEndTime) {
                    $rejectedCount++;
                    $rejectionReasons[] = "Check-in terlambat melewati batas (Mahasiswa: {$mahasiswa->name}, Waktu: {$checkInTime}, Batas akhir: {$graceEndTime})";
                    Log::warning("Attendance sync rejected - Too late", [
                        'mahasiswa_id' => $mahasiswaId,
                        'mahasiswa_name' => $mahasiswa->name,
                        'check_in_time' => $checkInTime,
                        'grace_end_time' => $graceEndTime,
                        'grace_period' => $gracePeriod
                    ]);
                    continue;
                }
                
                // Determine if late
                $isLate = $checkInTime > $checkInEnd;
                $lateDuration = 0;
                if ($isLate) {
                    $start = Carbon::parse($checkInEnd);
                    $end = Carbon::parse($checkInTime);
                    $lateDuration = $start->diffInMinutes($end);
                }
                
                $attendance = Attendance::daily()
                    ->where('mahasiswa_id', $mahasiswaId)
                    ->where('date', $kegiatanDate)
                    ->first();
            }

            $checkIn = isset($record['check_in']) ? Carbon::parse($record['check_in'])->toDateTimeString() : null;
            $checkOut = isset($record['check_out']) ? Carbon::parse($record['check_out'])->toDateTimeString() : null;

            if ($attendance) {
                $updateData = [
                    'status' => 'hadir',
                    'check_in' => $attendance->check_in ?? $checkIn ?? Carbon::now()->toDateTimeString(),
                    'check_out' => $checkOut ?: $attendance->check_out,
                ];
                
                // Add late info - prioritize from Python backend, fallback to Laravel calculation
                if (!$kegiatanId) {
                    if (isset($record['is_late'])) {
                        // Use data from Python backend
                        $updateData['is_late'] = $record['is_late'];
                        $updateData['late_duration'] = $record['late_duration'] ?? 0;
                    } elseif (isset($isLate) && isset($lateDuration)) {
                        // Use Laravel calculation
                        $updateData['is_late'] = $isLate;
                        $updateData['late_duration'] = $lateDuration;
                    }
                }
                
                $attendance->update($updateData);
            } else if ($checkIn) {
                $createData = [
                    'mahasiswa_id' => $mahasiswaId,
                    'kegiatan_id' => $kegiatanId,
                    'date' => $kegiatanDate,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'status' => 'hadir',
                ];
                
                // Add late info - prioritize from Python backend, fallback to Laravel calculation
                if (!$kegiatanId) {
                    if (isset($record['is_late'])) {
                        // Use data from Python backend
                        $createData['is_late'] = $record['is_late'];
                        $createData['late_duration'] = $record['late_duration'] ?? 0;
                    } elseif (isset($isLate) && isset($lateDuration)) {
                        // Use Laravel calculation
                        $createData['is_late'] = $isLate;
                        $createData['late_duration'] = $lateDuration;
                    }
                }
                
                Attendance::create($createData);
            }
            $syncedCount++;
        }

        $message = "Synced {$syncedCount} records";
        if ($rejectedCount > 0) {
            $message .= ", rejected {$rejectedCount} records";
        }
        
        return response()->json([
            'success' => true, 
            'message' => $message,
            'synced_count' => $syncedCount,
            'rejected_count' => $rejectedCount,
            'rejection_reasons' => $rejectionReasons
        ])->header('Access-Control-Allow-Origin', '*')
          ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
          ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');
    }
});

// Auth API (untuk mahasiswa dashboard yang masih pakai JS)
Route::middleware(['auth'])->group(function () {
    Route::get('/api/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
});

// Fallback route untuk melayani file storage (mengatasi symlink error/403 Forbidden di Windows)
Route::get('/file-bukti/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    if (!file_exists($filePath)) {
        abort(404);
    }
    return response()->file($filePath);
})->where('path', '.*');
