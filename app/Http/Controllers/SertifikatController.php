<?php

namespace App\Http\Controllers;

use App\Models\Mahasiswa;
use App\Models\SertifikatHistory;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SertifikatController extends Controller
{
    public function preview(Request $request, $mahasiswaId)
    {
        $mahasiswa = Mahasiswa::find($mahasiswaId);
        
        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'
            ], 404);
        }

        $periode = $request->input();
        $startDate = null;
        $endDate = null;

        // Default to weekly period (1 week)
        if (isset($periode['type']) && $periode['type'] === 'weekly') {
            $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
        } elseif (isset($periode['type']) && $periode['type'] === 'custom') {
            $startDate = $periode['startDate'];
            $endDate = $periode['endDate'];
        } elseif (isset($periode['type']) && $periode['type'] === 'monthly') {
            $month = $periode['month'];
            $year = $periode['year'];
            $startDate = Carbon::create($year, $month, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');
        } elseif (isset($periode['type']) && $periode['type'] === 'yearly') {
            $year = $periode['year'];
            $startDate = Carbon::create($year, 1, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, 12, 31)->format('Y-m-d');
        } else {
            // Default to weekly if no type specified
            $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
        }

        if (!$startDate || !$endDate) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak valid'
            ], 400);
        }

        $alphaCount = $mahasiswa->calculateAlphaCount($startDate, $endDate);
        $canGetCertificate = $mahasiswa->canGetCertificate($startDate, $endDate);
        
        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $attendanceCount = $mahasiswa->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['present', 'izin', 'hadir'])
            ->count();
        
        $izinCount = $mahasiswa->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'izin')
            ->count();

        $persentase = $totalDays > 0 ? round(($attendanceCount / $totalDays) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'totalHadir' => $attendanceCount,
                'totalIzin' => $izinCount,
                'totalHari' => $totalDays,
                'persentase' => $persentase,
                'alphaCount' => $alphaCount,
                'canGetCertificate' => $canGetCertificate
            ]
        ]);
    }

    public function generate(Request $request, $mahasiswaId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'admin' && $user->mahasiswa_id != $mahasiswaId) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $mahasiswa = Mahasiswa::find($mahasiswaId);
        
        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'
            ], 404);
        }

        $periode = $request->input();
        $startDate = null;
        $endDate = null;

        // Default to weekly period (1 week)
        if (isset($periode['type']) && $periode['type'] === 'weekly') {
            $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
        } elseif (isset($periode['type']) && $periode['type'] === 'custom') {
            $startDate = $periode['startDate'];
            $endDate = $periode['endDate'];
        } elseif (isset($periode['type']) && $periode['type'] === 'monthly') {
            $month = $periode['month'];
            $year = $periode['year'];
            $startDate = Carbon::create($year, $month, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');
        } elseif (isset($periode['type']) && $periode['type'] === 'yearly') {
            $year = $periode['year'];
            $startDate = Carbon::create($year, 1, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, 12, 31)->format('Y-m-d');
        } else {
            // Default to weekly if no type specified
            $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
        }

        if (!$startDate || !$endDate) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak valid'
            ], 400);
        }

        $canGetCertificate = $mahasiswa->canGetCertificate($startDate, $endDate);
        
        if (!$canGetCertificate) {
            $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
            $attendanceCount = $mahasiswa->attendances()
                ->whereBetween('date', [$startDate, $endDate])
                ->whereIn('status', ['present', 'izin', 'hadir'])
                ->count();
            $persentase = $totalDays > 0 ? round(($attendanceCount / $totalDays) * 100, 2) : 0;
            
            return response()->json([
                'success' => false,
                'message' => "Mahasiswa tidak dapat mendapatkan sertifikat karena kehadiran kurang dari 80% (saat ini {$persentase}%)"
            ], 400);
        }

        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $attendanceCount = $mahasiswa->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['present', 'izin', 'hadir'])
            ->count();
        
        $persentase = $totalDays > 0 ? round(($attendanceCount / $totalDays) * 100, 2) : 0;

        $sertifikat = SertifikatHistory::create([
            'mahasiswa_id' => $mahasiswa->id,
            'periode' => "{$startDate} s/d {$endDate}",
            'template' => 'sertifikat',
            'total_hadir' => $attendanceCount,
            'persentase' => $persentase
        ]);

        $pngContent = $this->generateCertificatePng($mahasiswa);

        return response($pngContent)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="sertifikat_' . $mahasiswa->id . '_' . time() . '.png"');
    }

    public function previewImage(Request $request, $mahasiswaId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'admin' && $user->mahasiswa_id != $mahasiswaId) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $mahasiswa = Mahasiswa::find($mahasiswaId);
        
        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'
            ], 404);
        }

        $periode = $request->input();
        $startDate = null;
        $endDate = null;

        // Default to weekly period (1 week)
        if (isset($periode['type']) && $periode['type'] === 'weekly') {
            $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
        } elseif (isset($periode['type']) && $periode['type'] === 'custom') {
            $startDate = $periode['startDate'];
            $endDate = $periode['endDate'];
        } elseif (isset($periode['type']) && $periode['type'] === 'monthly') {
            $month = $periode['month'];
            $year = $periode['year'];
            $startDate = Carbon::create($year, $month, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');
        } elseif (isset($periode['type']) && $periode['type'] === 'yearly') {
            $year = $periode['year'];
            $startDate = Carbon::create($year, 1, 1)->format('Y-m-d');
            $endDate = Carbon::create($year, 12, 31)->format('Y-m-d');
        } else {
            // Default to weekly if no type specified
            $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
        }

        if (!$startDate || !$endDate) {
            return response()->json([
                'success' => false,
                'message' => 'Periode tidak valid'
            ], 400);
        }

        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $attendanceCount = $mahasiswa->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['present', 'izin', 'hadir'])
            ->count();
        
        $persentase = $totalDays > 0 ? round(($attendanceCount / $totalDays) * 100, 2) : 0;

        $pngContent = $this->generateCertificatePng($mahasiswa);

        return response($pngContent)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'inline; filename="sertifikat_preview.png"');
    }

    public function previewPdf(Request $request, $mahasiswaId)
    {
        return $this->previewImage($request, $mahasiswaId);
    }

    public function history(Request $request, $mahasiswaId)
    {
        $user = Auth::user();
        
        if ($user->role !== 'admin' && $user->mahasiswa_id != $mahasiswaId) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $mahasiswa = Mahasiswa::find($mahasiswaId);
        
        if (!$mahasiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Mahasiswa tidak ditemukan'
            ], 404);
        }

        $history = $mahasiswa->sertifikatHistories()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }

    public function download($historyId)
    {
        $sertifikat = SertifikatHistory::find($historyId);
        
        if (!$sertifikat) {
            return response()->json([
                'success' => false,
                'message' => 'Sertifikat tidak ditemukan'
            ], 404);
        }

        $mahasiswa = $sertifikat->mahasiswa;
        
        // Parse periode to get start and end dates
        preg_match('/(\d{4}-\d{2}-\d{2})\s*s\/d\s*(\d{4}-\d{2}-\d{2})/', $sertifikat->periode, $matches);
        $startDate = $matches[1] ?? null;
        $endDate = $matches[2] ?? null;

        $pngContent = $this->generateCertificatePng($mahasiswa);

        return response($pngContent)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="sertifikat_' . $historyId . '.png"');
    }

    private function generateCertificatePng($mahasiswa)
    {
        $rawStudentName = trim($mahasiswa->name ?? '');
        $imagePath = public_path('static/img/sertifikat.png');

        if (!is_file($imagePath)) {
            abort(500, 'Template sertifikat tidak ditemukan di: ' . $imagePath);
        }

        // Bersihkan buffer yang ada sebelum mulai
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $image = @imagecreatefrompng($imagePath); // @ untuk suppress warning ke buffer
        if (!$image) {
            abort(500, 'Gagal membaca template PNG. Pastikan file valid.');
        }

        imagesavealpha($image, true);

        $width  = imagesx($image);
        $height = imagesy($image);
        $name   = mb_strtoupper($rawStudentName, 'UTF-8');

        $fontPath = $this->getCertificateFontPath();
        $fontSize = strlen($rawStudentName) > 32 ? 48 : (strlen($rawStudentName) > 24 ? 58 : 68);
        $color    = imagecolorallocate($image, 13, 59, 102);

        if ($fontPath) {
            $box       = imagettfbbox($fontSize, 0, $fontPath, $name);
            $textWidth = abs($box[2] - $box[0]);
            $x         = (int)(($width - $textWidth) / 2);
            $y         = (int)($height * 0.49);
            imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $name);
        } else {
            // Fallback: gunakan font bawaan GD
            $font      = 5;
            $textWidth = imagefontwidth($font) * strlen($name);
            $x         = (int)(($width - $textWidth) / 2);
            $y         = (int)($height * 0.46);
            imagestring($image, $font, $x, $y, $name, $color);
        }

        // Tulis langsung ke file temp, hindari ob conflict
        $tmpFile = tempnam(sys_get_temp_dir(), 'sertifikat_') . '.png';
        imagepng($image, $tmpFile);
        imagedestroy($image);

        $pngContent = file_get_contents($tmpFile);
        @unlink($tmpFile); // Hapus file temp

        if (!$pngContent || strlen($pngContent) < 100) {
            abort(500, 'Gagal menghasilkan PNG. Output kosong atau terlalu kecil.');
        }

        return $pngContent;
    }

    private function getCertificateFontPath()
    {
        $candidates = [
            public_path('static/fonts/Georgia-Bold.ttf'),
            'C:\\Windows\\Fonts\\georgiab.ttf',
            'C:\\Windows\\Fonts\\georgia.ttf',
            'C:\\Windows\\Fonts\\timesbd.ttf',
            'C:\\Windows\\Fonts\\times.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
