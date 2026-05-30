<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Mahasiswa\IzinController;
use App\Http\Controllers\Mahasiswa\KehadiranController;
use App\Http\Controllers\Mahasiswa\MahasiswaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/auth/login', [AuthController::class, 'auth'])->name('auth');
});

// Route default '/' (Akan otomatis mengecek role user yang login)
Route::get('/', function () {
    if (Illuminate\Support\Facades\Auth::check()) {
        $role = Illuminate\Support\Facades\Auth::user()->role;
        if ($role === 'admin') return redirect()->route('admin.dashboard');
        if ($role === 'timdis') return redirect()->route('timdis.dashboard');
        if ($role === 'mahasiswa') return redirect()->route('mahasiswa.dashboard');
    }
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    // Rute API untuk mengambil data user yang sedang login
    Route::get('/api/auth/me', [AuthController::class, 'me'])->name('api.auth.me');

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

    // Rute URL Khusus Admin (/admin/...)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard_admin'])->name('admin.dashboard');
        
        // Routes API User Management
        Route::get('/admin/users', [AdminController::class, 'getUsers'])->name('admin.users.get');
        Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::put('/admin/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::post('/admin/users/{id}/reset-password', [AdminController::class, 'resetUserPassword'])->name('admin.users.reset-password');
        Route::post('/admin/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('admin.users.toggle-status');
        Route::delete('/admin/users/{id}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
        Route::get('/admin/mahasiswa-options', [AdminController::class, 'getMahasiswaOptions'])->name('admin.mahasiswa-options');
    });

    // Rute URL Khusus Timdis (/timdis/...)
    Route::middleware(['role:timdis'])->group(function () {
        Route::get('/timdis/dashboard', [AdminController::class, 'dashboard_admin'])->name('timdis.dashboard');
    });
        
    // Rute API AJAX (Dapat diakses oleh Admin & Timdis)
    Route::middleware(['role:admin,timdis'])->group(function () {
        // Routes Verifikasi Izin
        Route::get('/admin/izin-submissions', [AdminController::class, 'getIzinSubmissions'])->name('admin.izin.get');
        Route::post('/admin/izin-submissions/{id}/verify', [AdminController::class, 'verifyIzin'])->name('admin.izin.verify');
        
        // Routes Verifikasi Kehadiran
        Route::get('/admin/kehadiran-submissions', [AdminController::class, 'getKehadiranSubmissions'])->name('admin.kehadiran.get');
        Route::post('/admin/kehadiran-submissions/{id}/verify', [AdminController::class, 'verifyKehadiran'])->name('admin.kehadiran.verify');

        // Route Upload Video
        Route::post('/admin/upload-video', [AdminController::class, 'uploadVideo'])->name('admin.upload.video');
    });
    
    // Rute URL Khusus Mahasiswa (/mahasiswa/...)
    Route::middleware(['role:mahasiswa'])->group(function () {
        Route::get('/mahasiswa/dashboard', [MahasiswaController::class, 'dashboard'])->name('mahasiswa.dashboard');
    });

});