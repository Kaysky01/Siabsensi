<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CameraStream;
use App\Models\IzinSubmission;
use App\Models\Jurusan;
use App\Models\Kegiatan;
use App\Models\KehadiranSubmission;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\SystemConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AdminController extends Controller
{
    // ─── DASHBOARD ────────────────────────────────────────────────────────────
    public function dashboard_admin()
    {
        $today = Carbon::today()->toDateString();
        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        $totalMahasiswa = Mahasiswa::count();
        $presentToday = Attendance::whereDate('date', $today)->distinct()->count('mahasiswa_id');
        $stillIn = Attendance::whereDate('date', $today)->whereNotNull('check_in')->whereNull('check_out')->count();
        $absent = max(0, $totalMahasiswa - $presentToday);
        $pct = $totalMahasiswa > 0 ? round(($presentToday / $totalMahasiswa) * 100) : 0;

        $pendingKehadiranCount = KehadiranSubmission::where('status', 'pending')->count();
        $pendingIzinCount = IzinSubmission::where('status', 'pending')->count();
        $totalPending = $pendingKehadiranCount + $pendingIzinCount;
        $isMaintenanceMode = SystemConfig::isMaintenanceMode();

        // Recent attendances (Urutkan berdasarkan aktivitas terbaru: check-out atau check-in)
        $recent = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereDate("$table.date", $today)
            ->orderByRaw("GREATEST(COALESCE($table.check_out, '1970-01-01'), COALESCE($table.check_in, '1970-01-01')) DESC")
            ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.photo_path")
            ->take(8)
            ->get();

        // 7-day trend
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = Attendance::whereDate('date', $date->toDateString())->distinct()->count('mahasiswa_id');
            $trend[] = ['date' => $date->format('d/m'), 'count' => $count];
        }

        // By kompi (diurutkan secara natural berdasarkan angka kompi 1, 2, 3... 14)
        $byKompi = DB::table($table)
            ->join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereDate("$table.date", $today)
            ->select("$mhsTable.kompi", DB::raw("count(DISTINCT $table.mahasiswa_id) as count"))
            ->groupBy("$mhsTable.kompi")
            ->get()
            ->sortBy(function ($item) {
                return (int) preg_replace('/[^0-9]/', '', $item->kompi ?? '');
            }, SORT_NUMERIC)
            ->values();

        $maxKompi = $byKompi->max('count') ?: 1;

        return view('admin.dashboard', compact(
            'totalMahasiswa', 'presentToday', 'absent', 'stillIn', 'pct',
            'recent', 'trend', 'byKompi', 'maxKompi', 'pendingKehadiranCount', 'pendingIzinCount', 'totalPending', 'isMaintenanceMode'
        ));
    }

    /**
     * Toggle System Maintenance Mode
     */
    public function toggleMaintenanceMode(Request $request)
    {
        $current = SystemConfig::isMaintenanceMode();
        $newStatus = !$current;
        SystemConfig::setMaintenanceMode($newStatus);

        $statusText = $newStatus 
            ? 'diaktifkan. Akses untuk role non-admin dialihkan ke halaman pemeliharaan.' 
            : 'dinonaktifkan. Seluruh role dapat kembali menggunakan sistem.';

        return back()->with('success', "Mode Maintenance berhasil {$statusText}");
    }

    // ─── ATTENDANCE ───────────────────────────────────────────────────────────
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
        } elseif (in_array($filter, ['izin', 'sakit', 'hadir', 'present'])) {
            $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->whereBetween("$table.date", [$start, $end])
                ->orderBy("$table.date", 'desc')
                ->orderByRaw("GREATEST(COALESCE($table.check_out, '1970-01-01'), COALESCE($table.check_in, '1970-01-01')) DESC")
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan", "$mhsTable.photo_path");
            
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
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sortBy(null, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $jurusanOptions = \App\Models\Jurusan::pluck('nama')->sort()->values();
        $prodiOptions = \App\Models\Prodi::pluck('nama')->sort()->values();

        return view('admin.attendance', compact('attendances', 'start', 'end', 'filter', 'search', 'kompi', 'jurusan', 'kompiOptions', 'jurusanOptions', 'prodiOptions'));
    }

    // ─── MAHASISWA ────────────────────────────────────────────────────────────
    public function mahasiswa(Request $request)
    {
        $this->ensureMahasiswaManagementAccess();

        $query = Mahasiswa::query();

        $user = Auth::user();
        if ($user && $user->role === 'timdis' && $user->assigned_kompi) {
            $query->where('kompi', $user->assigned_kompi);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('id', 'like', '%' . $search . '%')
                  ->orWhere('qr_code_id', 'like', '%' . $search . '%');
            });
        }
        if ($request->filled('kompi')) {
            if ($request->kompi === '__empty__') {
                $query->where(function ($q) {
                    $q->whereNull('kompi')->orWhere('kompi', '')->orWhere('kompi', '-');
                });
            } else {
                $query->where('kompi', $request->kompi);
            }
        }
        if ($request->filled('jurusan')) {
            $query->where('jurusan', 'like', '%' . $request->jurusan . '%');
        }
        if ($request->filled('prodi')) {
            $query->where('prodi', 'like', '%' . $request->prodi . '%');
        }

        // Auto-fix tanggal_lahir yang masih NULL (ekstrak dari email ddmmyyyy atau default 2006-01-01)
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

        $allKegiatan = \App\Models\PkkmbSchedule::orderBy('tanggal')->get();

        $mahasiswaList = $query->with('attendances')->orderBy('name')->paginate(20)->withQueryString();

        $kompiOptions = \Illuminate\Support\Facades\Cache::remember('master_kompi', 3600, function() {
            return \App\Models\Kompi::pluck('nama')->sortBy(null, SORT_NATURAL | SORT_FLAG_CASE)->values();
        });

        $jurusanOptions = \Illuminate\Support\Facades\Cache::remember('master_jurusan', 3600, function() {
            return \App\Models\Jurusan::pluck('nama')->sort()->values();
        });

        $prodiOptions = \Illuminate\Support\Facades\Cache::remember('master_prodi', 3600, function() {
            return \App\Models\Prodi::pluck('nama')->sort()->values();
        });

        $jurusanWithProdi = \Illuminate\Support\Facades\Cache::remember('master_jurusan_prodi', 3600, function() {
            return \App\Models\Jurusan::with('prodi')->get();
        });

        $managementRoutePrefix = $this->getMahasiswaManagementRoutePrefix();

        return view('admin.mahasiswa', compact(
            'mahasiswaList',
            'kompiOptions',
            'jurusanOptions',
            'prodiOptions',
            'jurusanWithProdi',
            'allKegiatan',
            'managementRoutePrefix'
        ));
    }

    public function getAttendanceDetail(Request $request, $id)
    {
        $mahasiswa = Mahasiswa::findOrFail($id);
        $schedules = \App\Models\PkkmbSchedule::orderBy('tanggal', 'asc')->get();

        $attendances = Attendance::where('mahasiswa_id', $id)
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        $kehadiranSubs = KehadiranSubmission::where('mahasiswa_id', $id)
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        $izinSubs = IzinSubmission::where('mahasiswa_id', $id)
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m-d');
            });

        $details = [];
        foreach ($schedules as $sched) {
            $tgl = Carbon::parse($sched->tanggal)->format('Y-m-d');
            $att = $attendances->get($tgl);
            $khdSub = $kehadiranSubs->get($tgl);
            $iznSub = $izinSubs->get($tgl);

            $inTime = '-';
            $outTime = '-';
            $status = 'alpha';
            $statusLabel = 'Alpha (Belum Absen)';
            $badgeClass = 'badge-red';
            $method = '-';
            $absenBy = '-';
            $attendanceId = null;

            // Determine Alasan / Keterangan submission
            $keterangan = '-';
            if ($iznSub && !empty($iznSub->keterangan)) {
                $keterangan = '[' . strtoupper($iznSub->submission_type) . '] ' . $iznSub->keterangan;
            } elseif ($khdSub && !empty($khdSub->keterangan)) {
                $keterangan = '[Absen Manual] ' . $khdSub->keterangan;
            } elseif ($att && !empty($att->notes)) {
                $keterangan = $att->notes;
            }

            if ($att) {
                $attendanceId = $att->id;
                $status = strtolower($att->status ?? 'alpha');
                $inTime = $att->check_in ? Carbon::parse($att->check_in)->format('H:i:s') : '-';
                $outTime = $att->check_out ? Carbon::parse($att->check_out)->format('H:i:s') : '-';
                $absenBy = $att->absen_by ?? ($att->camera_id ? 'Sistem Camera (' . $att->camera_id . ')' : '-');

                if (in_array($status, ['hadir', 'present', 'lengkap', 'manual'])) {
                    if ($att->check_in && $att->check_out) {
                        $statusLabel = 'Lengkap (Hadir & Keluar)';
                        $badgeClass = 'badge-green';
                    } elseif ($att->check_in) {
                        $statusLabel = 'Hadir (Masuk)';
                        $badgeClass = 'badge-green';
                    } else {
                        $statusLabel = 'Hadir (Manual)';
                        $badgeClass = 'badge-green';
                    }
                    $method = $att->absen_by ? 'Absen Manual' : ($att->camera_id ? 'Scan Kamera' : 'Sistem');
                } elseif ($status === 'izin') {
                    $statusLabel = 'Izin';
                    $badgeClass = 'badge-blue';
                    $method = 'Pengajuan Izin';
                } elseif ($status === 'sakit') {
                    $statusLabel = 'Sakit';
                    $badgeClass = 'badge-yellow';
                    $method = 'Pengajuan Sakit';
                } else {
                    $statusLabel = 'Alpha';
                    $badgeClass = 'badge-red';
                }
            } else {
                if (Carbon::parse($tgl)->isFuture()) {
                    $statusLabel = 'Belum Dimulai';
                    $badgeClass = 'badge-gray';
                }
            }

            $details[] = [
                'attendance_id' => $attendanceId,
                'hari_ke' => $sched->hari_ke,
                'title' => "Hari ke-{$sched->hari_ke} (" . Carbon::parse($sched->tanggal)->format('d M Y') . ")",
                'tanggal' => Carbon::parse($sched->tanggal)->format('d M Y'),
                'check_in' => $inTime,
                'check_out' => $outTime,
                'status' => $status,
                'status_label' => $statusLabel,
                'badge_class' => $badgeClass,
                'method' => $method,
                'absen_by' => $absenBy,
                'keterangan' => $keterangan,
            ];
        }

        return response()->json([
            'success' => true,
            'mahasiswa' => [
                'id' => $mahasiswa->id,
                'name' => $mahasiswa->name,
                'kompi' => $mahasiswa->kompi,
                'jurusan' => $mahasiswa->jurusan ?? '-',
                'prodi' => $mahasiswa->prodi ?? '-',
            ],
            'details' => $details,
            'is_admin' => Auth::check() && Auth::user()->role === 'admin',
        ]);
    }

    public function deleteAttendanceRecord($id)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Hanya Admin yang dapat menghapus data kehadiran.'], 403);
        }

        $att = Attendance::findOrFail($id);
        $att->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data kehadiran pada hari tersebut berhasil dihapus.',
        ]);
    }

    private function parseFormattedDate(?string $value): ?string
    {
        if (empty($value)) return null;
        $value = trim($value);

        if (str_contains($value, '/')) {
            try {
                return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                try {
                    return Carbon::parse($value)->format('Y-m-d');
                } catch (\Throwable $e2) {
                    return null;
                }
            }
        }

        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $m)) {
            if (checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function storeMahasiswa(Request $request)
    {
        $this->ensureMahasiswaManagementAccess();

        $user = Auth::user();
        if ($user && $user->role === 'timdis' && $user->assigned_kompi) {
            $request->merge(['kompi' => $user->assigned_kompi]);
        }

        if ($request->has('tanggal_lahir') && $request->filled('tanggal_lahir')) {
            $parsedDate = $this->parseFormattedDate($request->tanggal_lahir);
            if ($parsedDate) {
                $request->merge(['tanggal_lahir' => $parsedDate]);
            }
        }

        $validated = $request->validate([
            'id' => 'required|string|max:50|unique:mahasiswa,id',
            'name' => 'required|string|max:255',
            'kompi' => 'required|string',
            'jurusan' => 'required|string',
            'prodi' => 'nullable|string|max:100',
            'tanggal_lahir' => 'required|date',
            'email' => 'nullable|email|unique:mahasiswa,email',
            'no_telp_mahasiswa' => 'nullable|string',
            'no_telp_ortu' => 'nullable|string',
        ]);

        if (User::where('username', $validated['id'])->exists()) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['id' => 'Nomor registrasi sudah dipakai sebagai username user lain.']);
        }

        $validated['qr_code_id'] = $validated['id'];

        $mahasiswa = Mahasiswa::create($validated);

        $dob = Carbon::parse($mahasiswa->tanggal_lahir);
        $defaultPassword = $dob->format('dmY'); // format: ddmmyyyy

        User::create([
            'username'       => $mahasiswa->id,
            'password'       => Hash::make($defaultPassword),
            'full_name'      => $mahasiswa->name,
            'email'          => $mahasiswa->email,
            'role'           => 'mahasiswa',
            'mahasiswa_id'   => $mahasiswa->id,
            'assigned_kompi' => $mahasiswa->kompi,
            'is_active'      => 1,
        ]);

        return redirect()->route($this->getMahasiswaManagementRouteName('mahasiswa'))
            ->with('success', "Mahasiswa {$mahasiswa->name} berhasil ditambahkan. Username: {$mahasiswa->id}, Password default: {$defaultPassword}");

    }

    public function qrCode($id)
    {
        $this->ensureMahasiswaManagementAccess();

        $mahasiswa = Mahasiswa::findOrFail($id);
        $qrImage = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->generate($mahasiswa->qr_code_id);
        return view('admin.mahasiswa-qr', compact('mahasiswa', 'qrImage'));
    }

    public function downloadTemplateCSV()
    {
        $this->ensureMahasiswaManagementAccess();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Template_Import_Mahasiswa.csv"',
        ];

        $columns = [
            'Nomor Registrasi',
            'Nama',
            'Kompi (Opsional)',
            'Jurusan',
            'Prodi',
            'Tanggal Lahir (YYYY-MM-DD)',
            'Email (Opsional)',
            'Telp Mahasiswa (Opsional)',
            'Telp Ortu (Opsional)',
        ];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            // Add BOM for Excel UTF-8 support
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns, ';');
            // Contoh isi data
            fputcsv($file, ['REG2026001', 'Ahmad Budi', 'KOMPI 1', 'Teknologi Informasi', 'Manajemen Informatika', '2005-12-31', 'ahmad@example.com', '081234567890', '081987654321'], ';');
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importMahasiswaCSV(Request $request)
    {
        set_time_limit(0);
        $this->ensureMahasiswaManagementAccess();
        @ini_set('memory_limit', '512M');

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt,xls,xlsx|max:5120'
        ], [
            'csv_file.mimes' => 'Format file tidak didukung. Harap upload file berekstensi .csv, .xls, atau .xlsx'
        ]);

        $file = $request->file('csv_file');
        $rows = $this->readMahasiswaImportRows($file->getPathname(), $file->getClientOriginalExtension());

        if (count($rows) < 2) {
            return back()->with('error', 'File import kosong atau tidak memiliki data mahasiswa.');
        }

        $header = array_shift($rows);
        $headerMap = $this->resolveMahasiswaImportHeaderMap($header);
        $requiredHeaders = ['nomor_registrasi', 'name', 'prodi', 'tanggal_lahir'];
        $missingHeaders = [];

        foreach ($requiredHeaders as $field) {
            if (!array_key_exists($field, $headerMap)) {
                $missingHeaders[] = $this->getMahasiswaImportHeaderLabels()[$field];
            }
        }

        if (!empty($missingHeaders)) {
            return back()->with('error', 'Header file belum sesuai. Kolom wajib yang belum ada: ' . implode(', ', $missingHeaders) . '.');
        }

        $count = 0;
        $hashedPasswords = [];
        $mahasiswaBatch = [];
        $userBatch = [];
        $now = now();

        // Optimasi: Pre-fetch existing data ke memori (Lookups)
        $existingMahasiswaIds = array_fill_keys(Mahasiswa::pluck('id')->filter()->toArray(), true);
        $existingUsernames    = array_fill_keys(User::pluck('username')->filter()->toArray(), true);
        $existingEmails       = array_fill_keys(Mahasiswa::whereNotNull('email')->pluck('email')->filter()->toArray(), true);

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2;
                $record = $this->mapMahasiswaImportRow($row, $headerMap);

                if ($this->isMahasiswaImportRowEmpty($record)) {
                    continue;
                }

                $name = trim((string) ($record['name'] ?? ''));
                if ($name === '') {
                    throw new \RuntimeException("Baris {$rowNumber}: nama mahasiswa wajib diisi.");
                }

                $mahasiswaId = trim((string) ($record['nomor_registrasi'] ?? ''));
                if ($mahasiswaId === '') {
                    throw new \RuntimeException("Baris {$rowNumber}: nomor registrasi wajib diisi.");
                }

                if (isset($existingMahasiswaIds[$mahasiswaId]) || isset($existingUsernames[$mahasiswaId])) {
                    throw new \RuntimeException("Baris {$rowNumber}: nomor registrasi/username {$mahasiswaId} sudah digunakan.");
                }

                $email = $this->normalizeMahasiswaImportValue($record['email'] ?? null);
                if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException("Baris {$rowNumber}: format email tidak valid.");
                }
                if ($email !== null && isset($existingEmails[$email])) {
                    throw new \RuntimeException("Baris {$rowNumber}: email {$email} sudah digunakan.");
                }

                $tanggalLahirRaw = $record['tanggal_lahir'] ?? null;
                $tanggalLahir = $this->parseMahasiswaImportDate($tanggalLahirRaw, $rowNumber, $email);
                $defaultPassword = Carbon::parse($tanggalLahir)->format('dmY');

                if (!isset($hashedPasswords[$defaultPassword])) {
                    $hashedPasswords[$defaultPassword] = Hash::make($defaultPassword);
                }

                $jurusanProdi = $this->resolveImportedJurusanProdi(
                    $record['jurusan'] ?? null,
                    $record['prodi'] ?? null,
                    $rowNumber
                );

                $kompi = $this->normalizeMahasiswaImportValue($record['kompi'] ?? null) ?? '-';

                $mahasiswaBatch[] = [
                    'id'                => $mahasiswaId,
                    'qr_code_id'        => $mahasiswaId,
                    'name'              => $name,
                    'kompi'             => $kompi,
                    'jurusan'           => $jurusanProdi['jurusan'],
                    'prodi'             => $jurusanProdi['prodi'],
                    'tanggal_lahir'     => $tanggalLahir,
                    'email'             => $email,
                    'no_telp_mahasiswa' => $this->normalizeMahasiswaImportValue($record['no_telp_mahasiswa'] ?? null),
                    'no_telp_ortu'      => $this->normalizeMahasiswaImportValue($record['no_telp_ortu'] ?? null),
                    'is_active'         => 1,
                    'created_at'        => $now,
                ];

                $userBatch[] = [
                    'username'       => $mahasiswaId,
                    'password'       => $hashedPasswords[$defaultPassword],
                    'full_name'      => $name,
                    'email'          => $email,
                    'role'           => 'mahasiswa',
                    'mahasiswa_id'   => $mahasiswaId,
                    'assigned_kompi' => $kompi,
                    'is_active'      => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                // Update memory lookups untuk mendeteksi duplikat internal file
                $existingMahasiswaIds[$mahasiswaId] = true;
                $existingUsernames[$mahasiswaId]    = true;
                if ($email !== null) {
                    $existingEmails[$email] = true;
                }

                $count++;

                // Flush batch setiap 200 data untuk menjaga efisiensi memory & kecepatan SQL
                if (count($mahasiswaBatch) >= 200) {
                    Mahasiswa::insert($mahasiswaBatch);
                    User::insert($userBatch);
                    $mahasiswaBatch = [];
                    $userBatch = [];
                }
            }

            // Flush sisa data batch
            if (!empty($mahasiswaBatch)) {
                Mahasiswa::insert($mahasiswaBatch);
                User::insert($userBatch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses file import: ' . $e->getMessage());
        }

        \Illuminate\Support\Facades\Cache::forget('master_jurusan');
        \Illuminate\Support\Facades\Cache::forget('master_prodi');
        \Illuminate\Support\Facades\Cache::forget('master_jurusan_prodi');

        return back()->with('success', "Berhasil mengimpor {$count} mahasiswa dari file.");
    }

    private function readMahasiswaImportRows(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            return $sheet->toArray(null, true, true, false);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('File import tidak dapat dibuka.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return [];
        }

        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rows = [];
        while (($row = fgetcsv($handle, 10000, $delimiter)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function resolveMahasiswaImportHeaderMap(array $header): array
    {
        $aliases = [
            'nomor_registrasi' => ['nomor registrasi', 'nomor_registrasi', 'nomorregistrasi', 'id', 'nim', 'username', 'no pendaftaran', 'no. pendaftaran'],
            'name' => ['nama', 'nama lengkap', 'name'],
            'kompi' => ['kompi', 'kelompok', 'group'],
            'jurusan' => ['jurusan', 'jurusan polinela'],
            'prodi' => ['prodi', 'prodi polinela', 'program studi', 'prodi diterima'],
            'tanggal_lahir' => ['tanggal lahir', 'tanggal_lahir', 'tgl lahir', 'tgl_lahir'],
            'email' => ['email', 'e mail', 'e-mail'],
            'no_telp_mahasiswa' => ['telp mahasiswa', 'no telp mahasiswa', 'tlp mahasiswa', 'telepon mahasiswa', 'no_telp_mahasiswa'],
            'no_telp_ortu' => ['telp ortu', 'no telp ortu', 'tlp ortu', 'telepon ortu', 'telp orang tua', 'no telp orang tua', 'no_telp_ortu', 'no hp ayah', 'no. hp ayah'],
        ];

        $normalizedAliasMap = [];
        foreach ($aliases as $key => $values) {
            foreach ($values as $value) {
                $normalizedAliasMap[$this->normalizeMahasiswaImportHeader($value)] = $key;
            }
        }

        $headerMap = [];
        foreach ($header as $index => $column) {
            $normalized = $this->normalizeMahasiswaImportHeader($column);
            if ($normalized !== '' && isset($normalizedAliasMap[$normalized])) {
                $headerMap[$normalizedAliasMap[$normalized]] = $index;
            }
        }

        return $headerMap;
    }

    private function normalizeMahasiswaImportHeader($value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/\([^)]*\)/', '', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim($value);
    }

    private function getMahasiswaImportHeaderLabels(): array
    {
        return [
            'nomor_registrasi' => 'Nomor Registrasi',
            'name' => 'Nama',
            'kompi' => 'Kompi',
            'jurusan' => 'Jurusan',
            'prodi' => 'Prodi',
            'tanggal_lahir' => 'Tanggal Lahir',
            'email' => 'Email',
            'no_telp_mahasiswa' => 'Telp Mahasiswa',
            'no_telp_ortu' => 'Telp Ortu',
        ];
    }

    private function mapMahasiswaImportRow(array $row, array $headerMap): array
    {
        $record = [];

        foreach ($headerMap as $field => $index) {
            $record[$field] = $row[$index] ?? null;
        }

        return $record;
    }

    private function isMahasiswaImportRowEmpty(array $record): bool
    {
        foreach ($record as $value) {
            if ($this->normalizeMahasiswaImportValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function normalizeMahasiswaImportValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseMahasiswaImportDate($value, int $rowNumber, ?string $email = null): string
    {
        if ($value === null || $value === '') {
            if ($email && preg_match('/(\d{2})(\d{2})(\d{4})@/', $email, $matches)) {
                $day = (int)$matches[1];
                $month = (int)$matches[2];
                $year = (int)$matches[3];
                if (checkdate($month, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
            return '2006-01-01'; // Default fallback agar tidak pernah NULL
        }

        try {
            $dateString = trim((string) $value);

            // Coba format 8-digit DDMMYYYY (misal 13012008)
            if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $dateString, $matches)) {
                $day = (int)$matches[1];
                $month = (int)$matches[2];
                $year = (int)$matches[3];
                if (checkdate($month, $day, $year)) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }

            if (is_numeric($value) && (float)$value < 2922000) { // Excel date serial limit
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            }

            // Coba parsing spesifik DD/MM/YYYY dulu jika menggunakan slash
            if (strpos($dateString, '/') !== false) {
                try {
                    return Carbon::createFromFormat('d/m/Y', $dateString)->format('Y-m-d');
                } catch (\Throwable $e) {
                    // fall back ke Carbon::parse
                }
            }

            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Throwable $e) {
            throw new \RuntimeException("Baris {$rowNumber}: tanggal lahir tidak valid.");
        }
    }

    private function resolveImportedJurusanProdi($jurusanValue, $prodiValue, int $rowNumber): array
    {
        $jurusanInput = $this->normalizeMahasiswaImportValue($jurusanValue);
        $prodiInput = $this->normalizeMahasiswaImportValue($prodiValue);

        if ($prodiInput === null) {
            throw new \RuntimeException("Baris {$rowNumber}: prodi wajib diisi.");
        }

        if ($jurusanInput !== null) {
            $jurusan = Jurusan::whereRaw('LOWER(nama) = ?', [strtolower($jurusanInput)])->first();
            if (!$jurusan) {
                $jurusan = Jurusan::create(['nama' => $jurusanInput]);
            }

            $prodi = Prodi::where('jurusan_id', $jurusan->id)
                ->whereRaw('LOWER(nama) = ?', [strtolower($prodiInput)])
                ->first();

            if (!$prodi) {
                $prodi = Prodi::create([
                    'jurusan_id' => $jurusan->id,
                    'nama' => $prodiInput,
                ]);
            }

            return [
                'jurusan' => $jurusan->nama,
                'prodi' => $prodi->nama,
            ];
        }

        $prodi = Prodi::whereRaw('LOWER(nama) = ?', [strtolower($prodiInput)])->first();

        if (!$prodi) {
            throw new \RuntimeException("Baris {$rowNumber}: prodi '{$prodiInput}' tidak ditemukan di master data. Pastikan ProdiSeeder sudah dijalankan atau tambahkan prodi melalui menu master data.");
        }

        $jurusan = $prodi->jurusan;

        return [
            'jurusan' => $jurusan->nama,
            'prodi' => $prodi->nama,
        ];
    }


    public function updateMahasiswa(Request $request, $id)
    {
        $this->ensureMahasiswaManagementAccess();

        $mahasiswa = Mahasiswa::findOrFail($id);

        if ($request->filled('id') && $request->input('id') !== $id) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['id' => 'Nomor registrasi tidak dapat diubah.']);
        }

        if ($request->has('tanggal_lahir') && $request->filled('tanggal_lahir')) {
            $parsedDate = $this->parseFormattedDate($request->tanggal_lahir);
            if ($parsedDate) {
                $request->merge(['tanggal_lahir' => $parsedDate]);
            }
        }

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'kompi'             => 'required|string',
            'jurusan'           => 'required|string',
            'prodi'             => 'nullable|string|max:100',
            'email'             => 'nullable|email|unique:mahasiswa,email,' . $id . ',id',
            'tanggal_lahir'     => 'nullable|date',
            'no_telp_mahasiswa' => 'nullable|string',
            'no_telp_ortu'      => 'nullable|string',
            'new_password'      => 'nullable|string|min:6|confirmed',
        ]);

        // Remove password from update data
        $passwordData = null;
        if (!empty($validated['new_password'])) {
            $passwordData = $validated['new_password'];
        }
        unset($validated['new_password'], $validated['new_password_confirmation']);

        $mahasiswa->update($validated);

        // Sync user account
        $user = User::where('mahasiswa_id', $mahasiswa->id)->orWhere('username', $mahasiswa->id)->first();
        if ($user) {
            $userUpdate = [
                'full_name'      => $validated['name'],
                'email'          => $validated['email'] ?? $user->email,
                'assigned_kompi' => $mahasiswa->kompi ?? $user->assigned_kompi,
            ];
            if ($passwordData) {
                $userUpdate['password'] = Hash::make($passwordData);
            }
            $user->update($userUpdate);
        }

        $msg = "Data mahasiswa {$mahasiswa->name} berhasil diperbarui.";
        if ($passwordData) $msg .= " Password telah direset.";

        return redirect()->route($this->getMahasiswaManagementRouteName('mahasiswa'))->with('success', $msg);
    }

    public function deleteMahasiswa($id)
    {
        $this->ensureMahasiswaManagementAccess();

        $mahasiswa = Mahasiswa::findOrFail($id);
        $name = $mahasiswa->name;
        \Illuminate\Support\Facades\Cache::forget('qr_svg_' . $mahasiswa->id);
        User::where('mahasiswa_id', $id)->delete();
        $mahasiswa->delete();

        return redirect()->route($this->getMahasiswaManagementRouteName('mahasiswa'))->with('success', "Mahasiswa {$name} berhasil dihapus.");
    }

    // ─── MAHASISWA EXPORT ──────────────────────────────────────────────────────
    public function loadExportStudents(Request $request)
    {
        $this->ensureMahasiswaManagementAccess();

        $jurusan = $request->input('jurusan');
        $prodi = $request->input('prodi', []);

        $query = Mahasiswa::query();

        if (!empty($jurusan)) {
            $query->where('jurusan', 'like', '%' . $jurusan . '%');
        }

        if (!empty($prodi) && is_array($prodi)) {
            $query->whereIn('prodi', $prodi);
        }

        $students = $query->select('id', 'name', 'kompi')->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'students' => $students
        ]);
    }

    public function processExportMahasiswa(Request $request)
    {
        $this->ensureMahasiswaManagementAccess();

        // Handle prodi array - convert from JSON string if needed
        $prodi = $request->input('prodi');
        if (is_string($prodi)) {
            $prodi = json_decode($prodi, true) ?? [];
        }
        if (!is_array($prodi)) {
            $prodi = [];
        }

        $validated = $request->validate([
            'export_mode' => 'required|string|in:filter,selected',
            'jurusan' => 'nullable|string',
            'selected_students' => 'nullable|array',
            'selected_students.*' => 'nullable|string',
            'export_fields' => 'required|array',
            'export_fields.*' => 'required|string|in:id,name,kompi,jurusan,prodi,tanggal_lahir,email,no_telp_mahasiswa,no_telp_ortu',
        ]);

        $query = Mahasiswa::query();

        if ($validated['export_mode'] === 'selected') {
            // Export specific selected students
            if (empty($validated['selected_students'])) {
                return back()->with('error', 'Tidak ada mahasiswa yang dipilih.');
            }
            $query->whereIn('id', $validated['selected_students']);
        } else {
            // Export based on filter (all students matching filter)
            if (!empty($validated['jurusan'])) {
                $query->where('jurusan', 'like', '%' . $validated['jurusan'] . '%');
            }

            if (!empty($prodi)) {
                $query->whereIn('prodi', $prodi);
            }
        }

        $students = $query->orderBy('prodi')->orderBy('name', 'asc')->get();

        if ($students->isEmpty()) {
            return back()->with('error', 'Tidak ada mahasiswa yang ditemukan berdasarkan filter.');
        }

        // Kelompokkan data berdasarkan prodi
        $groupedStudents = $students->groupBy(function ($s) {
            return $s->prodi && $s->prodi !== '-' && $s->prodi !== '' ? $s->prodi : 'Tanpa Prodi';
        })->sortKeys();

        // Generate Excel file with selected fields
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        // Define field labels
        $fieldLabels = [
            'id' => 'Nomor Pendaftaran',
            'name' => 'Nama',
            'kompi' => 'Kompi',
            'jurusan' => 'Jurusan',
            'prodi' => 'Prodi',
            'tanggal_lahir' => 'Tanggal Lahir',
            'email' => 'Email',
            'no_telp_mahasiswa' => 'No. Telp Mahasiswa',
            'no_telp_ortu' => 'No. Telp Ortu',
        ];

        $numCols = 1 + count($validated['export_fields']); // 1 untuk kolom No
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);

        // Judul utama dokumen
        $currentRow = 1;
        $sheet->setCellValue('A1', 'Data Mahasiswa');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->getColor()->setARGB('FF1E3A8A');
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $currentRow++;

        $isFirstGroup = true;
        foreach ($groupedStudents as $prodiName => $groupStudents) {
            if (!$isFirstGroup) {
                $currentRow += 2; // jarak antar tabel prodi
            }
            $isFirstGroup = false;

            // Judul prodi
            $prodiTitleRange = "A{$currentRow}:{$lastCol}{$currentRow}";
            $sheet->setCellValue("A{$currentRow}", 'Prodi: ' . $prodiName);
            $sheet->mergeCells($prodiTitleRange);
            $sheet->getStyle($prodiTitleRange)->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle($prodiTitleRange)->getFont()->getColor()->setARGB('FF1E40AF');
            $sheet->getStyle($prodiTitleRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');
            $sheet->getStyle($prodiTitleRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;

            // Header tabel
            $headerRow = $currentRow;
            $sheet->setCellValueByColumnAndRow(1, $headerRow, 'No');
            $col = 2;
            foreach ($validated['export_fields'] as $field) {
                $sheet->setCellValueByColumnAndRow($col++, $headerRow, $fieldLabels[$field]);
            }
            $currentRow++;

            // Data
            $dataStartRow = $currentRow;
            $no = 1;
            foreach ($groupStudents as $student) {
                $sheet->setCellValueByColumnAndRow(1, $currentRow, $no);
                $col = 2;
                foreach ($validated['export_fields'] as $field) {
                    $value = $student->{$field} ?? '';
                    if ($field === 'tanggal_lahir' && $value) {
                        $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                    }
                    $sheet->setCellValueByColumnAndRow($col++, $currentRow, $value);
                }
                $no++;
                $currentRow++;
            }
            $dataEndRow = $currentRow - 1;

            // Style header tabel (bold, background biru, putih)
            $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
            $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($headerRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension($headerRow)->setRowHeight(20);

            // Border seluruh tabel (header + data)
            $tableRange = "A{$headerRow}:{$lastCol}{$dataEndRow}";
            $sheet->getStyle($tableRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);

            // Rata tengah kolom No & vertical center untuk semua data
            $sheet->getStyle("A{$dataStartRow}:A{$dataEndRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $dataRange = "B{$dataStartRow}:{$lastCol}{$dataEndRow}";
            $sheet->getStyle($dataRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }

        // Auto-size columns
        foreach (range(1, $numCols) as $colIdx) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // ===== PRINT SETUP (Print-Ready A4 Portrait) =====
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setVerticalCentered(false);

        // Margins tipis (0.2 inci)
        $sheet->getPageMargins()
            ->setTop(0.2)
            ->setBottom(0.2)
            ->setLeft(0.2)
            ->setRight(0.2)
            ->setHeader(0.15)
            ->setFooter(0.15);

        // Repeat header row (baris pertama) di setiap halaman cetak
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        // Cetak gridlines
        $sheet->setShowGridlines(true);

        // Header/footer kosong
        $sheet->getHeaderFooter()->setOddHeader('&C&BData Mahasiswa');
        $sheet->getHeaderFooter()->setOddFooter('&RHalaman &P dari &N');

        // Print area: seluruh data
        $lastDataRow = $currentRow - 1;
        $sheet->getPageSetup()->setPrintArea("A1:{$lastCol}{$lastDataRow}");

        $excelWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $excelFileName = 'data_mahasiswa_' . date('Y-m-d_His') . '.xlsx';

        return response()->stream(
            function() use ($excelWriter) {
                $excelWriter->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment;filename="' . $excelFileName . '"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    // ─── MAHASISWA SAYA (GARDA) ──────────────────────────────────────────────
    public function mahasiswaSaya()
    {
        $user = Auth::user();
        $query = Mahasiswa::query();

        if ($user->assigned_kompi) {
            $query->where('kompi', $user->assigned_kompi);
        }

        $allKegiatan = \App\Models\PkkmbSchedule::orderBy('tanggal')->get();
        $mahasiswaList = $query->with('attendances')->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.mahasiswa-saya', compact('mahasiswaList', 'allKegiatan'));
    }

    // ─── KOMPI MANAGEMENT ────────────────────────────────────────────────────
    public function kompiManagement(Request $request)
    {
        $filterKompi = $request->query('kompi');
        $search = $request->query('search');

        $query = Mahasiswa::orderBy('kompi')->orderBy('name');

        if ($filterKompi && $filterKompi !== 'all') {
            if ($filterKompi === '__empty__') {
                $query->where(function ($q) {
                    $q->whereNull('kompi')->orWhere('kompi', '')->orWhere('kompi', '-');
                });
            } else {
                $query->where('kompi', $filterKompi);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $mahasiswaList = $query->paginate(20)->withQueryString();
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sortBy(null, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $kompiCounts = Mahasiswa::select('kompi', DB::raw('count(*) as total'))
            ->whereNotNull('kompi')
            ->where('kompi', '!=', '')
            ->where('kompi', '!=', '-')
            ->groupBy('kompi')
            ->pluck('total', 'kompi');

        return view('admin.kompi-management', compact('mahasiswaList', 'kompiOptions', 'filterKompi', 'search', 'kompiCounts'));
    }

    public function shuffleKompi(Request $request)
    {
        $kompis = \App\Models\Kompi::get()->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values();
        if ($kompis->isEmpty()) {
            return back()->with('error', 'Gagal mengacak: Belum ada data Master Kompi yang dibuat.');
        }

        $kompiNames = $kompis->pluck('nama')->toArray();
        $kompiCount = count($kompiNames);

        $shuffleMode = $request->input('shuffle_mode', 'by_jurusan');
        $distStrategy = $request->input('distribution_strategy', 'even');
        $maxPerKompi = (int) $request->input('max_per_kompi', 50);

        // Fetch all active/valid mahasiswa
        $allStudents = Mahasiswa::all();
        if ($allStudents->isEmpty()) {
            return back()->with('error', 'Gagal mengacak: Data mahasiswa kosong.');
        }

        // 1. Group / Shuffle Order according to mode
        if ($shuffleMode === 'by_prodi') {
            $grouped = $allStudents->groupBy(function ($m) {
                return !empty($m->prodi) ? $m->prodi : 'Lainnya';
            });
        } elseif ($shuffleMode === 'by_jurusan') {
            $grouped = $allStudents->groupBy(function ($m) {
                return !empty($m->jurusan) ? $m->jurusan : 'Lainnya';
            });
        } else {
            $grouped = collect(['all' => $allStudents]);
        }

        // Interleave grouped students to distribute evenly across Kompis
        $finalStudentList = collect();
        if ($shuffleMode !== 'pure_random') {
            $groupArrays = [];
            foreach ($grouped as $groupStudents) {
                $groupArrays[] = $groupStudents->shuffle()->values();
            }

            $maxInGroup = max(array_map(function ($arr) {
                return count($arr);
            }, $groupArrays));

            for ($i = 0; $i < $maxInGroup; $i++) {
                foreach ($groupArrays as $gArr) {
                    if (isset($gArr[$i])) {
                        $finalStudentList->push($gArr[$i]);
                    }
                }
            }
        } else {
            $finalStudentList = $allStudents->shuffle();
        }

        // 2. Distribute to Kompis according to strategy
        if ($distStrategy === 'max_quota' && $maxPerKompi > 0) {
            $kompiPointer = 0;
            $currentKompiCount = 0;

            foreach ($finalStudentList as $mhs) {
                if ($currentKompiCount >= $maxPerKompi && ($kompiPointer + 1) < $kompiCount) {
                    $kompiPointer++;
                    $currentKompiCount = 0;
                }

                $assignedKompi = $kompiNames[$kompiPointer];
                $mhs->update(['kompi' => $assignedKompi]);

                User::where('mahasiswa_id', $mhs->id)
                    ->orWhere('username', $mhs->id)
                    ->update(['assigned_kompi' => $assignedKompi]);

                $currentKompiCount++;
            }
            $msgStrategy = "dengan kuota maks {$maxPerKompi} orang/kompi";
        } else {
            // Even distribution (round-robin)
            foreach ($finalStudentList as $index => $mhs) {
                $assignedKompi = $kompiNames[$index % $kompiCount];
                $mhs->update(['kompi' => $assignedKompi]);

                User::where('mahasiswa_id', $mhs->id)
                    ->orWhere('username', $mhs->id)
                    ->update(['assigned_kompi' => $assignedKompi]);
            }
            $msgStrategy = "secara merata ke {$kompiCount} Kompi";
        }

        $modeLabel = $shuffleMode === 'by_prodi' ? 'Program Studi' : ($shuffleMode === 'by_jurusan' ? 'Jurusan' : 'Random');
        return redirect()->back()->with('success', "Mahasiswa berhasil diacak berdasarkan {$modeLabel} dan didistribusikan {$msgStrategy}.");
    }

    public function bulkUpdateKompi(Request $request)
    {
        $scope = $request->input('update_scope', 'selected');

        // Case 1: Server-side bulk update for ALL matching filter results
        if ($scope === 'all_filtered') {
            $targetKompiRaw = $request->input('target_kompi');
            if (empty($targetKompiRaw) && $request->has('assignments')) {
                $firstAssignment = collect($request->input('assignments'))->first();
                if ($firstAssignment && isset($firstAssignment['kompi'])) {
                    $targetKompiRaw = $firstAssignment['kompi'];
                }
            }
            $targetKompi = (!empty($targetKompiRaw) && $targetKompiRaw !== '__CLEAR__') ? $targetKompiRaw : null;

            $filterKompi = $request->input('filter_kompi');
            $search = $request->input('search');

            $query = Mahasiswa::query();

            if ($filterKompi && $filterKompi !== 'all') {
                if ($filterKompi === '__empty__') {
                    $query->where(function ($q) {
                        $q->whereNull('kompi')->orWhere('kompi', '')->orWhere('kompi', '-');
                    });
                } else {
                    $query->where('kompi', $filterKompi);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            }

            $mahasiswaIds = $query->pluck('id');
            $count = $mahasiswaIds->count();

            if ($count === 0) {
                return redirect()->back()->with('error', 'Tidak ada data mahasiswa yang sesuai dengan filter.');
            }

            // Perform bulk update in DB for Mahasiswa
            Mahasiswa::whereIn('id', $mahasiswaIds)->update(['kompi' => $targetKompi]);

            // Sync User table assigned_kompi
            User::whereIn('mahasiswa_id', $mahasiswaIds)
                ->orWhereIn('username', $mahasiswaIds)
                ->update(['assigned_kompi' => $targetKompi]);

            $label = $targetKompi ? "ke '{$targetKompi}'" : "menjadi belum ada kompi";
            return redirect()->back()->with('success', "Kompi berhasil diperbarui {$label} untuk seluruh {$count} mahasiswa hasil filter secara server-side.");
        }

        // Case 2: Selective row updates from page submission
        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.id' => 'required|string|exists:mahasiswa,id',
            'assignments.*.kompi' => 'nullable|string|max:100',
        ]);

        $updated = 0;
        foreach ($validated['assignments'] as $assignment) {
            $m = Mahasiswa::find($assignment['id']);
            if ($m) {
                $rawKompi = $assignment['kompi'] ?? null;
                $newKompi = (!empty($rawKompi) && $rawKompi !== '__CLEAR__') ? $rawKompi : null;
                
                if ($m->kompi !== $newKompi) {
                    $m->update(['kompi' => $newKompi]);

                    // Sync associated User assigned_kompi
                    User::where('mahasiswa_id', $m->id)
                        ->orWhere('username', $m->id)
                        ->update(['assigned_kompi' => $newKompi]);

                    $updated++;
                }
            }
        }

        return redirect()->back()->with('success', "Kompi berhasil diperbarui untuk {$updated} mahasiswa.");
    }

    /**
     * Download data mahasiswa per-kompi atau seluruh kompi (Excel .xlsx)
     * Layout persis sesuai format laporan Excel: Header Biru Navy, Judul Merged, Notasi NPM Rapi
     */
    public function downloadKompiData(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $kompiFilter = $request->query('kompi');

        $query = Mahasiswa::orderBy('kompi')->orderBy('name');
        if ($kompiFilter && $kompiFilter !== 'all') {
            $query->where('kompi', $kompiFilter);
        }
        $mahasiswaList = $query->get(['id', 'name', 'kompi', 'jurusan', 'prodi', 'no_telp_mahasiswa']);

        $tanggal  = now()->format('d-m-Y');
        $namaFile = $kompiFilter && $kompiFilter !== 'all'
            ? 'Data_Kompi_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $kompiFilter) . "_{$tanggal}"
            : "Data_Seluruh_Kompi_{$tanggal}";

        $title = $kompiFilter && $kompiFilter !== 'all'
            ? "Data Mahasiswa Kompi {$kompiFilter}"
            : 'Data Seluruh Kompi';

        $totalMhs = $mahasiswaList->count();

        // ─── PhpSpreadsheet setup ─────────────────────────────────────────────
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Kompi');

        // Styles
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A8A'] // Dark blue
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        // 1. Judul Utama (A1:G1)
        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A8A'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // 2. Subtitle (A2:G2)
        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', 'Dicetak: ' . now()->format('d M Y, H:i') . ' WIB  |  Total: ' . $totalMhs . ' mahasiswa');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('475569'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // 3. Row 3: Kosong

        // 4. Header Kolom (Row 4)
        $headers = ['No', 'Kompi', 'ID / No Pendaftaran', 'Nama Mahasiswa', 'Jurusan', 'Prodi', 'No. Telp'];
        $cols    = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        foreach ($headers as $idx => $hText) {
            $colLetter = $cols[$idx];
            $sheet->setCellValue("{$colLetter}4", $hText);
            $sheet->getStyle("{$colLetter}4")->applyFromArray($headerStyle);
        }
        $sheet->getRowDimension(4)->setRowHeight(26);

        // 5. Data Rows (Row 5 onwards)
        $row = 5;
        $altFill = [
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F8FAFC']
            ]
        ];

        foreach ($mahasiswaList as $no => $m) {
            // No
            $sheet->setCellValue("A{$row}", $no + 1);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // Kompi
            $sheet->setCellValue("B{$row}", $m->kompi ?? '-');
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            // ID / NPM: PENTING! setValueExplicit sebagai TYPE_STRING agar tidak jadi 2,6191E+11 di Excel
            $sheet->setCellValueExplicit("C{$row}", (string) $m->id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Nama Mahasiswa
            $sheet->setCellValue("D{$row}", $m->name);

            // Jurusan
            $sheet->setCellValue("E{$row}", $m->jurusan ?? '-');

            // Prodi
            $sheet->setCellValue("F{$row}", $m->prodi ?? '-');

            // No. Telp
            $sheet->setCellValueExplicit("G{$row}", (string) ($m->no_telp_mahasiswa ?? '-'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Zebra striping
            if ($no % 2 === 1) {
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($altFill);
            }

            $row++;
        }

        // Set Border tipis untuk tabel
        $lastRow = $row - 1;
        if ($lastRow >= 4) {
            $sheet->getStyle("A4:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('CBD5E1'));
        }

        // Auto width untuk kolom A-G
        foreach ($cols as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Output ke stream/buffer
        $writer  = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'kompi_excel_');
        $writer->save($tmpFile);
        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$namaFile}.xlsx\"",
            'Content-Length'      => strlen($content),
            'Cache-Control'       => 'no-store',
        ]);
    }



    public function history(Request $request)
    {
        $start = $request->get('start', Carbon::now()->subWeek()->toDateString());
        $end = $request->get('end', Carbon::today()->toDateString());
        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');
        $kompi = $request->get('kompi', '');
        $jurusan = $request->get('jurusan', '');

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        if ($filter === 'alpha') {
            $query = Mahasiswa::select(
                "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan", "$mhsTable.id as mahasiswa_id",
                DB::raw('null as check_in'), DB::raw('null as check_out'), DB::raw('null as date'),
                DB::raw("'alpha' as status")
            )->whereNotExists(function ($q) use ($table, $start, $end, $mhsTable) {
                $q->select(DB::raw(1))->from($table)
                    ->whereColumn("$table.mahasiswa_id", "$mhsTable.id")
                    ->whereBetween("$table.date", [$start, $end]);
            });
            
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
                ->orderBy("$table.check_in", 'desc')
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan");

            if (in_array($filter, ['hadir', 'present'])) {
                $query->whereIn("$table.status", ['hadir', 'present']);
            } elseif (in_array($filter, ['izin', 'sakit'])) {
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
        }

        // Get filter options
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sortBy(null, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $jurusanOptions = \App\Models\Jurusan::pluck('nama')->sort()->values();

        return view('admin.history', compact('attendances', 'start', 'end', 'filter', 'search', 'kompi', 'jurusan', 'kompiOptions', 'jurusanOptions'));
    }

    // ─── KEGIATAN ────────────────────────────────────────────────────────────
    public function kegiatan()
    {
        $kegiatanList = Kegiatan::orderBy('tanggal_pelaksanaan', 'desc')->get();
        return view('admin.kegiatan', compact('kegiatanList'));
    }

    // ─── MONITORING KEGIATAN ─────────────────────────────────────────────────
    public function monitoringKegiatan()
    {
        // Mengambil semua sesi dari jadwal PKKMB yang aktif untuk direkap/dimonitoring
        $kegiatanList = \App\Models\KegiatanSesi::with(['pkkmbSchedule'])
            ->join('pkkmb_schedules', 'kegiatan_sesi.pkkmb_schedule_id', '=', 'pkkmb_schedules.id')
            ->orderBy('pkkmb_schedules.tanggal', 'desc')
            ->orderBy('kegiatan_sesi.jam_mulai', 'asc')
            ->select('kegiatan_sesi.*', 'pkkmb_schedules.tanggal')
            ->get();

        return view('admin.monitoring-kegiatan', compact('kegiatanList'));
    }

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

        return view('admin.monitoring-kegiatan-detail', compact('kegiatan', 'attendances', 'totalMahasiswa', 'hadir', 'tidakHadir'));
    }

    // ─── KELULUSAN ───────────────────────────────────────────────────────────
    public function kelulusan(Request $request)
    {
        $filterProdi = $request->get('prodi');
        $filterJurusan = $request->get('jurusan');
        $search = $request->get('search');

        $query = Mahasiswa::where('is_active', 1);
        if ($filterJurusan) $query->where('jurusan', $filterJurusan);
        if ($filterProdi) $query->where('prodi', $filterProdi);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $mahasiswaPaginator = $query->paginate(20)->withQueryString();

        $mahasiswaPaginator->getCollection()->transform(function ($m) {
            $stats = $m->getCertificateStats();
            $m->total_hari   = $stats['total_sesi'];
            $m->total_hadir  = $stats['hadir_sesi'];
            $m->persentase   = $stats['persentase'];
            $m->status_lulus = $stats['can_get'] ? 'Lulus' : 'Tidak Lulus';
            return $m;
        });
        
        $kelulusanData = $mahasiswaPaginator;

        $jurusanOptions = Mahasiswa::distinct()->pluck('jurusan')->filter()->sort()->values();

        $jurusanProdiMap = [];
        $jurusanList = \App\Models\Jurusan::with('prodi')->get();
        foreach ($jurusanList as $jur) {
            $jurusanProdiMap[$jur->nama] = $jur->prodi->pluck('nama')->sort()->values()->toArray();
        }

        $prodiOptions = $filterJurusan && isset($jurusanProdiMap[$filterJurusan])
            ? collect($jurusanProdiMap[$filterJurusan])
            : Mahasiswa::distinct()->pluck('prodi')->filter()->sort()->values();

        return view('admin.kelulusan', compact('kelulusanData', 'prodiOptions', 'jurusanOptions', 'filterProdi', 'filterJurusan', 'search', 'jurusanProdiMap'));
    }

    public function toggleSertifikatLock(Request $request, $id)
    {
        $mahasiswa = Mahasiswa::findOrFail($id);
        $request->validate(['sertifikat_status' => 'required|in:auto,locked,unlocked']);
        $mahasiswa->sertifikat_status = $request->sertifikat_status;
        $mahasiswa->save();
        
        return redirect()->back()->with('success', 'Status sertifikat berhasil diperbarui.');
    }

    public function bulkToggleSertifikatLock(Request $request)
    {
        $request->validate(['sertifikat_status' => 'required|in:auto,locked,unlocked']);
        
        $query = Mahasiswa::query();
        if ($request->filled('prodi')) {
            $query->where('prodi', 'like', '%' . $request->prodi . '%');
        }
        if ($request->filled('jurusan')) {
            $query->where('jurusan', $request->jurusan);
        }
        
        $count = $query->count();
        $query->update(['sertifikat_status' => $request->sertifikat_status]);
        
        return redirect()->back()->with('success', "Status sertifikat untuk $count mahasiswa berhasil diperbarui menjadi: " . $request->sertifikat_status);
    }

    // ─── IZIN TIMDIS ─────────────────────────────────────────────────────────
    public function izin(Request $request)
    {
        $user = Auth::user();
        $izinTable = (new IzinSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();
        $filterStatus = $request->get('status', '');
        $searchQuery = $request->get('search', '');

        $query = IzinSubmission::join($mhsTable, "$izinTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$izinTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$izinTable.created_at", 'desc')
            ->orderBy("$izinTable.id", 'desc');

        if ($user->role === 'garda' && $user->assigned_kompi) {
            $query->where("$mhsTable.kompi", $user->assigned_kompi);
        }

        if ($filterStatus) {
            $query->where("$izinTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery, $mhsTable) {
                $q->where("$mhsTable.name", 'like', "%{$searchQuery}%")
                  ->orWhere("$mhsTable.id", 'like', "%{$searchQuery}%");
            });
        }

        $submissions = $query->paginate(20)->withQueryString();

        // Stats filtered by kompi for garda
        $statsQuery = IzinSubmission::query();
        if ($user->role === 'garda' && $user->assigned_kompi) {
            $statsQuery->whereHas('mahasiswa', fn($q) => $q->where('kompi', $user->assigned_kompi));
        }
        $stats = [
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
        ];

        return view('admin.izin', compact('submissions', 'stats', 'filterStatus'));
    }

    public function verifyIzin(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject,cancel',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = IzinSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        // Garda only can verify their own kompi
        $user = Auth::user();
        if ($user->role === 'garda' && $user->assigned_kompi) {
            if ($submission->mahasiswa->kompi !== $user->assigned_kompi) {
                return redirect()->back()->with('error', 'Anda hanya bisa memverifikasi pengajuan dari kompi Anda.');
            }
        }

        if ($validated['action'] === 'cancel') {
            $submission->status = 'pending';
            $submission->verified_by = null;
            $submission->verified_at = null;
            $submission->rejection_reason = null;
            $submission->save();

            // Jika dibatalkan, hapus dari tabel attendance bila sebelumnya disetujui
            Attendance::where('mahasiswa_id', $submission->mahasiswa_id)
                ->where('date', $submission->date)
                ->where('status', $submission->submission_type)
                ->delete();

            return redirect()->route('admin.izin')->with('success', 'Verifikasi pengajuan berhasil dibatalkan (dikembalikan ke Menunggu).');
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $submission->verified_by = Auth::user()->username;
        $submission->verified_at = Carbon::now();
        if ($validated['action'] === 'reject') {
            $submission->rejection_reason = $validated['rejection_reason'];
        }
        $submission->save();

        if ($validated['action'] === 'approve') {
            Attendance::updateOrCreate(
                ['mahasiswa_id' => $submission->mahasiswa_id, 'date' => $submission->date],
                [
                    'status' => $submission->submission_type,
                    'absen_by' => Auth::user()->username,
                    'notes' => $submission->keterangan ?? ucfirst($submission->submission_type) . ' Disetujui',
                ]
            );
        }

        $msg = $validated['action'] === 'approve' ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.';
        return redirect()->route('admin.izin')->with('success', $msg);
    }

    // ─── KEHADIRAN TIMDIS ────────────────────────────────────────────────────
    public function kehadiran(Request $request)
    {
        $user = Auth::user();
        $khdTable = (new KehadiranSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();
        $filterStatus = $request->get('status', '');
        $searchQuery = $request->get('search', '');

        $query = KehadiranSubmission::join($mhsTable, "$khdTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$khdTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$khdTable.created_at", 'desc')
            ->orderBy("$khdTable.id", 'desc');

        if ($user->role === 'garda' && $user->assigned_kompi) {
            $query->where("$mhsTable.kompi", $user->assigned_kompi);
        }

        if ($filterStatus) {
            $query->where("$khdTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery, $mhsTable) {
                $q->where("$mhsTable.name", 'like', "%{$searchQuery}%")
                  ->orWhere("$mhsTable.id", 'like', "%{$searchQuery}%");
            });
        }

        $submissions = $query->paginate(20)->withQueryString();

        // Stats filtered by kompi for garda
        $statsQuery = KehadiranSubmission::query();
        if ($user->role === 'garda' && $user->assigned_kompi) {
            $statsQuery->whereHas('mahasiswa', fn($q) => $q->where('kompi', $user->assigned_kompi));
        }
        $stats = [
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'approved' => (clone $statsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $statsQuery)->where('status', 'rejected')->count(),
        ];

        return view('admin.kehadiran', compact('submissions', 'stats', 'filterStatus'));
    }

    public function verifyKehadiran(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject,cancel',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = KehadiranSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        // Garda only can verify their own kompi
        $user = Auth::user();
        if ($user->role === 'garda' && $user->assigned_kompi) {
            if ($submission->mahasiswa->kompi !== $user->assigned_kompi) {
                return redirect()->back()->with('error', 'Anda hanya bisa memverifikasi kehadiran dari kompi Anda.');
            }
        }

        if ($validated['action'] === 'cancel') {
            $submission->status = 'pending';
            $submission->verified_by = null;
            $submission->verified_at = null;
            $submission->rejection_reason = null;
            $submission->save();

            // Hapus dari attendance jika sebelumnya disetujui
            $dateOnly = Carbon::parse($submission->date)->format('Y-m-d');
            Attendance::where('mahasiswa_id', $submission->mahasiswa_id)
                ->where('date', $dateOnly)
                ->where('status', 'present')
                ->delete();

            return redirect()->route('admin.kehadiran')->with('success', 'Verifikasi kehadiran berhasil dibatalkan (dikembalikan ke Menunggu).');
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $submission->verified_by = Auth::user()->username;
        $submission->verified_at = Carbon::now();
        if ($validated['action'] === 'reject') {
            $submission->rejection_reason = $validated['rejection_reason'];
        }
        $submission->save();

        if ($validated['action'] === 'approve') {
            $dateOnly = Carbon::parse($submission->date)->format('Y-m-d');
            $inTime = $request->input('check_in_time', $submission->check_in_time ?? '06:00:00');
            $outTime = $request->input('check_out_time', $submission->check_out_time ?? '17:00:00');

            if (strlen($inTime) === 5) { $inTime .= ':00'; }
            if ($outTime && strlen($outTime) === 5) { $outTime .= ':00'; }

            Attendance::updateOrCreate(
                ['mahasiswa_id' => $submission->mahasiswa_id, 'date' => $dateOnly],
                [
                    'check_in' => $dateOnly . ' ' . $inTime,
                    'check_out' => $outTime ? $dateOnly . ' ' . $outTime : null,
                    'status' => 'present',
                    'absen_by' => Auth::user()->username,
                    'notes' => $submission->keterangan ?? 'Absen Manual Disetujui',
                ]
            );
        }

        $msg = $validated['action'] === 'approve' ? 'Kehadiran disetujui.' : 'Kehadiran ditolak.';
        return redirect()->route('admin.kehadiran')->with('success', $msg);
    }

    public function deleteKehadiranSubmission($id)
    {
        $submission = KehadiranSubmission::findOrFail($id);

        if ($submission->bukti_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($submission->bukti_path);
        }

        $dateOnly = Carbon::parse($submission->date)->format('Y-m-d');
        Attendance::where('mahasiswa_id', $submission->mahasiswa_id)
            ->where('date', $dateOnly)
            ->delete();

        $submission->delete();

        return redirect()->back()->with('success', 'Pengajuan kehadiran berhasil dihapus oleh Admin.');
    }

    public function deleteIzinSubmission($id)
    {
        $submission = IzinSubmission::findOrFail($id);

        if ($submission->bukti_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($submission->bukti_path);
        }

        $dateOnly = Carbon::parse($submission->date)->format('Y-m-d');
        Attendance::where('mahasiswa_id', $submission->mahasiswa_id)
            ->where('date', $dateOnly)
            ->where('status', $submission->submission_type)
            ->delete();

        $submission->delete();

        return redirect()->back()->with('success', 'Pengajuan izin berhasil dihapus oleh Admin.');
    }

    // ─── USERS MANAGEMENT ────────────────────────────────────────────────────
    public function users(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        } else {
            $query->where('role', '!=', 'mahasiswa');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%$search%")
                    ->orWhere('full_name', 'like', "%$search%");
            });
        }
        if ($request->has('status') && $request->status !== null && $request->status !== '') {
            $query->where('is_active', (int) $request->status);
        }

        $usersList = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sortBy(null, SORT_NATURAL | SORT_FLAG_CASE)->values();

        $statsAdmin = User::where('role', 'admin')->count();
        $statsTimdis = User::where('role', 'timdis')->count();
        $statsGarda = User::where('role', 'garda')->count();
        $statsAcara = User::where('role', 'acara')->count();
        $statsMahasiswa = User::where('role', 'mahasiswa')->count();
        $statsTotal = User::where('role', '!=', 'mahasiswa')->count();

        return view('admin.users', compact('usersList', 'kompiOptions', 'statsAdmin', 'statsTimdis', 'statsGarda', 'statsAcara', 'statsMahasiswa', 'statsTotal'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|unique:users,username',
            'full_name' => 'required|string',
            'email' => 'nullable|email',
            'role' => 'required|in:admin,timdis,garda,acara',
            'password' => 'required|string|min:6',
            'assigned_kompi' => 'nullable|string|max:100',
        ]);

        $data['password'] = Hash::make($data['password']);
        if (in_array($data['role'], ['admin', 'acara'])) {
            $data['assigned_kompi'] = null;
        }
        $user = User::create($data);

        // Sync with Kompi Master Data if Garda
        if ($user->role === 'garda' && !empty($user->assigned_kompi)) {
            \App\Models\Kompi::where('nama', $user->assigned_kompi)->update(['garda_id' => $user->username]);
        }

        return redirect()->route('admin.users')->with('success', "User {$data['username']} berhasil ditambahkan.");
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $fields = $request->validate([
            'full_name' => 'required|string',
            'email' => 'nullable|email',
            'assigned_kompi' => 'nullable|string|max:100',
        ]);
        if (in_array($user->role, ['admin', 'acara'])) {
            $fields['assigned_kompi'] = null;
        }
        $user->update($fields);

        // Sync with Kompi Master Data if Garda
        if ($user->role === 'garda') {
            \App\Models\Kompi::where('garda_id', $user->username)->update(['garda_id' => null]);
            if (!empty($user->assigned_kompi)) {
                \App\Models\Kompi::where('nama', $user->assigned_kompi)->update(['garda_id' => $user->username]);
            }
        }

        return redirect()->route('admin.users')->with('success', "User {$user->username} berhasil diperbarui.");
    }

    public function activateUser($id)
    {
        User::where('id', $id)->update(['is_active' => 1]);
        return redirect()->route('admin.users')->with('success', 'User berhasil diaktifkan.');
    }

    public function deactivateUser($id)
    {
        User::where('id', $id)->update(['is_active' => 0]);
        return redirect()->route('admin.users')->with('success', 'User berhasil dinonaktifkan.');
    }

    public function resetUserPassword(Request $request, $id)
    {
        $request->validate(['new_password' => 'required|string|min:6']);
        User::where('id', $id)->update(['password' => Hash::make($request->new_password)]);
        return redirect()->route('admin.users')->with('success', 'Password berhasil di-reset.');
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        // Tidak boleh hapus diri sendiri
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }

        $username = $user->username;
        $role = $user->role;

        // Validasi agar hanya admin, garda, timdis, dan acara yang bisa dihapus lewat sini
        if (!in_array($role, ['admin', 'garda', 'timdis', 'acara'])) {
            return redirect()->route('admin.users')->with('error', 'Role user ini tidak valid untuk dihapus.');
        }

        // Jika garda, bersihkan referensi di tabel kompi
        if ($role === 'garda' && $user->assigned_kompi) {
            \App\Models\Kompi::where('garda_id', $user->username)->update(['garda_id' => null]);
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', "User {$username} ({$role}) berhasil dihapus.");
    }

    // ─── SETTINGS ────────────────────────────────────────────────────────────
    public function settings()
    {
        $yoloSettings = [];
        $settingsFile = base_path('yolo_settings.json');
        if (file_exists($settingsFile)) {
            $yoloSettings = json_decode(file_get_contents($settingsFile), true) ?? [];
        }

        return view('admin.settings', compact('yoloSettings'));
    }

    public function saveSettings(Request $request)
    {
        $settings = $request->only(['model_path', 'confidence', 'qr_cooldown']);

        $settingsFile = base_path('yolo_settings.json');
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

        try {
            Http::post('http://127.0.0.1:5000/api/python/reload-settings');
        } catch (\Exception $e) {
            // Ignore if Python backend is not running
        }

        return redirect()->route('admin.settings')->with('success', 'Pengaturan berhasil disimpan.');
    }

    // ─── EXPORT ──────────────────────────────────────────────────────────────
    public function exportAttendance(Request $request)
    {
        $start = $request->input('start', Carbon::today()->toDateString());
        $end = $request->input('end', Carbon::today()->toDateString());
        $splitMode = $request->input('split_mode', 'combined');

        $statuses = (array) $request->input('statuses', []);
        if (empty($statuses)) {
            $singleStatus = $request->input('status', $request->input('filter', 'all'));
            if ($singleStatus === 'all') {
                $statuses = ['hadir', 'izin', 'sakit', 'alpha'];
            } else {
                $statuses = [$singleStatus];
            }
        }

        $kompi = $request->input('kompi', '');
        $jurusan = $request->input('jurusan', '');
        $prodi = $request->input('prodi', '');
        $search = $request->input('search', '');
        $exportFields = $request->input('export_fields', []);

        $fieldLabelsMap = [
            'id' => 'Nomor Pendaftaran',
            'name' => 'Nama Mahasiswa',
            'email' => 'Email',
            'kompi' => 'Kompi',
            'jurusan' => 'Jurusan',
            'prodi' => 'Prodi',
            'date' => 'Tanggal',
            'check_in' => 'Jam Masuk',
            'check_out' => 'Jam Keluar',
            'status' => 'Status Absensi',
            'camera_id' => 'Kamera / Device',
        ];

        $selectedFields = !empty($exportFields) ? array_intersect(array_keys($fieldLabelsMap), (array)$exportFields) : array_keys($fieldLabelsMap);

        $requestedSheets = (array) $request->input('sheets', ['mandiri', 'reguler', 'non_mandiri', 'kompi_14']);
        if (empty($requestedSheets)) {
            $requestedSheets = ['mandiri', 'reguler', 'non_mandiri', 'kompi_14'];
        }

        $monthsIndo = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        try {
            $cStart = Carbon::parse($start)->startOfDay();
            $cEnd = Carbon::parse($end)->startOfDay();
        } catch (\Exception $e) {
            $cStart = Carbon::today();
            $cEnd = Carbon::today();
        }

        if ($cStart->gt($cEnd)) {
            $temp = $cStart;
            $cStart = $cEnd;
            $cEnd = $temp;
            $start = $cStart->toDateString();
            $end = $cEnd->toDateString();
        }

        $isMultipleDays = $cStart->toDateString() !== $cEnd->toDateString();

        // High priority: If split_mode is per_day and we have a multi-day range, export 1 Excel per day in a ZIP
        if ($splitMode === 'per_day' && $isMultipleDays) {
            return $this->exportAttendancePerDayZip(
                $cStart,
                $cEnd,
                $statuses,
                $kompi,
                $jurusan,
                $prodi,
                $search,
                $selectedFields,
                $fieldLabelsMap,
                $requestedSheets,
                $monthsIndo
            );
        }

        // Standard combined export into 1 Excel file
        $records = $this->fetchAttendanceRecordsForPeriod($start, $end, $statuses, $kompi, $jurusan, $prodi, $search);

        if ($records->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data absensi yang ditemukan berdasarkan filter yang dipilih.');
        }

        $spreadsheet = $this->createAttendanceSpreadsheet($records, $selectedFields, $fieldLabelsMap, $requestedSheets, $start, $end);
        $excelWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        $startDay = $cStart->day;
        $endDay = $cEnd->day;
        $startMonthStr = $monthsIndo[$cStart->month] ?? $cStart->format('F');
        $endMonthStr = $monthsIndo[$cEnd->month] ?? $cEnd->format('F');
        $startYear = $cStart->year;
        $endYear = $cEnd->year;

        if ($cStart->toDateString() === $cEnd->toDateString()) {
            $dateRangeStr = "{$startDay} {$startMonthStr}";
            $excelFileName = "Recap PKKMB {$startYear} {$dateRangeStr}.xlsx";
        } elseif ($startYear === $endYear && $cStart->month === $cEnd->month) {
            $dateRangeStr = "{$startDay}-{$endDay} {$startMonthStr}";
            $excelFileName = "Recap PKKMB {$startYear} {$dateRangeStr}.xlsx";
        } elseif ($startYear === $endYear) {
            $dateRangeStr = "{$startDay} {$startMonthStr} - {$endDay} {$endMonthStr}";
            $excelFileName = "Recap PKKMB {$startYear} {$dateRangeStr}.xlsx";
        } else {
            $dateRangeStr = "{$startDay} {$startMonthStr} {$startYear} - {$endDay} {$endMonthStr} {$endYear}";
            $excelFileName = "Recap PKKMB {$dateRangeStr}.xlsx";
        }

        return response()->stream(
            function () use ($excelWriter) {
                $excelWriter->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment;filename="' . $excelFileName . '"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    private function exportAttendancePerDayZip(
        Carbon $cStart,
        Carbon $cEnd,
        array $statuses,
        string $kompi,
        string $jurusan,
        string $prodi,
        string $search,
        array $selectedFields,
        array $fieldLabelsMap,
        array $requestedSheets,
        array $monthsIndo
    ) {
        if (!class_exists('\ZipArchive')) {
            return redirect()->back()->with('error', 'Ekstensi PHP ZipArchive tidak aktif pada server.');
        }

        $dates = [];
        $curr = $cStart->copy();
        while ($curr->lte($cEnd)) {
            $dates[] = $curr->toDateString();
            $curr->addDay();
        }

        $tempZipPath = tempnam(sys_get_temp_dir(), 'pkkmb_export_') . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with('error', 'Gagal membuat file ZIP untuk export.');
        }

        $addedFiles = 0;

        foreach ($dates as $singleDate) {
            $records = $this->fetchAttendanceRecordsForPeriod($singleDate, $singleDate, $statuses, $kompi, $jurusan, $prodi, $search);

            if ($records->isEmpty()) {
                continue;
            }

            $spreadsheet = $this->createAttendanceSpreadsheet($records, $selectedFields, $fieldLabelsMap, $requestedSheets, $singleDate, $singleDate);
            $excelWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

            ob_start();
            $excelWriter->save('php://output');
            $excelData = ob_get_clean();

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $excelWriter);

            $dt = Carbon::parse($singleDate);
            $monthStr = $monthsIndo[$dt->month] ?? $dt->format('F');
            $dailyFileName = "Recap PKKMB {$dt->year} {$dt->day} {$monthStr}.xlsx";

            $zip->addFromString($dailyFileName, $excelData);
            $addedFiles++;
        }

        $zip->close();

        if ($addedFiles === 0) {
            if (file_exists($tempZipPath)) {
                @unlink($tempZipPath);
            }
            return redirect()->back()->with('error', 'Tidak ada data absensi yang ditemukan pada rentang tanggal tersebut.');
        }

        $startDay = $cStart->day;
        $endDay = $cEnd->day;
        $startMonthStr = $monthsIndo[$cStart->month] ?? $cStart->format('F');
        $endMonthStr = $monthsIndo[$cEnd->month] ?? $cEnd->format('F');
        $startYear = $cStart->year;
        $endYear = $cEnd->year;

        if ($startYear === $endYear && $cStart->month === $cEnd->month) {
            $zipDownloadName = "Recap PKKMB {$startYear} {$startDay}-{$endDay} {$startMonthStr} (Per Hari).zip";
        } elseif ($startYear === $endYear) {
            $zipDownloadName = "Recap PKKMB {$startYear} {$startDay} {$startMonthStr} - {$endDay} {$endMonthStr} (Per Hari).zip";
        } else {
            $zipDownloadName = "Recap PKKMB {$startDay} {$startMonthStr} {$startYear} - {$endDay} {$endMonthStr} {$endYear} (Per Hari).zip";
        }

        return response()->download($tempZipPath, $zipDownloadName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function fetchAttendanceRecordsForPeriod(
        string $start,
        string $end,
        array $statuses,
        string $kompi,
        string $jurusan,
        string $prodi,
        string $search
    ): \Illuminate\Support\Collection {
        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        $includeAlpha = in_array('alpha', $statuses);
        $nonAlphaStatuses = array_diff($statuses, ['alpha']);

        $recordsCollection = collect();

        // 1. Fetch Attendance Records for non-alpha statuses (hadir, izin, sakit)
        if (!empty($nonAlphaStatuses)) {
            $queryAtt = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->select(
                    "$table.*",
                    "$mhsTable.id as mhs_id",
                    "$mhsTable.name",
                    "$mhsTable.kompi",
                    "$mhsTable.jurusan",
                    "$mhsTable.prodi",
                    "$mhsTable.email"
                );

            if ($start && $end) {
                $queryAtt->whereBetween("$table.date", [$start, $end]);
            } elseif ($start) {
                $queryAtt->whereDate("$table.date", '>=', $start);
            } elseif ($end) {
                $queryAtt->whereDate("$table.date", '<=', $end);
            }

            $mappedStatuses = [];
            foreach ($nonAlphaStatuses as $s) {
                if ($s === 'hadir') {
                    $mappedStatuses[] = 'hadir';
                    $mappedStatuses[] = 'present';
                } else {
                    $mappedStatuses[] = $s;
                }
            }
            $queryAtt->whereIn("$table.status", array_unique($mappedStatuses));

            if ($kompi && $kompi !== 'all') {
                $queryAtt->where("$mhsTable.kompi", $kompi);
            }
            if ($jurusan) {
                $queryAtt->where("$mhsTable.jurusan", $jurusan);
            }
            if ($prodi) {
                $queryAtt->where("$mhsTable.prodi", $prodi);
            }
            if ($search) {
                $queryAtt->where(function ($q) use ($search, $mhsTable) {
                    $q->where("$mhsTable.name", 'like', "%{$search}%")
                      ->orWhere("$mhsTable.id", 'like', "%{$search}%");
                });
            }

            $recordsCollection = $recordsCollection->concat($queryAtt->orderBy("$mhsTable.prodi", 'asc')->orderBy("$mhsTable.name", 'asc')->get());
        }

        // 2. Fetch Alpha Records if 'alpha' is checked
        if ($includeAlpha) {
            $queryAlpha = Mahasiswa::select(
                "$mhsTable.id",
                "$mhsTable.id as mahasiswa_id",
                "$mhsTable.name",
                "$mhsTable.kompi",
                "$mhsTable.jurusan",
                "$mhsTable.prodi",
                "$mhsTable.email",
                DB::raw('null as check_in'),
                DB::raw('null as check_out'),
                DB::raw('null as date'),
                DB::raw("'alpha' as status"),
                DB::raw('null as camera_id')
            )->whereNotExists(function ($q) use ($table, $mhsTable, $start, $end) {
                $q->select(DB::raw(1))->from($table)
                    ->whereColumn("$table.mahasiswa_id", "$mhsTable.id");
                if ($start && $end) {
                    $q->whereBetween("$table.date", [$start, $end]);
                }
            });

            if ($kompi && $kompi !== 'all') {
                $queryAlpha->where("$mhsTable.kompi", $kompi);
            }
            if ($jurusan) {
                $queryAlpha->where("$mhsTable.jurusan", $jurusan);
            }
            if ($prodi) {
                $queryAlpha->where("$mhsTable.prodi", $prodi);
            }
            if ($search) {
                $queryAlpha->where(function ($q) use ($search, $mhsTable) {
                    $q->where("$mhsTable.name", 'like', "%{$search}%")
                      ->orWhere("$mhsTable.id", 'like', "%{$search}%");
                });
            }

            $recordsCollection = $recordsCollection->concat($queryAlpha->orderBy("$mhsTable.prodi", 'asc')->orderBy("$mhsTable.name", 'asc')->get());
        }

        return $recordsCollection->sortBy([
            ['prodi', 'asc'],
            ['name', 'asc'],
        ]);
    }

    private function createAttendanceSpreadsheet(
        \Illuminate\Support\Collection $records,
        array $selectedFields,
        array $fieldLabelsMap,
        array $requestedSheets,
        string $start,
        string $end
    ): \PhpOffice\PhpSpreadsheet\Spreadsheet {
        $recordsMandiri = $records->filter(fn($item) => $this->isJalurMandiriRecord($item));
        $recordsExMandiri = $records->filter(fn($item) => !$this->isJalurMandiriRecord($item));
        $recordsKompi14 = $records->filter(fn($item) => $this->isKompi14Record($item));

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $sheetIndex = 0;

        // Sheet: Jalur Mandiri
        if (in_array('mandiri', $requestedSheets)) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle('Jalur Mandiri');
            $this->buildAttendanceSheet($sheet, 'Monitoring Live Absensi - Jalur Mandiri', $recordsMandiri, $selectedFields, $fieldLabelsMap, $start, $end);
            $sheetIndex++;
        }

        // Sheet: Jalur Reguler (Seluruh Jalur Kecuali Mandiri)
        if (in_array('reguler', $requestedSheets) || in_array('non_mandiri', $requestedSheets)) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle('Jalur Reguler');
            $this->buildAttendanceSheet($sheet, 'Monitoring Live Absensi - Jalur Reguler', $recordsExMandiri, $selectedFields, $fieldLabelsMap, $start, $end);
            $sheetIndex++;
        }

        // Sheet: Kompi 14 (Mahasiswa Ngulang)
        if (in_array('kompi_14', $requestedSheets)) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle('Kompi 14 (Ngulang)');
            $this->buildAttendanceSheet($sheet, 'Monitoring Live Absensi - Kompi 14 (Mahasiswa Ngulang)', $recordsKompi14, $selectedFields, $fieldLabelsMap, $start, $end);
            $sheetIndex++;
        }

        if ($sheetIndex === 0) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Monitoring Absensi');
            $this->buildAttendanceSheet($sheet, 'Monitoring Live Absensi', $records, $selectedFields, $fieldLabelsMap, $start, $end);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function isJalurMandiriRecord($item): bool
    {
        $kompi = strtolower($item->kompi ?? '');
        $id = strtoupper((string) ($item->mhs_id ?? $item->mahasiswa_id ?? $item->id ?? ''));
        $jurusan = strtolower($item->jurusan ?? '');
        $prodi = strtolower($item->prodi ?? '');

        if (str_contains($kompi, 'mandiri')) return true;
        if (str_contains($id, 'MANDIRI') || str_starts_with($id, 'MAND') || str_starts_with($id, 'MAN-') || str_starts_with($id, 'MND-')) return true;
        if (str_contains($jurusan, 'mandiri') || str_contains($prodi, 'mandiri')) return true;

        return false;
    }

    private function isKompi14Record($item): bool
    {
        $kompi = trim(strtoupper($item->kompi ?? ''));
        return $kompi === 'KOMPI 14' || $kompi === '14' || preg_match('/kompi\s*14\b/i', $kompi);
    }

    private function buildAttendanceSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $titleHeader,
        \Illuminate\Support\Collection $records,
        array $selectedFields,
        array $fieldLabelsMap,
        string $start,
        string $end
    ): void {
        $numCols = 1 + count($selectedFields); // 1 untuk No
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);

        // Header Dokumen Utama
        $currentRow = 1;
        $sheet->setCellValue('A1', $titleHeader);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF1E3A8A');
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $currentRow++;

        // Subtitle Info
        $dateSubtitle = 'Periode: ' . \Carbon\Carbon::parse($start)->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($end)->format('d/m/Y') . '  |  Dicetak: ' . now()->format('d M Y, H:i') . ' WIB  |  Total: ' . $records->count() . ' data';
        $sheet->setCellValue("A{$currentRow}", $dateSubtitle);
        $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getFont()->setSize(10)->getColor()->setARGB('FF475569');
        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($currentRow)->setRowHeight(18);
        $currentRow += 2;

        if ($records->isEmpty()) {
            $sheet->setCellValue("A{$currentRow}", 'Tidak ada data absensi untuk kategori ini.');
            $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getFont()->setItalic(true)->getColor()->setARGB('FF64748B');
            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $currentRow++;
        } else {
            // Kelompokkan data berdasarkan prodi
            $groupedData = $records->groupBy(function ($item) {
                return $item->prodi && $item->prodi !== '-' && $item->prodi !== '' ? $item->prodi : 'Tanpa Prodi';
            })->sortKeys();

            $isFirstGroup = true;
            foreach ($groupedData as $prodiName => $groupItems) {
                if (!$isFirstGroup) {
                    $currentRow += 2; // jarak antar tabel prodi
                }
                $isFirstGroup = false;

                // Header Judul Prodi
                $prodiTitleRange = "A{$currentRow}:{$lastCol}{$currentRow}";
                $sheet->setCellValue("A{$currentRow}", 'Prodi: ' . $prodiName . ' (' . count($groupItems) . ' data)');
                $sheet->mergeCells($prodiTitleRange);
                $sheet->getStyle($prodiTitleRange)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF1E40AF');
                $sheet->getStyle($prodiTitleRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');
                $sheet->getStyle($prodiTitleRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($currentRow)->setRowHeight(20);
                $currentRow++;

                // Header Tabel
                $headerRow = $currentRow;
                $sheet->setCellValueByColumnAndRow(1, $headerRow, 'No');
                $col = 2;
                foreach ($selectedFields as $field) {
                    $sheet->setCellValueByColumnAndRow($col++, $headerRow, $fieldLabelsMap[$field]);
                }
                $currentRow++;

                // Isi Data
                $dataStartRow = $currentRow;
                $no = 1;
                foreach ($groupItems as $item) {
                    $sheet->setCellValueByColumnAndRow(1, $currentRow, $no);

                    $rawStatus = strtolower($item->status ?? 'alpha');
                    $isManualAtt = !empty($item->absen_by) || $rawStatus === 'manual';

                    if ($rawStatus === 'izin') {
                        $statusText = 'Izin';
                    } elseif ($rawStatus === 'sakit') {
                        $statusText = 'Sakit';
                    } elseif ($rawStatus === 'alpha') {
                        $statusText = 'Alpha (Belum Absen)';
                    } elseif ($isManualAtt) {
                        $statusText = $item->check_out ? 'Lengkap (Absen Manual)' : 'Hadir (Absen Manual)';
                    } elseif ($item->check_out) {
                        $statusText = 'Lengkap';
                    } elseif ($item->check_in) {
                        $statusText = 'Hadir';
                    } else {
                        $statusText = 'Hadir';
                    }

                    $col = 2;
                    foreach ($selectedFields as $field) {
                        switch ($field) {
                            case 'id':
                                $val = (string) ($item->mhs_id ?? $item->mahasiswa_id ?? $item->id ?? '-');
                                $sheet->setCellValueExplicitByColumnAndRow($col, $currentRow, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                break;
                            case 'name':
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $item->name ?? '-');
                                break;
                            case 'email':
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $item->email ?? '-');
                                break;
                            case 'kompi':
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $item->kompi ?? '-');
                                break;
                            case 'jurusan':
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $item->jurusan ?? '-');
                                break;
                            case 'prodi':
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $item->prodi ?? '-');
                                break;
                            case 'date':
                                $val = $item->date ? \Carbon\Carbon::parse($item->date)->format('d/m/Y') : '-';
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $val);
                                break;
                            case 'check_in':
                                if ($item->check_in) {
                                    $val = \Carbon\Carbon::parse($item->check_in)->format('H:i');
                                } elseif ($isManualAtt) {
                                    $val = 'Manual';
                                } else {
                                    $val = '-';
                                }
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $val);
                                break;
                            case 'check_out':
                                if ($item->check_out) {
                                    $val = \Carbon\Carbon::parse($item->check_out)->format('H:i');
                                } else {
                                    $val = '-';
                                }
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $val);
                                break;
                            case 'status':
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $statusText);
                                break;
                            case 'camera_id':
                                $sheet->setCellValueByColumnAndRow($col, $currentRow, $item->camera_id ?? '-');
                                break;
                        }
                        $col++;
                    }
                    $no++;
                    $currentRow++;
                }
                $dataEndRow = $currentRow - 1;

                // Style Header Tabel (Dark Blue)
                $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A8A');
                $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($headerRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($headerRow)->setRowHeight(20);

                // Border seluruh tabel (header + data)
                $tableRange = "A{$headerRow}:{$lastCol}{$dataEndRow}";
                $sheet->getStyle($tableRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Alignment
                $sheet->getStyle("A{$dataStartRow}:A{$dataEndRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $dataRange = "B{$dataStartRow}:{$lastCol}{$dataEndRow}";
                $sheet->getStyle($dataRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
        }

        // Auto-size columns
        foreach (range(1, $numCols) as $colIdx) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Print Setup (Print-Ready A4 Portrait)
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true)
            ->setVerticalCentered(false);

        $sheet->getPageMargins()
            ->setTop(0.2)
            ->setBottom(0.2)
            ->setLeft(0.2)
            ->setRight(0.2)
            ->setHeader(0.15)
            ->setFooter(0.15);

        $sheet->setShowGridlines(true);
        $sheet->getHeaderFooter()->setOddHeader('&C&B' . $titleHeader);
        $sheet->getHeaderFooter()->setOddFooter('&RHalaman &P dari &N');

        $lastDataRow = max(1, $currentRow - 1);
        $sheet->getPageSetup()->setPrintArea("A1:{$lastCol}{$lastDataRow}");
    }

    // ─── QR CODE ─────────────────────────────────────────────────────────────
    public function getMahasiswaQR($id)
    {
        $this->ensureMahasiswaManagementAccess();

        $mahasiswa = Mahasiswa::findOrFail($id);
        
        $template = $mahasiswa->getIdCardTemplate();
        
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => "Template kartu untuk jurusan {$mahasiswa->jurusan} belum tersedia."
            ], 404);
        }
        
        $qrSvg = \Illuminate\Support\Facades\Cache::remember('qr_svg_' . $mahasiswa->id, 300, function() use ($mahasiswa) {
            $svg = '';
            if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
                $svg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->generate($mahasiswa->qr_code_id);
            }
            return $svg;
        });
        
        return response()->json([
            'success' => true, 
            'data' => [
                'qr_code_id' => $mahasiswa->qr_code_id, 
                'qr_svg' => $qrSvg,
                'template_depan' => $template['depan_url'],
                'template_belakang' => $template['belakang_url'],
                'photo_path' => $mahasiswa->photo_url,
                'name' => $mahasiswa->name,
                'kompi' => $mahasiswa->kompi,
                'prodi' => $mahasiswa->prodi
            ]
        ]);
    }

    private function ensureMahasiswaManagementAccess(): void
    {
        abort_unless(in_array(Auth::user()?->role, ['admin', 'timdis'], true), 403);
    }

    private function getMahasiswaManagementRoutePrefix(): string
    {
        return Auth::user()?->role === 'timdis' ? 'timdis' : 'admin';
    }

    private function getMahasiswaManagementRouteName(string $suffix): string
    {
        return $this->getMahasiswaManagementRoutePrefix() . '.' . $suffix;
    }

    // ─── LATE STATUS OVERRIDE ────────────────────────────────────────────────
    public function overrideLateStatus(Request $request)
    {
        $validated = $request->validate([
            'attendance_id' => 'required|integer|exists:attendance,id',
            'override_reason' => 'required|string|min:10|max:500',
        ], [
            'attendance_id.required' => 'ID attendance wajib diisi',
            'attendance_id.exists' => 'Data attendance tidak ditemukan',
            'override_reason.required' => 'Alasan override wajib diisi',
            'override_reason.min' => 'Alasan override minimal 10 karakter',
            'override_reason.max' => 'Alasan override maksimal 500 karakter',
        ]);

        $attendance = Attendance::findOrFail($validated['attendance_id']);

        // Validate that attendance is actually late and not already overridden
        if (!$attendance->is_late) {
            return redirect()->back()->with('error', 'Attendance ini tidak memiliki status telat');
        }

        if ($attendance->late_overridden) {
            return redirect()->back()->with('error', 'Status telat sudah di-override sebelumnya');
        }

        // Update attendance with override info
        $attendance->late_overridden = true;
        $attendance->overridden_by = Auth::user()->username;
        $attendance->override_reason = $validated['override_reason'];
        $attendance->override_timestamp = now();
        $attendance->save();

        $mahasiswaName = $attendance->mahasiswa->name ?? 'Unknown';
        return redirect()->back()->with('success', "Status telat untuk {$mahasiswaName} berhasil di-override");
    }

    public function cancelOverrideLateStatus(Request $request, $attendanceId)
    {
        $attendance = Attendance::findOrFail($attendanceId);

        if (!$attendance->late_overridden) {
            return redirect()->back()->with('error', 'Attendance ini tidak memiliki override');
        }

        // Cancel override
        $attendance->late_overridden = false;
        $attendance->overridden_by = null;
        $attendance->override_reason = null;
        $attendance->override_timestamp = null;
        $attendance->save();

        $mahasiswaName = $attendance->mahasiswa->name ?? 'Unknown';
        return redirect()->back()->with('success', "Override untuk {$mahasiswaName} berhasil dibatalkan");
    }

    // ─── LATE ATTENDANCE REPORT ──────────────────────────────────────────────
    public function lateAttendanceReport(Request $request)
    {
        $schedules = \App\Models\PkkmbSchedule::where('is_active', 1)->orderBy('tanggal', 'asc')->get();

        $firstSchedule = $schedules->first();
        $lastSchedule = $schedules->last();

        $start = $request->get('start', $firstSchedule ? $firstSchedule->tanggal->format('Y-m-d') : Carbon::today()->toDateString());
        $end = $request->get('end', $lastSchedule ? $lastSchedule->tanggal->format('Y-m-d') : Carbon::today()->toDateString());
        $filterKompi = $request->get('kompi');
        $filterJurusan = $request->get('jurusan');
        $search = $request->get('search');

        $scheduleDates = $schedules->filter(function ($sched) use ($start, $end) {
            $tanggal = $sched->tanggal->format('Y-m-d');
            return $tanggal >= $start && $tanggal <= $end;
        })->pluck('tanggal')->map(function ($d) {
            return $d->format('Y-m-d');
        })->values()->toArray();

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereIn("$table.date", $scheduleDates)
            ->where("$table.is_late", true)
            ->where(function ($q) use ($table) {
                $q->where("$table.late_overridden", false)
                  ->orWhereNull("$table.late_overridden");
            })
            ->select(
                "$mhsTable.id as mahasiswa_id",
                "$mhsTable.name",
                "$mhsTable.kompi",
                "$mhsTable.jurusan",
                DB::raw("COUNT($table.id) as total_late"),
                DB::raw("AVG($table.late_duration) as avg_late_duration"),
                DB::raw("MAX($table.late_duration) as max_late_duration"),
                DB::raw("MIN($table.late_duration) as min_late_duration")
            )
            ->groupBy("$mhsTable.id", "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan");

        if ($filterKompi) {
            $query->where("$mhsTable.kompi", $filterKompi);
        }
        if ($filterJurusan) {
            $query->where("$mhsTable.jurusan", $filterJurusan);
        }
        if ($search) {
            $query->where(function ($q) use ($mhsTable, $search) {
                $q->where("$mhsTable.name", 'like', "%{$search}%")
                  ->orWhere("$mhsTable.id", 'like', "%{$search}%");
            });
        }

        $lateReports = $query->orderBy('total_late', 'desc')->paginate(20)->withQueryString();

        $totalLateOccurrences = Attendance::whereIn('date', $scheduleDates)
            ->where('is_late', true)
            ->where(function ($q) {
                $q->where('late_overridden', false)->orWhereNull('late_overridden');
            })->count();

        $totalOverrides = Attendance::whereIn('date', $scheduleDates)
            ->where('is_late', true)
            ->where('late_overridden', true)
            ->count();

        $avgLateDuration = Attendance::whereIn('date', $scheduleDates)
            ->where('is_late', true)
            ->where(function ($q) {
                $q->where('late_overridden', false)->orWhereNull('late_overridden');
            })->avg('late_duration');

        $kompiOptions = Mahasiswa::distinct()->pluck('kompi')->filter()->sortBy(null, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $jurusanOptions = Mahasiswa::distinct()->pluck('jurusan')->filter()->sort()->values();

        return view('admin.late-attendance-report', compact(
            'lateReports',
            'start',
            'end',
            'filterKompi',
            'filterJurusan',
            'search',
            'totalLateOccurrences',
            'totalOverrides',
            'avgLateDuration',
            'kompiOptions',
            'jurusanOptions',
            'schedules'
        ));
    }

    public function exportLateAttendanceReport(Request $request)
    {
        $schedules = \App\Models\PkkmbSchedule::where('is_active', 1)->orderBy('tanggal', 'asc')->get();

        $firstSchedule = $schedules->first();
        $lastSchedule = $schedules->last();

        $start = $request->get('start', $firstSchedule ? $firstSchedule->tanggal->format('Y-m-d') : Carbon::today()->toDateString());
        $end = $request->get('end', $lastSchedule ? $lastSchedule->tanggal->format('Y-m-d') : Carbon::today()->toDateString());
        $filterKompi = $request->get('kompi');
        $filterJurusan = $request->get('jurusan');

        $scheduleDates = $schedules->filter(function ($sched) use ($start, $end) {
            $tanggal = $sched->tanggal->format('Y-m-d');
            return $tanggal >= $start && $tanggal <= $end;
        })->pluck('tanggal')->map(function ($d) {
            return $d->format('Y-m-d');
        })->values()->toArray();

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereIn("$table.date", $scheduleDates)
            ->where("$table.is_late", true)
            ->where(function ($q) use ($table) {
                $q->where("$table.late_overridden", false)
                  ->orWhereNull("$table.late_overridden");
            })
            ->select(
                "$mhsTable.id as mahasiswa_id",
                "$mhsTable.name",
                "$mhsTable.kompi",
                "$mhsTable.jurusan",
                DB::raw("COUNT($table.id) as total_late"),
                DB::raw("AVG($table.late_duration) as avg_late_duration"),
                DB::raw("MAX($table.late_duration) as max_late_duration"),
                DB::raw("MIN($table.late_duration) as min_late_duration")
            )
            ->groupBy("$mhsTable.id", "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan");

        if ($filterKompi) {
            $query->where("$mhsTable.kompi", $filterKompi);
        }
        if ($filterJurusan) {
            $query->where("$mhsTable.jurusan", $filterJurusan);
        }

        $lateReports = $query->orderBy('total_late', 'desc')->get();

        // Generate CSV
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Laporan_Keterlambatan_' . Carbon::now()->format('YmdHis') . '.csv"',
        ];

        $columns = ['ID Mahasiswa', 'Nama', 'Kompi', 'Jurusan', 'Total Telat', 'Rata-rata Durasi (menit)', 'Durasi Maksimal (menit)', 'Durasi Minimal (menit)'];

        $callback = function () use ($lateReports, $columns) {
            $file = fopen('php://output', 'w');
            // Add BOM for Excel UTF-8 support
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns, ';');

            foreach ($lateReports as $report) {
                fputcsv($file, [
                    $report->mahasiswa_id,
                    $report->name,
                    $report->kompi,
                    $report->jurusan,
                    $report->total_late,
                    round($report->avg_late_duration, 2),
                    $report->max_late_duration,
                    $report->min_late_duration,
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

