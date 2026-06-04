<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Mahasiswa\IzinController;
use App\Http\Controllers\Mahasiswa\KehadiranController;
use App\Http\Controllers\Mahasiswa\MahasiswaController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Menangani path kosong (/) dengan kondisi pengecekan login
Route::get('/', function () {
    // Jika pengguna sudah login, arahkan ke dashboard masing-masing
    if (Auth::check()) {
        $role = Auth::user()->role;
        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'timdis' => redirect()->route('timdis.dashboard'),
            'mahasiswa' => redirect()->route('mahasiswa.dashboard'),
            default => redirect()->route('login'),
        };
    }
    
    // Kondisi jika pengguna BELUM login
    return redirect()->route('login');
});

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/auth/login', [AuthController::class, 'auth'])->name('auth');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    // Rute API untuk mengambil data user yang sedang login
    Route::get('/api/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
});

// Routes untuk Mahasiswa (hanya role mahasiswa)
Route::middleware(['auth', 'role:mahasiswa'])->group(function () {
    Route::get('/mahasiswa/dashboard', [MahasiswaController::class, 'dashboard'])->name('mahasiswa.dashboard');

    // Rute API Data Mahasiswa
    Route::get('/api/mahasiswa/{id}/statistics', [MahasiswaController::class, 'getStatistics']);
    Route::get('/api/mahasiswa/{id}/chart/weekly', [MahasiswaController::class, 'getWeeklyChart']);
    Route::get('/api/mahasiswa/{id}/chart/monthly', [MahasiswaController::class, 'getMonthlyChart']);
    Route::get('/api/mahasiswa/{id}/activity', [MahasiswaController::class, 'getRecentActivity']);

    // Rute untuk data mahasiswa
    Route::get('/api/mahasiswa/{id}', [MahasiswaController::class, 'getProfile']);
    Route::put('/api/mahasiswa/{id}', [MahasiswaController::class, 'updateProfile']);
    Route::get('/api/mahasiswa/{id}/riwayat', [MahasiswaController::class, 'getRiwayat']);
    Route::get('/api/mahasiswa/{id}/riwayat/export', [MahasiswaController::class, 'exportRiwayat']);

    // Rute untuk pengajuan izin mahasiswa
    Route::post('/api/izin/submit', [IzinController::class, 'submit']);
    Route::get('/api/izin/mahasiswa/{id}', [IzinController::class, 'history']);
    Route::get('/api/izin/bukti/{filename}', [IzinController::class, 'getBukti']);

    // Rute untuk kehadiran mahasiswa
    Route::post('/api/kehadiran/submit', [KehadiranController::class, 'submit']);
    Route::get('/api/kehadiran/mahasiswa/{id}', [KehadiranController::class, 'history']);
    Route::get('/api/kehadiran/bukti/{filename}', [App\Http\Controllers\Mahasiswa\KehadiranController::class, 'getBukti']);
});

// Routes untuk Admin & Timdis
Route::middleware(['auth', 'role:admin,timdis'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard_admin'])->name('admin.dashboard');
    
    // Perbaikan: Daftarkan route untuk timdis agar redirect saat login tidak error 500
    Route::get('/timdis/dashboard', [AdminController::class, 'dashboard_admin'])->name('timdis.dashboard');
    
    // Rute API Data Dashboard Admin
    Route::get('/api/dashboard', [AdminController::class, 'getDashboardData'])->name('api.dashboard.data');
    
    // Rute API Data Tabel (Absensi & Mahasiswa)
    Route::get('/api/attendance/today', [AdminController::class, 'getAttendanceToday']);
    Route::get('/api/attendance/history', [AdminController::class, 'getAttendanceHistory']);
    Route::get('/api/attendance/export', [AdminController::class, 'exportAttendance']);
    Route::get('/api/mahasiswa', [AdminController::class, 'getAllMahasiswa']);
    
    // Rute API Verifikasi Pengajuan Izin dan Kehadiran (Admin & Timdis)
    Route::get('/api/izin/list', [AdminController::class, 'getIzinSubmissions']);
    Route::post('/api/izin/verify', [AdminController::class, 'verifyIzin']);
    Route::get('/api/kehadiran/list', [AdminController::class, 'getKehadiranSubmissions']);
    Route::post('/api/kehadiran/verify', [AdminController::class, 'verifyKehadiran']);

    // CRUD Mahasiswa (Admin)
    Route::post('/api/mahasiswa', [AdminController::class, 'storeMahasiswa']);
    Route::delete('/api/mahasiswa/{id}', [AdminController::class, 'deleteMahasiswa']);
    Route::get('/api/mahasiswa/{id}/qr', [AdminController::class, 'getMahasiswaQR']);

    // CRUD Users Management (Admin)
    Route::get('/api/users', [AdminController::class, 'getAllUsers']);
    Route::post('/api/users', [AdminController::class, 'storeUser']);
    Route::put('/api/users/{id}', [AdminController::class, 'updateUser']);
    Route::post('/api/users/{id}/activate', [AdminController::class, 'activateUser']);
    Route::post('/api/users/{id}/deactivate', [AdminController::class, 'deactivateUser']);
    Route::post('/api/users/{id}/reset-password', [AdminController::class, 'resetUserPassword']);

    // CRUD Kamera RTSP (Admin)
    Route::get('/api/cameras', [AdminController::class, 'getCameras']);
    Route::get('/api/cameras/available', [AdminController::class, 'getAvailableWebcams']);
    Route::post('/api/cameras', [AdminController::class, 'storeCamera']);
    Route::put('/api/cameras/{id}', [AdminController::class, 'updateCamera']);
    Route::delete('/api/cameras/{id}', [AdminController::class, 'deleteCamera']);
});