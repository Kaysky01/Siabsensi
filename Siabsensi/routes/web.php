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
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Timdis\TimdisController;

// ?????? ROOT REDIRECT ????????????????????????????????????????????????????????????????????????????????????????????????????????????????
Route::get('/', function () {
    if (Auth::check()) {
        $role = Auth::user()->role;
        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'timdis' => redirect()->route('timdis.dashboard'),
            'garda' => redirect()->route('garda.dashboard'),
            'acara' => redirect()->route('acara.dashboard'),
            'mahasiswa' => redirect()->route('mahasiswa.dashboard'),
            default => redirect()->route('login'),
        };
    }
    return redirect()->route('login');
});

// Maintenance Mode Page
Route::get('/maintenance', function () {
    return view('errors.maintenance');
})->name('maintenance');

// ─── AUTH ────────────────────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'auth'])->name('auth')->middleware('login.ratelimit');

Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
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
    Route::get('/kompi-saya', [\App\Http\Controllers\Garda\KompiSayaController::class, 'index'])->name('garda.kompi-saya');
    Route::post('/kompi-saya/announcement', [\App\Http\Controllers\Garda\KompiSayaController::class, 'saveAnnouncement'])->name('garda.kompi-saya.announcement');
    Route::get('/riwayat', [RiwayatController::class, '__invoke'])->name('garda.riwayat');
    Route::get('/profile', [ProfileController::class, 'profile'])->name('garda.profile');
    Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('garda.profile.update');
    Route::get('/izin', [VerificationController::class, 'izin'])->name('garda.izin');
    Route::post('/izin/verify', [VerificationController::class, 'verifyIzin'])->name('garda.izin.verify');
    Route::get('/kehadiran-manual', [VerificationController::class, 'kehadiranManual'])->name('garda.kehadiran-manual');
    Route::post('/kehadiran/verify', [VerificationController::class, 'verifyKehadiran'])->name('garda.kehadiran.verify');
});

// ─── ACARA PAGES ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:acara'])->prefix('acara')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Acara\AcaraController::class, 'dashboard'])->name('acara.dashboard');
    Route::get('/pkkmb-schedule', [\App\Http\Controllers\Acara\PkkmbScheduleController::class, 'index'])->name('acara.pkkmb-schedule.index');
    Route::post('/pkkmb-schedule', [\App\Http\Controllers\Acara\PkkmbScheduleController::class, 'store'])->name('acara.pkkmb-schedule.store');
    Route::put('/pkkmb-schedule/{id}', [\App\Http\Controllers\Acara\PkkmbScheduleController::class, 'update'])->name('acara.pkkmb-schedule.update');
    Route::post('/pkkmb-schedule/{id}/toggle', [\App\Http\Controllers\Acara\PkkmbScheduleController::class, 'toggleActive'])->name('acara.pkkmb-schedule.toggle');
    Route::delete('/pkkmb-schedule/{id}', [\App\Http\Controllers\Acara\PkkmbScheduleController::class, 'destroy'])->name('acara.pkkmb-schedule.destroy');
    Route::post('/pkkmb-schedule/grace-period', [\App\Http\Controllers\Acara\PkkmbScheduleController::class, 'updateGracePeriod'])->name('acara.pkkmb-schedule.gracePeriod');

    Route::get('/kegiatan', [\App\Http\Controllers\Acara\PkkmbSesiController::class, 'index'])->name('acara.kegiatan');
    Route::post('/kegiatan', [\App\Http\Controllers\Acara\PkkmbSesiController::class, 'store'])->name('acara.kegiatan.store');
    Route::put('/kegiatan/{sesi}', [\App\Http\Controllers\Acara\PkkmbSesiController::class, 'update'])->name('acara.kegiatan.update');
    Route::post('/kegiatan/{sesi}/toggle', [\App\Http\Controllers\Acara\PkkmbSesiController::class, 'toggleActive'])->name('acara.kegiatan.toggle');
    Route::delete('/kegiatan/{sesi}', [\App\Http\Controllers\Acara\PkkmbSesiController::class, 'destroy'])->name('acara.kegiatan.destroy');
});

// ─── TIMDIS PAGES ───────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:timdis'])->prefix('timdis')->group(function () {
    Route::get('/dashboard', [TimdisController::class, 'dashboard'])->name('timdis.dashboard');
    Route::get('/kompi-saya', [\App\Http\Controllers\Timdis\KompiSayaController::class, 'index'])->name('timdis.kompi-saya');
    Route::get('/attendance', [\App\Http\Controllers\Timdis\AttendanceController::class, 'attendance'])->name('timdis.attendance');
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
    Route::get('/mahasiswa/{id}/attendance-detail', [AdminController::class, 'getAttendanceDetail'])->name('timdis.mahasiswa.attendance-detail');
    Route::post('/mahasiswa/export/load', [AdminController::class, 'loadExportStudents'])->name('timdis.mahasiswa.export.load');
    Route::post('/mahasiswa/export/process', [AdminController::class, 'processExportMahasiswa'])->name('timdis.mahasiswa.export.process');
    Route::post('/mahasiswa', [AdminController::class, 'storeMahasiswa'])->name('timdis.mahasiswa.store');
    Route::put('/mahasiswa/{id}', [AdminController::class, 'updateMahasiswa'])->name('timdis.mahasiswa.update');
    Route::delete('/mahasiswa/{id}', [AdminController::class, 'deleteMahasiswa'])->name('timdis.mahasiswa.destroy');
    Route::get('/mahasiswa/import/template', [AdminController::class, 'downloadTemplateCSV'])->name('timdis.mahasiswa.import.template');
    Route::post('/mahasiswa/import', [AdminController::class, 'importMahasiswaCSV'])->name('timdis.mahasiswa.import');
    Route::get('/mahasiswa/{id}/qr', [AdminController::class, 'qrCode'])->name('timdis.mahasiswa.qr');
    Route::get('/mahasiswa/{id}/qr-json', [AdminController::class, 'getMahasiswaQR'])->name('timdis.mahasiswa.qr.json');
});

// ?????? ADMIN PAGES (Server-Side Rendered) ??????
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard_admin'])->name('admin.dashboard');
    Route::post('/maintenance-mode/toggle', [AdminController::class, 'toggleMaintenanceMode'])->name('admin.maintenance.toggle');
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
    Route::get('/mahasiswa/{id}/attendance-detail', [AdminController::class, 'getAttendanceDetail'])->name('admin.mahasiswa.attendance-detail');
    Route::delete('/attendance-record/{id}', [AdminController::class, 'deleteAttendanceRecord'])->name('admin.attendance.record.destroy');
    Route::post('/mahasiswa/export/load', [AdminController::class, 'loadExportStudents'])->name('admin.mahasiswa.export.load');
    Route::post('/mahasiswa/export/process', [AdminController::class, 'processExportMahasiswa'])->name('admin.mahasiswa.export.process');
    Route::get('/mahasiswa/import/template', [AdminController::class, 'downloadTemplateCSV'])->name('admin.mahasiswa.import.template');
    Route::post('/mahasiswa/import', [AdminController::class, 'importMahasiswaCSV'])->name('admin.mahasiswa.import');
    Route::get('/mahasiswa/{id}/qr', [AdminController::class, 'qrCode'])->name('admin.mahasiswa.qr');
    Route::get('/mahasiswa-saya', [AdminController::class, 'mahasiswaSaya'])->name('admin.mahasiswa-saya');
    Route::get('/kompi-management', [AdminController::class, 'kompiManagement'])->name('admin.kompi-management');
    Route::get('/kompi-management/download', [AdminController::class, 'downloadKompiData'])->name('admin.kompi.download');
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
    Route::post('/izin/verify', [AdminController::class, 'verifyIzin'])->name('admin.izin.verify');
    Route::delete('/izin/{id}', [AdminController::class, 'deleteIzinSubmission'])->name('admin.izin.destroy');
    Route::get('/kehadiran', [AdminController::class, 'kehadiran'])->name('admin.kehadiran');
    Route::post('/kehadiran/verify', [AdminController::class, 'verifyKehadiran'])->name('admin.kehadiran.verify');
    Route::delete('/kehadiran/{id}', [AdminController::class, 'deleteKehadiranSubmission'])->name('admin.kehadiran.destroy');

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

     // Redirect legacy dashboard URLs
     Route::get('/timdis/dashboard', fn() => redirect()->route('timdis.dashboard'));
     Route::get('/garda/dashboard', fn() => redirect()->route('garda.dashboard'));

    // Export
    Route::get('/attendance/export', [AdminController::class, 'exportAttendance'])->name('admin.attendance.export');
});

// ?????? ADMIN FORM ACTIONS (POST) ??????
Route::middleware(['auth', 'role:admin,timdis,garda,acara'])->prefix('admin')->group(function () {

    
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
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->name('admin.users.destroy');

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
    Route::delete('/izin/{id}', [MahasiswaController::class, 'deleteIzin'])->name('mahasiswa.izin.delete');
    
    Route::get('/kehadiran', [MahasiswaController::class, 'kehadiran'])->name('mahasiswa.kehadiran');
    Route::post('/kehadiran', [MahasiswaController::class, 'submitKehadiran'])->name('mahasiswa.kehadiran.submit');
    Route::delete('/kehadiran/{id}', [MahasiswaController::class, 'deleteKehadiran'])->name('mahasiswa.kehadiran.delete');

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
        if (empty($data)) {
            return response()->json([
                'success' => true,
                'message' => 'Synced 0 records',
                'synced_count' => 0,
                'rejected_count' => 0,
                'rejection_reasons' => []
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Accept');
        }

        // 1. Pre-fetch all students in bulk to eliminate N+1 queries
        $mahasiswaIdMap = [];
        $mahasiswaQrMap = [];
        $incomingIds = [];

        foreach ($data as $r) {
            if (isset($r['mahasiswa_id'])) {
                $rawId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $r['mahasiswa_id']);
                $incomingIds[] = $rawId;
                $cleanId = preg_replace('/^(QR-|-)/i', '', $rawId);
                if ($cleanId !== $rawId) {
                    $incomingIds[] = $cleanId;
                }
            }
        }
        $incomingIds = array_values(array_unique(array_filter($incomingIds)));

        if (!empty($incomingIds)) {
            $mhsCollection = Mahasiswa::whereIn('id', $incomingIds)
                ->orWhereIn('qr_code_id', $incomingIds)
                ->get(['id', 'name', 'qr_code_id']);

            foreach ($mhsCollection as $mhs) {
                $mahasiswaIdMap[(string)$mhs->id] = $mhs;
                if ($mhs->qr_code_id) {
                    $mahasiswaQrMap[(string)$mhs->qr_code_id] = $mhs;
                }
            }
        }

        // 2. Pre-fetch active schedules mapped by date Y-m-d
        $schedules = \App\Models\PkkmbSchedule::where('is_active', 1)->get();
        $scheduleMap = [];
        foreach ($schedules as $sched) {
            $dateKey = Carbon::parse($sched->tanggal)->format('Y-m-d');
            $scheduleMap[$dateKey] = $sched;
        }

        // 3. Pre-fetch active kegiatan mapped by id
        $kegiatanMap = \App\Models\Kegiatan::all(['id', 'nama', 'tanggal_pelaksanaan'])->keyBy('id');

        // 4. Pre-fetch existing attendance records for students in this batch
        $matchedMahasiswaIds = array_unique(array_merge(
            array_keys($mahasiswaIdMap),
            array_map(fn($m) => $m->id, array_values($mahasiswaQrMap))
        ));

        $existingAttendanceMap = [];
        if (!empty($matchedMahasiswaIds)) {
            $existingAtts = Attendance::whereIn('mahasiswa_id', $matchedMahasiswaIds)->get();
            foreach ($existingAtts as $att) {
                $kId = $att->kegiatan_id ?? 'daily';
                $key = $att->mahasiswa_id . '_' . $kId . '_' . $att->date;
                $existingAttendanceMap[$key] = $att;
            }
        }

        $syncedCount = 0;
        $rejectedCount = 0;
        $rejectionReasons = [];

        DB::transaction(function () use (
            $data,
            $mahasiswaIdMap,
            $mahasiswaQrMap,
            $scheduleMap,
            $kegiatanMap,
            &$existingAttendanceMap,
            &$syncedCount,
            &$rejectedCount,
            &$rejectionReasons
        ) {
            $today = Carbon::today()->format('Y-m-d');

            foreach ($data as $record) {
                if (!isset($record['mahasiswa_id'])) continue;

                $qrOrId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $record['mahasiswa_id']);
                
                // Fast in-memory lookup
                $mahasiswa = $mahasiswaIdMap[$qrOrId] 
                    ?? ($mahasiswaQrMap[$qrOrId] ?? null);

                if (!$mahasiswa) {
                    $cleanId = preg_replace('/^(QR-|-)/i', '', $qrOrId);
                    $mahasiswa = $mahasiswaIdMap[$cleanId] 
                        ?? ($mahasiswaQrMap[$cleanId] ?? null);
                }

                if (!$mahasiswa) {
                    $rejectedCount++;
                    $rejectionReasons[] = "Mahasiswa tidak ditemukan: {$qrOrId}";
                    continue;
                }

                $mahasiswaId = $mahasiswa->id;
                $kegiatanId = $record['kegiatan_id'] ?? null;

                if ($kegiatanId && !isset($kegiatanMap[$kegiatanId])) {
                    $rejectedCount++;
                    $rejectionReasons[] = "Kegiatan ID {$kegiatanId} tidak ditemukan (Mahasiswa: {$mahasiswa->name})";
                    continue;
                }

                // Determine attendance date correctly
                if ($kegiatanId) {
                    $kegiatan = $kegiatanMap[$kegiatanId];
                    $kegiatanDate = Carbon::parse($kegiatan->tanggal_pelaksanaan)->format('Y-m-d');
                } else {
                    if (!empty($record['date'])) {
                        $kegiatanDate = Carbon::parse($record['date'])->format('Y-m-d');
                    } elseif (!empty($record['check_in'])) {
                        $kegiatanDate = Carbon::parse($record['check_in'])->format('Y-m-d');
                    } elseif (!empty($record['check_out'])) {
                        $kegiatanDate = Carbon::parse($record['check_out'])->format('Y-m-d');
                    } else {
                        $kegiatanDate = $today;
                    }
                }

                $checkIn = isset($record['check_in']) ? Carbon::parse($record['check_in'])->toDateTimeString() : null;
                $checkOut = isset($record['check_out']) ? Carbon::parse($record['check_out'])->toDateTimeString() : null;

                // Calculate late status if not supplied
                $isLate = false;
                $lateDuration = 0;
                if (!$kegiatanId && $checkIn) {
                    $sched = $scheduleMap[$kegiatanDate] ?? ($scheduleMap[$today] ?? null);
                    if ($sched) {
                        $checkInObj = Carbon::parse($checkIn);
                        $checkInTime = $checkInObj->format('H:i:s');
                        $checkInEnd = Carbon::parse($sched->check_in_end)->format('H:i:s');
                        if ($checkInTime > $checkInEnd) {
                            $isLate = true;
                            $start = Carbon::parse($checkInEnd);
                            $lateDuration = $start->diffInMinutes($checkInObj);
                        }
                    }
                }

                $attKey = $mahasiswaId . '_' . ($kegiatanId ?? 'daily') . '_' . $kegiatanDate;
                $attendance = $existingAttendanceMap[$attKey] ?? null;

                if ($attendance) {
                    // Preserve manual permissions ('sakit' / 'izin') unless complete check-in & check-out scans exist
                    if (in_array($attendance->status, ['sakit', 'izin']) && !($checkIn && $checkOut)) {
                        // Preserved
                    } else {
                        $updateData = [];
                        if ($checkIn) {
                            $updateData['status'] = 'hadir';
                            $updateData['check_in'] = $attendance->check_in ? min($attendance->check_in, $checkIn) : $checkIn;
                        }
                        if ($checkOut) {
                            $updateData['status'] = 'hadir';
                            $updateData['check_out'] = $checkOut;
                        }

                        if (!$kegiatanId) {
                            if (isset($record['is_late'])) {
                                $updateData['is_late'] = $record['is_late'];
                                $updateData['late_duration'] = $record['late_duration'] ?? 0;
                            } elseif ($isLate) {
                                $updateData['is_late'] = true;
                                $updateData['late_duration'] = $lateDuration;
                            }
                        }

                        if (!empty($updateData)) {
                            $attendance->update($updateData);
                        }
                    }
                } else if ($checkIn || $checkOut) {
                    $createData = [
                        'mahasiswa_id' => $mahasiswaId,
                        'kegiatan_id'  => $kegiatanId,
                        'date'         => $kegiatanDate,
                        'check_in'     => $checkIn ?? Carbon::now()->toDateTimeString(),
                        'check_out'    => $checkOut,
                        'status'       => 'hadir',
                    ];

                    if (!$kegiatanId) {
                        if (isset($record['is_late'])) {
                            $createData['is_late'] = $record['is_late'];
                            $createData['late_duration'] = $record['late_duration'] ?? 0;
                        } else {
                            $createData['is_late'] = $isLate;
                            $createData['late_duration'] = $lateDuration;
                        }
                    }

                    $newAtt = Attendance::create($createData);
                    $existingAttendanceMap[$attKey] = $newAtt;
                }

                $syncedCount++;
            }
        });

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
        Log::error("Sync error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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

// ─── EXTERNAL SYNC API ROUTES (Untuk Web Lain / Landing Page) ─────────────
Route::get('/api/pkkmb/absensi', [\App\Http\Controllers\Api\SyncController::class, 'pkkmbAbsensi']);

// ─── PYTHON & GENERAL SYNC API ROUTES ──────────────────────────────────────
// Routes untuk Python backend / external service menarik data dari Laravel
Route::prefix('api/sync')->group(function () {
    Route::get('/pkkmb-absensi', [\App\Http\Controllers\Api\SyncController::class, 'pkkmbAbsensi']);
    Route::get('/mahasiswa', [\App\Http\Controllers\Api\SyncController::class, 'mahasiswa']);
    Route::get('/schedules', [\App\Http\Controllers\Api\SyncController::class, 'schedules']);
    Route::get('/kegiatan', [\App\Http\Controllers\Api\SyncController::class, 'kegiatan']);
    Route::get('/system-config', [\App\Http\Controllers\Api\SyncController::class, 'systemConfig']);
    Route::get('/attendance', [\App\Http\Controllers\Api\SyncController::class, 'attendance']);
    Route::get('/status', [\App\Http\Controllers\Api\SyncController::class, 'status']);
});


