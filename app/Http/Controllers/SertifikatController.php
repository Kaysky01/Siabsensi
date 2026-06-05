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

        return response()->json([
            'success' => true,
            'stats' => [
                'totalHadir' => $attendanceCount,
                'totalIzin' => $izinCount,
                'totalHari' => $totalDays,
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
        $template = $request->input('template', 'default');
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
            'template' => $template,
            'total_hadir' => $attendanceCount,
            'persentase' => $persentase
        ]);

        // Generate simple PDF content (placeholder)
        $htmlContent = $this->generateCertificateHtml($mahasiswa, $startDate, $endDate, $attendanceCount, $persentase, $template);

        return response($htmlContent)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="sertifikat_' . $mahasiswa->id . '_' . time() . '.html"');
    }

    public function previewPdf(Request $request, $mahasiswaId)
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
        $template = $request->input('template', 'default');
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

        $htmlContent = $this->generateCertificateHtml($mahasiswa, $startDate, $endDate, $attendanceCount, $persentase, $template);

        return response($htmlContent)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="sertifikat_preview.html"');
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

        $htmlContent = $this->generateCertificateHtml($mahasiswa, $startDate, $endDate, $sertifikat->total_hadir, $sertifikat->persentase, $sertifikat->template);

        return response($htmlContent)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="sertifikat_' . $historyId . '.html"');
    }

    private function generateCertificateHtml($mahasiswa, $startDate, $endDate, $attendanceCount, $persentase, $template)
    {
        // Format dates for display
        $startDateFormatted = date('d F Y', strtotime($startDate));
        $endDateFormatted = date('d F Y', strtotime($endDate));
        
        // Get the full URL for the background image
        $imageUrl = asset('static/img/serifikat.png');

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Sertifikat Kehadiran</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: 'Times New Roman', serif; 
                    background: #f0f0f0; 
                    display: flex; 
                    justify-content: center; 
                    align-items: center; 
                    min-height: 100vh;
                    padding: 20px;
                }
                .certificate-container {
                    position: relative;
                    width: 100%;
                    max-width: 1123px;
                    aspect-ratio: 1.414;
                    background-image: url('{$imageUrl}');
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                }
                .certificate-content {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    padding: 40px;
                }
                .student-name {
                    font-size: 32px;
                    font-weight: bold;
                    text-align: center;
                    margin: 20px 0;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
                .student-details {
                    font-size: 18px;
                    text-align: center;
                    margin: 10px 0;
                    line-height: 1.8;
                }
                .student-details strong {
                    font-weight: bold;
                }
                .attendance-info {
                    font-size: 20px;
                    text-align: center;
                    margin: 30px 0;
                    font-weight: bold;
                }
                .signature-section {
                    position: absolute;
                    bottom: 80px;
                    right: 100px;
                    text-align: center;
                }
                .signature-line {
                    border-top: 2px solid #000;
                    width: 200px;
                    margin-top: 60px;
                }
                @media print {
                    body { background: white; }
                    .certificate-container { box-shadow: none; }
                }
            </style>
        </head>
        <body>
            <div class='certificate-container'>
                <div class='certificate-content'>
                    <div class='student-name'>{$mahasiswa->name}</div>
                    <div class='student-details'>
                        <strong>NIM:</strong> {$mahasiswa->id}<br>
                        <strong>Kelompok:</strong> {$mahasiswa->kelompok}<br>
                        <strong>Jurusan:</strong> {$mahasiswa->jurusan}
                    </div>
                    <div class='attendance-info'>
                        Telah mengikuti kegiatan PKKMB 2026<br>
                        Periode: {$startDateFormatted} s/d {$endDateFormatted}<br>
                        Total Kehadiran: {$attendanceCount} hari ({$persentase}%)
                    </div>
                </div>
                <div class='signature-section'>
                    <p>Panitia PKKMB 2026</p>
                    <div class='signature-line'></div>
                </div>
            </div>
        </body>
        </html>
        ";

        return $html;
    }
}
