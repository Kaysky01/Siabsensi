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

        // Recent attendances
        $recent = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereDate("$table.date", $today)
            ->orderBy("$table.check_in", 'desc')
            ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi")
            ->take(8)
            ->get();

        // 7-day trend
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = Attendance::whereDate('date', $date->toDateString())->distinct()->count('mahasiswa_id');
            $trend[] = ['date' => $date->format('d/m'), 'count' => $count];
        }

        // By kompi
        $byKompi = DB::table($table)
            ->join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereDate("$table.date", $today)
            ->select("$mhsTable.kompi", DB::raw("count(DISTINCT $table.mahasiswa_id) as count"))
            ->groupBy("$mhsTable.kompi")
            ->get();

        $maxKompi = $byKompi->max('count') ?: 1;

        return view('admin.dashboard', compact(
            'totalMahasiswa', 'presentToday', 'absent', 'stillIn', 'pct',
            'recent', 'trend', 'byKompi', 'maxKompi'
        ));
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
                "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan", "$mhsTable.id as mahasiswa_id",
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
                $query->where("$mhsTable.name", 'like', "%{$search}%");
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
                ->orderBy("$table.check_in", 'desc')
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.jurusan");
            
            // Filter by status
            if (in_array($filter, ['hadir', 'present'])) {
                $query->whereIn("$table.status", ['hadir', 'present']);
            } else {
                $query->where("$table.status", $filter);
            }
            
            // Apply filters
            if ($search) {
                $query->where("$mhsTable.name", 'like', "%{$search}%");
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
            
            // Apply filters
            if ($search) {
                $query->where("$mhsTable.name", 'like', "%{$search}%");
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

        return view('admin.attendance', compact('attendances', 'start', 'end', 'filter', 'search', 'kompi', 'jurusan', 'kompiOptions', 'jurusanOptions'));
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
            $query->where('name', 'like', '%' . $request->search . '%');
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

                $mhs = Mahasiswa::create([
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
                ]);

                User::create([
                    'username'       => $mhs->id,
                    'password'       => $hashedPasswords[$defaultPassword],
                    'full_name'      => $mhs->name,
                    'email'          => $mhs->email,
                    'role'           => 'mahasiswa',
                    'mahasiswa_id'   => $mhs->id,
                    'assigned_kompi' => $mhs->kompi,
                    'is_active'      => 1,
                ]);

                // Update memory lookups untuk mendeteksi duplikat internal file
                $existingMahasiswaIds[$mahasiswaId] = true;
                $existingUsernames[$mahasiswaId]    = true;
                if ($email !== null) {
                    $existingEmails[$email] = true;
                }

                $count++;
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

        $query = Mahasiswa::orderBy('kompi')->orderBy('name');

        if ($filterKompi && $filterKompi !== 'all') {
            $query->where('kompi', $filterKompi);
        }

        $mahasiswaList = $query->paginate(20)->withQueryString();
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sortBy(null, SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('admin.kompi-management', compact('mahasiswaList', 'kompiOptions', 'filterKompi'));
    }

    public function shuffleKompi(Request $request)
    {
        $kompis = \App\Models\Kompi::get()->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values();
        if ($kompis->isEmpty()) {
            return back()->with('error', 'Gagal mengacak: Belum ada data Master Kompi yang dibuat.');
        }

        $kompiNames = $kompis->pluck('nama')->toArray();
        $kompiCount = count($kompiNames);

        // Group students by jurusan to ensure each kompi has all jurusans
        $mahasiswaByJurusan = Mahasiswa::get()->groupBy('jurusan');

        foreach ($mahasiswaByJurusan as $jurusan => $students) {
            // Shuffle students in this jurusan randomly
            $shuffledStudents = $students->shuffle();
            
            // Distribute them evenly in round-robin across available Kompi
            foreach ($shuffledStudents as $index => $mhs) {
                $assignedKompi = $kompiNames[$index % $kompiCount];
                $mhs->update(['kompi' => $assignedKompi]);
            }
        }

        return back()->with('success', 'Mahasiswa berhasil diacak dan didistribusikan secara merata berdasarkan Jurusan ke ' . $kompiCount . ' Kompi.');
    }

    public function bulkUpdateKompi(Request $request)
    {
        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.id' => 'required|string|exists:mahasiswa,id',
            'assignments.*.kompi' => 'required|string|max:100',
        ]);

        $updated = 0;
        foreach ($validated['assignments'] as $assignment) {
            $m = Mahasiswa::find($assignment['id']);
            if ($m && $m->kompi !== $assignment['kompi']) {
                $m->update(['kompi' => $assignment['kompi']]);
                $updated++;
            }
        }

        return redirect()->route('admin.kompi-management')->with('success', "Kompi berhasil diperbarui untuk {$updated} mahasiswa.");
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
        $headers = ['No', 'Kompi', 'ID / NPM', 'Nama Mahasiswa', 'Jurusan', 'Prodi', 'No. Telp'];
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
                $query->where("$mhsTable.name", 'like', "%{$search}%");
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
                $query->where("$mhsTable.name", 'like', "%{$search}%");
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
            ->orderBy("$izinTable.created_at", 'desc');

        if ($user->role === 'garda' && $user->assigned_kompi) {
            $query->where("$mhsTable.kompi", $user->assigned_kompi);
        }

        if ($filterStatus) {
            $query->where("$izinTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where("$mhsTable.name", 'like', "%{$searchQuery}%");
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
                ['status' => $submission->submission_type]
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
            ->orderBy("$khdTable.created_at", 'desc');

        if ($user->role === 'garda' && $user->assigned_kompi) {
            $query->where("$mhsTable.kompi", $user->assigned_kompi);
        }

        if ($filterStatus) {
            $query->where("$khdTable.status", $filterStatus);
        }

        if ($searchQuery) {
            $query->where("$mhsTable.name", 'like', "%{$searchQuery}%");
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
            Attendance::updateOrCreate(
                ['mahasiswa_id' => $submission->mahasiswa_id, 'date' => $dateOnly],
                [
                    'check_in' => $dateOnly . ' ' . $submission->check_in_time,
                    'check_out' => $submission->check_out_time ? $dateOnly . ' ' . $submission->check_out_time : null,
                    'status' => 'present',
                ]
            );
        }

        $msg = $validated['action'] === 'approve' ? 'Kehadiran disetujui.' : 'Kehadiran ditolak.';
        return redirect()->route('admin.kehadiran')->with('success', $msg);
    }

    // ─── USERS MANAGEMENT ────────────────────────────────────────────────────
    public function users(Request $request)
    {
        $query = User::where('role', '!=', 'mahasiswa');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%$search%")
                    ->orWhere('full_name', 'like', "%$search%");
            });
        }
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status);
        }

        $usersList = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sortBy(null, SORT_NATURAL | SORT_FLAG_CASE)->values();

        $statsAdmin = User::where('role', 'admin')->count();
        $statsTimdis = User::where('role', 'timdis')->count();
        $statsGarda = User::where('role', 'garda')->count();
        $statsAcara = User::where('role', 'acara')->count();
        $statsMahasiswa = User::where('role', 'mahasiswa')->count();
        $statsTotal = User::count();

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
        $start = $request->query('start');
        $end = $request->query('end');
        return Excel::download(new AttendanceExport($start, $end), 'absensi_' . date('Y-m-d') . '.xlsx');
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

