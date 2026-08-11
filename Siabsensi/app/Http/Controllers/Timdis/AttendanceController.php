<?php

namespace App\Http\Controllers\Timdis;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Mahasiswa;
use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Monitor Absensi Hari Ini
     */
    public function attendance(Request $request)
    {
        // Always use date range
        $start = $request->get('start', Carbon::today()->toDateString());
        $end = $request->get('end', Carbon::today()->toDateString());
        
        // Validate dates
        try {
            Carbon::parse($start);
            Carbon::parse($end);
        } catch (\Exception $e) {
            $start = Carbon::today()->toDateString();
            $end = Carbon::today()->toDateString();
        }
        
        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');
        $kompi = $request->get('kompi', '');
        $jurusan = $request->get('jurusan', '');
        $assignedKompi = auth()->user()->assigned_kompi;

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        if ($filter === 'alpha') {
            $query = Mahasiswa::select(
                "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan", "$mhsTable.photo_path", "$mhsTable.id as mahasiswa_id",
                DB::raw('null as check_in'), DB::raw('null as check_out'), DB::raw('null as date'),
                DB::raw("'alpha' as status"), DB::raw('null as camera_id'),
                DB::raw('null as kegiatan_id'), DB::raw('null as is_late'), DB::raw('null as late_duration')
            )->whereNotExists(function ($q) use ($table, $start, $end, $mhsTable) {
                $q->select(DB::raw(1))->from($table)
                    ->whereColumn("$table.mahasiswa_id", "$mhsTable.id")
                    ->whereBetween("$table.date", [$start, $end]);
            });
            
            // Apply assigned_kompi filter
            if ($assignedKompi) {
                $query->where("$mhsTable.kompi", $assignedKompi);
            }

            // Apply user filters
            if ($search) {
                $query->where(function ($q) use ($search, $mhsTable) {
                    $q->where("$mhsTable.name", 'like', "%{$search}%")
                      ->orWhere("$mhsTable.id", 'like', "%{$search}%");
                });
            }
            if ($kompi) {
                $query->where("$mhsTable.kompi", $kompi);
            }
            if ($jurusan) {
                $query->where("$mhsTable.jurusan", $jurusan);
            }
            
            $attendances = $query->paginate(20)->withQueryString();
        } elseif (in_array($filter, ['izin', 'sakit', 'hadir', 'present'])) {
            $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->whereBetween("$table.date", [$start, $end])
                ->orderBy("$table.date", 'desc')
                ->orderByRaw("GREATEST(COALESCE($table.check_out, '1970-01-01'), COALESCE($table.check_in, '1970-01-01')) DESC")
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan", "$mhsTable.photo_path");
            
            if ($assignedKompi) {
                $query->where("$mhsTable.kompi", $assignedKompi);
            }

            // Filter by status
            if (in_array($filter, ['hadir', 'present'])) {
                $query->whereIn("$table.status", ['hadir', 'present']);
            } else {
                $query->where("$table.status", $filter);
            }
            
            // Apply filters
            if ($search) {
                $query->where(function ($q) use ($search, $mhsTable) {
                    $q->where("$mhsTable.name", 'like', "%{$search}%")
                      ->orWhere("$mhsTable.id", 'like', "%{$search}%");
                });
            }
            if ($kompi) {
                $query->where("$mhsTable.kompi", $kompi);
            }
            if ($jurusan) {
                $query->where("$mhsTable.jurusan", $jurusan);
            }
            
            $attendances = $query->paginate(20)->withQueryString();
        } else {
            $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->whereBetween("$table.date", [$start, $end])
                ->orderBy("$table.date", 'desc')
                ->orderByRaw("GREATEST(COALESCE($table.check_out, '1970-01-01'), COALESCE($table.check_in, '1970-01-01')) DESC")
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan", "$mhsTable.photo_path");
            
            if ($assignedKompi) {
                $query->where("$mhsTable.kompi", $assignedKompi);
            }

            // Apply filters
            if ($search) {
                $query->where(function ($q) use ($search, $mhsTable) {
                    $q->where("$mhsTable.name", 'like', "%{$search}%")
                      ->orWhere("$mhsTable.id", 'like', "%{$search}%");
                });
            }
            if ($kompi) {
                $query->where("$mhsTable.kompi", $kompi);
            }
            if ($jurusan) {
                $query->where("$mhsTable.jurusan", $jurusan);
            }
            
            $attendances = $query->paginate(20)->withQueryString();
        }

        // Get filter options
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sortBy(function ($name) { return (int) preg_replace('/[^0-9]/', '', $name ?? ''); })->values();
        $jurusanOptions = \App\Models\Jurusan::pluck('nama')->sort()->values();

        return view('timdis.attendance', compact('attendances', 'start', 'end', 'filter', 'search', 'kompi', 'jurusan', 'kompiOptions', 'jurusanOptions'));
    }

    /**
     * Monitoring Kegiatan / Event Aktif
     */
    public function monitoringKegiatan()
    {
        // Mengambil semua sesi dari jadwal PKKMB yang aktif untuk direkap/dimonitoring
        $kegiatanList = \App\Models\KegiatanSesi::with(['pkkmbSchedule'])
            ->join('pkkmb_schedules', 'kegiatan_sesi.pkkmb_schedule_id', '=', 'pkkmb_schedules.id')
            ->orderBy('pkkmb_schedules.tanggal', 'desc')
            ->orderBy('kegiatan_sesi.jam_mulai', 'asc')
            ->select('kegiatan_sesi.*', 'pkkmb_schedules.tanggal')
            ->get();

        return view('timdis.monitoring-kegiatan', compact('kegiatanList'));
    }

    /**
     * Detail Monitoring Kegiatan
     */
    public function monitoringKegiatanDetail($id)
    {
        $kegiatan = Kegiatan::findOrFail($id);

        // Ambil semua attendance yang terkait kegiatan ini
        $attendances = Attendance::where('kegiatan_id', $id)
            ->with('mahasiswa')
            ->orderBy('check_in_time', 'asc')
            ->get();

        // Statistik
        $totalMahasiswa = \App\Models\Mahasiswa::where('is_active', 1)->count();
        $hadir = $attendances->where('status', 'present')->count();
        $tidakHadir = $totalMahasiswa - $hadir;

        return view('timdis.monitoring-kegiatan-detail', compact('kegiatan', 'attendances', 'totalMahasiswa', 'hadir', 'tidakHadir'));
    }
}


