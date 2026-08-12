<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\PkkmbSchedule;
use App\Models\Kegiatan;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * Get all mahasiswa data for Python sync
     */
    public function mahasiswa()
    {
        try {
            // Otomatis isi tanggal_lahir jika masih NULL (ekstrak dari email ddmmyyyy atau default)
            Mahasiswa::whereNull('tanggal_lahir')->get()->each(function ($mhs) {
                $dob = null;
                if ($mhs->email && preg_match('/(\d{2})(\d{2})(\d{4})@/', $mhs->email, $matches)) {
                    $day = (int)$matches[1];
                    $month = (int)$matches[2];
                    $year = (int)$matches[3];
                    if (checkdate($month, $day, $year)) {
                        $dob = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    }
                }
                if (!$dob) {
                    $dob = '2006-01-01';
                }
                $mhs->update(['tanggal_lahir' => $dob]);
            });

            $mahasiswaList = Mahasiswa::select([
                'id',
                'name',
                'kompi',
                'jurusan',
                'prodi',
                'tanggal_lahir',
                'email',
                'no_telp_mahasiswa',
                'no_telp_ortu',
                'qr_code_id',
                'is_active'
            ])->get();

            return response()->json([
                'success' => true,
                'data' => $mahasiswaList,
                'count' => $mahasiswaList->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch mahasiswa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all PKKMB schedules for Python sync
     */
    public function schedules()
    {
        try {
            $schedules = PkkmbSchedule::select([
                'id',
                'hari_ke',
                'tanggal',
                'check_in_start',
                'check_in_end',
                'check_out_start',
                'check_out_end',
                'is_active'
            ])->orderBy('tanggal', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'count' => $schedules->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all kegiatan for Python sync
     */
    public function kegiatan()
    {
        try {
            $kegiatanList = Kegiatan::select([
                'id',
                'nama',
                'tanggal_pelaksanaan',
                'is_active'
            ])->orderBy('tanggal_pelaksanaan', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $kegiatanList,
                'count' => $kegiatanList->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch kegiatan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system config for Python sync
     */
    public function systemConfig()
    {
        try {
            $configs = \App\Models\SystemConfig::select([
                'config_key',
                'config_value',
                'description'
            ])->get();

            return response()->json([
                'success' => true,
                'data' => $configs,
                'count' => $configs->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system config: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's attendance records for Python sync
     */
    public function attendance()
    {
        try {
            $today = \Carbon\Carbon::today()->format('Y-m-d');
            $attendances = \App\Models\Attendance::where('date', $today)
                ->select([
                    'id',
                    'mahasiswa_id',
                    'kegiatan_id',
                    'date',
                    'check_in',
                    'check_out',
                    'status',
                    'is_late',
                    'late_duration'
                ])->get();

            return response()->json([
                'success' => true,
                'data' => $attendances,
                'count' => $attendances->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status and statistics
     */
    public function status()
    {
        try {
            $stats = [
                'mahasiswa_count' => Mahasiswa::count(),
                'mahasiswa_active_count' => Mahasiswa::where('is_active', 1)->count(),
                'schedules_count' => PkkmbSchedule::count(),
                'schedules_active_count' => PkkmbSchedule::where('is_active', 1)->count(),
                'kegiatan_count' => Kegiatan::count(),
                'kegiatan_active_count' => Kegiatan::where('is_active', 1)->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Laravel API ready for sync',
                'stats' => $stats,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API Endpoint untuk ditarik oleh Web Lain (Landing Page)
     * Mengambil data absensi MABA yang PERNAH HADIR/IZIN/SAKIT dalam format SUPER RINGKAS.
     */
    public function pkkmbAbsensi(Request $request)
    {
        try {
            // 1. Ambil seluruh tanggal jadwal PKKMB yang aktif
            $activeSchedules = PkkmbSchedule::where('is_active', true)
                ->orderBy('tanggal', 'asc')
                ->get(['hari_ke', 'tanggal']);

            $dateToHariMap = [];
            foreach ($activeSchedules as $sched) {
                $formattedDate = \Carbon\Carbon::parse($sched->tanggal)->format('Y-m-d');
                $dateToHariMap[$formattedDate] = (int) $sched->hari_ke;
            }

            $activeDates = array_keys($dateToHariMap);

            // 2. Query HANYA absensi yang statusnya valid (hadir, present, izin, sakit, atau ada check_in)
            $attendances = \App\Models\Attendance::with('mahasiswa:id,name')
                ->daily()
                ->whereIn('date', $activeDates)
                ->where(function ($q) {
                    $q->whereIn('status', ['hadir', 'present', 'izin', 'sakit'])
                      ->orWhereNotNull('check_in');
                })
                ->whereHas('mahasiswa', function ($q) {
                    $q->whereNotNull('id')->where('id', '<>', '');
                })
                ->get();

            // 3. Kelompokkan per mahasiswa yang pernah hadir
            $groupedByMhs = $attendances->groupBy('mahasiswa_id');

            $resultData = [];
            foreach ($groupedByMhs as $npm => $mhsAtts) {
                $firstMhs = $mhsAtts->first()->mahasiswa;
                if (!$firstMhs) continue;

                $hariHadir = [];
                $hariIzinSakit = [];

                foreach ($mhsAtts as $att) {
                    $rawStatus = strtolower($att->status ?? '');
                    $formattedDate = \Carbon\Carbon::parse($att->date)->format('Y-m-d');
                    $hariKe = $dateToHariMap[$formattedDate] ?? null;

                    if (!$hariKe) continue;

                    if (in_array($rawStatus, ['hadir', 'present']) || !empty($att->check_in)) {
                        $hariHadir[] = $hariKe;
                    } elseif (in_array($rawStatus, ['izin', 'sakit'])) {
                        $hariIzinSakit[] = $hariKe;
                    }
                }

                $hariHadir = array_values(array_unique($hariHadir));
                $hariIzinSakit = array_values(array_unique($hariIzinSakit));

                if (!empty($hariHadir) || !empty($hariIzinSakit)) {
                    $resultData[] = [
                        'npm'             => (string) $npm,
                        'nama'            => $firstMhs->name,
                        'hari_hadir'      => $hariHadir,
                        'hari_izin_sakit' => $hariIzinSakit,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'total'   => count($resultData),
                'data'    => $resultData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menarik data absensi PKKMB: ' . $e->getMessage()
            ], 500);
        }
    }
}

