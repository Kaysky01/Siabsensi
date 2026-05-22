<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
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

    // Rute URL Khusus Admin (/admin/...)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'dashboard_admin'])->name('admin.dashboard');
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
        
        // Route Upload Video
        Route::post('/admin/upload-video', [AdminController::class, 'uploadVideo'])->name('admin.upload.video');
    });
    
    // Rute URL Khusus Mahasiswa (/mahasiswa/...)
    Route::middleware(['role:mahasiswa'])->group(function () {
        Route::get('/mahasiswa/dashboard', [MahasiswaController::class, 'dashboard'])->name('mahasiswa.dashboard');
    });

});