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
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sort()->values();
        $jurusanOptions = \App\Models\Jurusan::pluck('nama')->sort()->values();

        return view('admin.attendance', compact('attendances', 'start', 'end', 'filter', 'search', 'kompi', 'jurusan', 'kompiOptions', 'jurusanOptions'));
    }

    // ─── MAHASISWA ────────────────────────────────────────────────────────────
    public function mahasiswa(Request $request)
    {
        $this->ensureMahasiswaManagementAccess();

        $query = Mahasiswa::query();

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

        $allKegiatan = \App\Models\PkkmbSchedule::orderBy('tanggal')->get();

        $mahasiswaList = $query->with('attendances')->orderBy('name')->paginate(20)->withQueryString();

        $kompiOptions = \Illuminate\Support\Facades\Cache::remember('master_kompi', 3600, function() {
            return \App\Models\Kompi::pluck('nama')->sort()->values();
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

    public function storeMahasiswa(Request $request)
    {
        $this->ensureMahasiswaManagementAccess();

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
            'username'     => $mahasiswa->id,
            'password'     => Hash::make($defaultPassword),
            'full_name'    => $mahasiswa->name,
            'email'        => $mahasiswa->email,
            'role'         => 'mahasiswa',
            'mahasiswa_id' => $mahasiswa->id,
            'is_active'    => 1,
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
        $this->ensureMahasiswaManagementAccess();

        $request->validate([
            'csv_file' => 'required|mimes:csv,txt,xls,xlsx|max:5120'
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

                if (Mahasiswa::where('id', $mahasiswaId)->exists() || User::where('username', $mahasiswaId)->exists()) {
                    throw new \RuntimeException("Baris {$rowNumber}: nomor registrasi/username {$mahasiswaId} sudah digunakan.");
                }

                $tanggalLahirRaw = $record['tanggal_lahir'] ?? null;
                $tanggalLahir = $this->parseMahasiswaImportDate($tanggalLahirRaw, $rowNumber);
                $defaultPassword = Carbon::parse($tanggalLahir)->format('dmY');

                $jurusanProdi = $this->resolveImportedJurusanProdi(
                    $record['jurusan'] ?? null,
                    $record['prodi'] ?? null,
                    $rowNumber
                );

                $email = $this->normalizeMahasiswaImportValue($record['email'] ?? null);
                if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException("Baris {$rowNumber}: format email tidak valid.");
                }
                if ($email !== null && Mahasiswa::where('email', $email)->exists()) {
                    throw new \RuntimeException("Baris {$rowNumber}: email {$email} sudah digunakan.");
                }

                $kompi = $this->normalizeMahasiswaImportValue($record['kompi'] ?? null) ?? '-';

                $mhs = Mahasiswa::create([
                    'id' => $mahasiswaId,
                    'qr_code_id' => $mahasiswaId,
                    'name' => $name,
                    'kompi' => $kompi,
                    'jurusan' => $jurusanProdi['jurusan'],
                    'prodi' => $jurusanProdi['prodi'],
                    'tanggal_lahir' => $tanggalLahir,
                    'email' => $email,
                    'no_telp_mahasiswa' => $this->normalizeMahasiswaImportValue($record['no_telp_mahasiswa'] ?? null),
                    'no_telp_ortu' => $this->normalizeMahasiswaImportValue($record['no_telp_ortu'] ?? null),
                ]);

                User::create([
                    'username' => $mhs->id,
                    'password' => Hash::make($defaultPassword),
                    'full_name' => $mhs->name,
                    'email' => $mhs->email,
                    'role' => 'mahasiswa',
                    'mahasiswa_id' => $mhs->id,
                    'is_active' => 1,
                ]);

                $count++;
            }

            DB::commit();
        } catch (\Exception $e) {
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
            $sheet = IOFactory::load($path)->getActiveSheet();
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

    private function parseMahasiswaImportDate($value, int $rowNumber): string
    {
        if ($value === null || $value === '') {
            throw new \RuntimeException("Baris {$rowNumber}: tanggal lahir wajib diisi.");
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            }

            $dateString = trim((string) $value);

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
        $user = User::where('mahasiswa_id', $mahasiswa->id)->first();
        if ($user) {
            $userUpdate = [
                'full_name' => $validated['name'],
                'email'     => $validated['email'] ?? $user->email,
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
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sort()->values();

        return view('admin.kompi-management', compact('mahasiswaList', 'kompiOptions', 'filterKompi'));
    }

    public function shuffleKompi(Request $request)
    {
        $kompis = \App\Models\Kompi::all();
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
            Mahasiswa::where('id', $assignment['id'])->update(['kompi' => $assignment['kompi']]);
            $updated++;
        }

        return redirect()->route('admin.kompi-management')->with('success', "Kompi berhasil diperbarui untuk {$updated} mahasiswa.");
    }

    // ─── HISTORY ─────────────────────────────────────────────────────────────
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
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sort()->values();
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

        $query = Mahasiswa::where('is_active', 1);
        if ($filterProdi) $query->where('prodi', $filterProdi);
        if ($filterJurusan) $query->where('jurusan', $filterJurusan);

        $mahasiswaPaginator = $query->paginate(20)->withQueryString();
        // Menghitung seluruh jumlah kegiatan tanpa filter tanggal
        $totalDays = \App\Models\Kegiatan::count();
        if ($totalDays == 0) {
            $totalDays = 1; // Prevent division by zero
        }

        $allAttendances = Attendance::whereIn('mahasiswa_id', $mahasiswaPaginator->pluck('id'))
            ->where(function ($query) {
                $query->whereIn('status', ['izin', 'sakit'])
                      ->orWhere(function ($q) {
                          $q->whereIn('status', ['present', 'hadir'])
                            ->whereNotNull('check_in')
                            ->whereNotNull('check_out');
                      });
            })
            ->selectRaw('mahasiswa_id, COUNT(*) as total_hadir')
            ->groupBy('mahasiswa_id')
            ->pluck('total_hadir', 'mahasiswa_id');

        $mahasiswaPaginator->getCollection()->transform(function ($m) use ($totalDays, $allAttendances) {
            $hadir = (int) ($allAttendances->get($m->id, 0));
            $persentase = $totalDays > 0 ? round(($hadir / $totalDays) * 100, 2) : 0;
            $m->total_hari = $totalDays;
            $m->total_hadir = $hadir;
            $m->persentase = $persentase;
            
            if ($m->sertifikat_status === 'locked') {
                $m->status_lulus = 'Tidak Lulus';
            } elseif ($m->sertifikat_status === 'unlocked') {
                $m->status_lulus = 'Lulus';
            } else {
                $m->status_lulus = $persentase >= 80 ? 'Lulus' : 'Tidak Lulus';
            }
            return $m;
        });
        
        $kelulusanData = $mahasiswaPaginator;

        $prodiOptions = Mahasiswa::distinct()->pluck('prodi')->filter()->sort()->values();
        $jurusanOptions = Mahasiswa::distinct()->pluck('jurusan')->filter()->sort()->values();

        return view('admin.kelulusan', compact('kelulusanData', 'prodiOptions', 'jurusanOptions', 'filterProdi', 'filterJurusan'));
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

        $query = IzinSubmission::join($mhsTable, "$izinTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$izinTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$izinTable.created_at", 'desc');

        if ($user->role === 'garda' && $user->assigned_kompi) {
            $query->where("$mhsTable.kompi", $user->assigned_kompi);
        }

        if ($filterStatus) {
            $query->where("$izinTable.status", $filterStatus);
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
            'action' => 'required|in:approve,reject',
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

        $query = KehadiranSubmission::join($mhsTable, "$khdTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$khdTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$khdTable.created_at", 'desc');

        if ($user->role === 'garda' && $user->assigned_kompi) {
            $query->where("$mhsTable.kompi", $user->assigned_kompi);
        }

        if ($filterStatus) {
            $query->where("$khdTable.status", $filterStatus);
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
            'action' => 'required|in:approve,reject',
            'reject_reason' => 'nullable|string',
        ]);

        $submission = KehadiranSubmission::with('mahasiswa')->findOrFail($validated['submission_id']);

        // Garda only can verify their own kompi
        $user = Auth::user();
        if ($user->role === 'garda' && $user->assigned_kompi) {
            if ($submission->mahasiswa->kompi !== $user->assigned_kompi) {
                return redirect()->back()->with('error', 'Anda hanya bisa memverifikasi kehadiran dari kompi Anda.');
            }
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $submission->verified_by = Auth::user()->username;
        $submission->verified_at = Carbon::now();
        if ($validated['action'] === 'reject') {
            $submission->rejection_reason = $validated['reject_reason'];
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
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sort()->values();

        $statsAdmin = User::where('role', 'admin')->count();
        $statsTimdis = User::where('role', 'timdis')->count();
        $statsGarda = User::where('role', 'garda')->count();
        $statsMahasiswa = User::where('role', 'mahasiswa')->count();
        $statsTotal = User::count();

        return view('admin.users', compact('usersList', 'kompiOptions', 'statsAdmin', 'statsTimdis', 'statsGarda', 'statsMahasiswa', 'statsTotal'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|unique:users,username',
            'full_name' => 'required|string',
            'email' => 'nullable|email',
            'role' => 'required|in:admin,timdis,garda',
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
        
        // Get jurusan folder
        $jurusanFolder = $mahasiswa->jurusan;
        
        // Check if templates exist
        $depanPath = public_path("static/img/{$jurusanFolder}/Depan.jpg");
        $belakangPath = public_path("static/img/{$jurusanFolder}/Belakang.jpg");
        
        if (!file_exists($depanPath) || !file_exists($belakangPath)) {
            return response()->json([
                'success' => false,
                'message' => "Template kartu untuk jurusan {$jurusanFolder} belum tersedia."
            ], 404);
        }
        
        $qrSvg = \Illuminate\Support\Facades\Cache::remember('qr_svg_' . $mahasiswa->id, 300, function() use ($mahasiswa) {
            $svg = '';
            if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
                $svg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->generate($mahasiswa->qr_code_id);
            }
            return $svg;
        });
        
        $templateDepan = "static/img/{$jurusanFolder}/Depan.jpg";
        $templateBelakang = "static/img/{$jurusanFolder}/Belakang.jpg";
        
        return response()->json([
            'success' => true, 
            'data' => [
                'qr_code_id' => $mahasiswa->qr_code_id, 
                'qr_svg' => $qrSvg,
                'template_depan' => asset($templateDepan),
                'template_belakang' => asset($templateBelakang),
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
        $start = $request->get('start', Carbon::now()->startOfMonth()->toDateString());
        $end = $request->get('end', Carbon::today()->toDateString());
        $filterKompi = $request->get('kompi');
        $filterJurusan = $request->get('jurusan');

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        // Build query for late attendance
        $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereBetween("$table.date", [$start, $end])
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

        // Apply filters
        if ($filterKompi) {
            $query->where("$mhsTable.kompi", $filterKompi);
        }
        if ($filterJurusan) {
            $query->where("$mhsTable.jurusan", $filterJurusan);
        }

        $lateReports = $query->orderBy('total_late', 'desc')->paginate(20)->withQueryString();

        // Calculate summary statistics
        $summaryQuery = Attendance::whereBetween('date', [$start, $end]);
        
        $totalLateOccurrences = $summaryQuery->where('is_late', true)
            ->where(function ($q) {
                $q->where('late_overridden', false)->orWhereNull('late_overridden');
            })->count();
        
        $totalOverrides = Attendance::whereBetween('date', [$start, $end])
            ->where('is_late', true)
            ->where('late_overridden', true)
            ->count();
        
        $avgLateDuration = $summaryQuery->where('is_late', true)
            ->where(function ($q) {
                $q->where('late_overridden', false)->orWhereNull('late_overridden');
            })->avg('late_duration');

        // Get filter options
        $kompiOptions = Mahasiswa::distinct()->pluck('kompi')->filter()->sort()->values();
        $jurusanOptions = Mahasiswa::distinct()->pluck('jurusan')->filter()->sort()->values();

        return view('admin.late-attendance-report', compact(
            'lateReports',
            'start',
            'end',
            'filterKompi',
            'filterJurusan',
            'totalLateOccurrences',
            'totalOverrides',
            'avgLateDuration',
            'kompiOptions',
            'jurusanOptions'
        ));
    }

    public function exportLateAttendanceReport(Request $request)
    {
        $start = $request->get('start', Carbon::now()->startOfMonth()->toDateString());
        $end = $request->get('end', Carbon::today()->toDateString());
        $filterKompi = $request->get('kompi');
        $filterJurusan = $request->get('jurusan');

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        // Build query for late attendance
        $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereBetween("$table.date", [$start, $end])
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

        // Apply filters
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
