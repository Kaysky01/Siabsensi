<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Mahasiswa\IzinController;
use App\Http\Controllers\Mahasiswa\KehadiranController;
use App\Http\Controllers\Mahasiswa\MahasiswaController;
use App\Http\Controllers\SertifikatController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

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

// Monitor Absensi (Public - untuk display di layar besar)
Route::get('/monitor', function () {
    return view('monitor');
});

// API untuk Monitor (Public - tanpa auth) - menggunakan endpoint khusus monitor
Route::get('/api/monitor/cameras', [AdminController::class, 'getCameras']);
Route::get('/api/monitor/attendance/today', [AdminController::class, 'getAttendanceToday']);

// API untuk Python Backend (Proxy ke port 5000)
Route::get('/api/python/status', function () {
    try {
        $response = \Illuminate\Support\Facades\Http::get('http://127.0.0.1:5000/api/python/status');
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Python backend tidak tersedia'], 503);
    }
});

Route::post('/api/python/detect', function (\Illuminate\Http\Request $request) {
    try {
        // Forward raw body (mencegah memory limit pada base64 payload besar)
        $response = \Illuminate\Support\Facades\Http::withBody($request->getContent(), 'application/json')->post('http://127.0.0.1:5000/api/python/detect');
        return response()->json($response->json(), $response->status());
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Python backend tidak tersedia'], 503);
    }
});

Route::post('/api/python/attendance', function (\Illuminate\Http\Request $request) {
    try {
        $mahasiswaId = $request->input('mahasiswa_id');
        $status = $request->input('status', 'present');
        
        if (!$mahasiswaId) {
            return response()->json(['success' => false, 'message' => 'Data mahasiswa_id tidak boleh kosong.'], 400);
        }

        // Cek apakah mahasiswa valid untuk menghindari Database Constraint Error (500)
        $mahasiswa = \App\Models\Mahasiswa::find($mahasiswaId);
        if (!$mahasiswa) {
            return response()->json(['success' => false, 'message' => 'Mahasiswa tidak ditemukan di database.'], 404);
        }

        // Record attendance directly in Laravel
        $attendance = \App\Models\Attendance::where('mahasiswa_id', $mahasiswaId)
            ->where('date', \Carbon\Carbon::today()->format('Y-m-d'))
            ->first();
        
        if ($attendance) {
            // Update existing attendance
            $attendance->update([
                'status' => $status,
                'check_in' => $attendance->check_in ?? \Carbon\Carbon::now()->toDateTimeString(),
                'check_out' => $status === 'present' ? \Carbon\Carbon::now()->toDateTimeString() : null,
            ]);
        } else {
            // Create new attendance
            \App\Models\Attendance::create([
                'mahasiswa_id' => $mahasiswaId,
                'date' => \Carbon\Carbon::today()->format('Y-m-d'),
                'status' => $status,
                'check_in' => \Carbon\Carbon::now()->toDateTimeString(),
                'check_out' => null,
                'created_at' => \Carbon\Carbon::now()->toDateTimeString(),
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to record attendance: ' . $e->getMessage()
        ], 500);
    }
});

// API untuk Models (Public - untuk browse models folder)
Route::get('/api/models/list', function () {
    $modelsDir = base_path('models');
    $models = [];

    if (is_dir($modelsDir)) {
        $files = scandir($modelsDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'pt') {
                $filePath = $modelsDir . '/' . $file;
                $models[] = [
                    'name' => $file,
                    'path' => 'models/' . $file,
                    'size' => formatBytes(filesize($filePath))
                ];
            }
        }
    }

    return response()->json([
        'success' => true,
        'data' => $models
    ]);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    // Rute API untuk mengambil data user yang sedang login
    Route::get('/api/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
});

// Routes untuk Admin Only (bukan timdis)
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Settings RTSP (Admin Only)
    Route::post('/api/settings/rtsp', [AdminController::class, 'saveRtspSettings']);
    // Settings YOLO (Admin Only)
    Route::post('/api/settings/yolo', [AdminController::class, 'saveYoloSettings']);
});

// Routes untuk Mahasiswa (hanya role mahasiswa)
Route::middleware(['auth', 'role:mahasiswa'])->group(function () {
    Route::get('/mahasiswa/dashboard', [MahasiswaController::class, 'dashboard'])->name('mahasiswa.dashboard');

    // Rute API Data Mahasiswa
    Route::get('/api/mahasiswa/{id}/statistics', [MahasiswaController::class, 'getStatistics']);
    Route::get('/api/mahasiswa/{id}/chart/weekly', [MahasiswaController::class, 'getWeeklyChart']);
    Route::get('/api/mahasiswa/{id}/chart/monthly', [MahasiswaController::class, 'getMonthlyChart']);
    Route::get('/api/mahasiswa/{id}/activity', [MahasiswaController::class, 'getRecentActivity']);
    Route::get('/api/mahasiswa/{id}/today-attendance', [MahasiswaController::class, 'getTodayAttendanceStatus']);

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

    // Sertifikat (Mahasiswa)
    Route::post('/api/mahasiswa/{mahasiswaId}/sertifikat/preview', [SertifikatController::class, 'preview']);
    Route::post('/api/mahasiswa/{mahasiswaId}/sertifikat/preview-image', [SertifikatController::class, 'previewImage']);
    Route::post('/api/mahasiswa/{mahasiswaId}/sertifikat/generate', [SertifikatController::class, 'generate']);
    Route::post('/api/mahasiswa/{mahasiswaId}/sertifikat/preview-pdf', [SertifikatController::class, 'previewPdf']);
    Route::get('/api/mahasiswa/{mahasiswaId}/sertifikat/history', [SertifikatController::class, 'history']);
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

    // Sertifikat (Admin - untuk download)
    Route::get('/api/sertifikat/download/{historyId}', [SertifikatController::class, 'download']);

});
