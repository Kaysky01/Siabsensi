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
            $mahasiswaList = Mahasiswa::select([
                'id',
                'name',
                'kompi',
                'jurusan',
                'prodi',
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
}
