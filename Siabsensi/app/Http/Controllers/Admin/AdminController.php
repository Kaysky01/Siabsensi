<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CameraStream;
use App\Models\IzinSubmission;
use App\Models\Kegiatan;
use App\Models\KehadiranSubmission;
use App\Models\Mahasiswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
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
        try {
            $rawDate = $request->get('date', Carbon::today()->toDateString());
            $date = Carbon::parse($rawDate)->format('Y-m-d');
        } catch (\Exception $e) {
            $date = Carbon::today()->toDateString();
        }
        $filter = $request->get('filter', 'all');

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        if ($filter === 'alpha') {
            $attendances = Mahasiswa::select(
                "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.id as mahasiswa_id",
                DB::raw('null as check_in'), DB::raw('null as check_out'),
                DB::raw("'alpha' as status"), DB::raw('null as camera_id')
            )->whereNotExists(function ($q) use ($table, $date, $mhsTable) {
                $q->select(DB::raw(1))->from($table)
                    ->whereColumn("$table.mahasiswa_id", "$mhsTable.id")
                    ->whereDate("$table.date", $date);
            })->get();
        } elseif (in_array($filter, ['izin', 'sakit'])) {
            $attendances = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->whereDate("$table.date", $date)
                ->where("$table.status", $filter)
                ->orderBy("$table.check_in", 'desc')
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi")
                ->get();
        } else {
            $attendances = Mahasiswa::leftJoin($table, function ($join) use ($table, $mhsTable, $date) {
                $join->on("$table.mahasiswa_id", '=', "$mhsTable.id")
                    ->whereDate("$table.date", $date);
            })->select(
                "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.id as mahasiswa_id",
                "$table.check_in", "$table.check_out", "$table.camera_id",
                DB::raw("COALESCE($table.status, 'alpha') as status")
            )->orderBy("$table.check_in", 'desc')->get();
        }

        return view('admin.attendance', compact('attendances', 'date', 'filter'));
    }

    // ─── MAHASISWA ────────────────────────────────────────────────────────────
    public function mahasiswa(Request $request)
    {
        $query = Mahasiswa::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('kompi')) {
            $query->where('kompi', $request->kompi);
        }
        if ($request->filled('jurusan')) {
            $query->where('jurusan', $request->jurusan);
        }
        if ($request->filled('prodi')) {
            $query->where('prodi', $request->prodi);
        }

        $allKegiatan = \Illuminate\Support\Facades\Cache::remember('master_kegiatan', 3600, function() {
            return \App\Models\Kegiatan::orderBy('tanggal_pelaksanaan')->get();
        });
        
        $mahasiswaList = $query->orderBy('name')->paginate(50)->withQueryString();

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

        return view('admin.mahasiswa', compact('mahasiswaList', 'kompiOptions', 'jurusanOptions', 'prodiOptions', 'jurusanWithProdi', 'allKegiatan'));
    }

    public function storeMahasiswa(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'kompi' => 'required|string',
            'jurusan' => 'required|string',
            'prodi' => 'nullable|string|max:100',
            'tanggal_lahir' => 'required|date',
            'email' => 'nullable|email|unique:mahasiswa,email',
            'no_telp_mahasiswa' => 'nullable|string',
            'no_telp_ortu' => 'nullable|string',
        ]);

        $lastMahasiswa = Mahasiswa::where('id', 'like', 'MHS25%')->orderBy('id', 'desc')->first();
        $nextId = 1;
        if ($lastMahasiswa && strlen($lastMahasiswa->id) >= 11) {
            $nextId = (int) substr($lastMahasiswa->id, 5) + 1;
        }
        $validated['id'] = 'MHS25' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
        $validated['qr_code_id'] = $validated['id'];

        $mahasiswa = Mahasiswa::create($validated);

        $dob = Carbon::parse($mahasiswa->tanggal_lahir);
        $defaultPassword = $dob->format('dmY');

        User::create([
            'username' => $mahasiswa->id,
            'password' => Hash::make($defaultPassword),
            'full_name' => $mahasiswa->name,
            'email' => $mahasiswa->email,
            'role' => 'mahasiswa',
            'mahasiswa_id' => $mahasiswa->id,
            'is_active' => 1,
        ]);

        return redirect()->route('admin.mahasiswa')->with('success', "Mahasiswa {$mahasiswa->name} berhasil ditambahkan. Username/Nomor Registrasi: {$mahasiswa->id}, Password: {$defaultPassword}");
    }

    public function qrCode($id)
    {
        $mahasiswa = Mahasiswa::findOrFail($id);
        $qrImage = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->generate($mahasiswa->qr_code_id);
        return view('admin.mahasiswa-qr', compact('mahasiswa', 'qrImage'));
    }

    public function downloadTemplateCSV()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Template_Import_Mahasiswa.csv"',
        ];

        $columns = ['Nama', 'Jurusan', 'Prodi', 'Tanggal Lahir (YYYY-MM-DD)', 'Email (Opsional)', 'Telp Mahasiswa (Opsional)', 'Telp Ortu (Opsional)'];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            // Add BOM for Excel UTF-8 support
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns, ';');
            // Contoh isi data
            fputcsv($file, ['Ahmad Budi', 'Teknologi Informasi', 'Manajemen Informatika', '2005-12-31', 'ahmad@example.com', '081234567890', '081987654321'], ';');
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importMahasiswaCSV(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:2048'
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getPathname(), "r");
        
        // Detect delimiter
        $firstLine = fgets($handle);
        $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
        rewind($handle);

        // Skip BOM if exists
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Skip header
        $header = fgetcsv($handle, 1000, $delimiter);
        
        $count = 0;
        
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                // $data index: 0=Name, 1=Jurusan, 2=Prodi, 3=Tanggal Lahir (Y-m-d)
                if (count($data) < 4 || empty(trim($data[0]))) continue;

                $lastMahasiswa = Mahasiswa::where('id', 'like', 'MHS25%')->orderBy('id', 'desc')->first();
                $nextId = 1;
                if ($lastMahasiswa && strlen($lastMahasiswa->id) >= 11) {
                    $nextId = (int) substr($lastMahasiswa->id, 5) + 1;
                }
                $newId = 'MHS25' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

                $tglLahir = \Carbon\Carbon::parse(trim($data[3]))->format('Y-m-d');
                $defaultPassword = \Carbon\Carbon::parse($tglLahir)->format('dmY');

                $jurusanName = strtoupper(trim($data[1]));
                $prodiName = strtoupper(trim($data[2]));

                // Auto-create Jurusan in Master Data if not exists
                $jurusanModel = \App\Models\Jurusan::firstOrCreate(['nama' => $jurusanName]);

                // Auto-create Prodi in Master Data if not exists
                if (!empty($prodiName)) {
                    \App\Models\Prodi::firstOrCreate([
                        'jurusan_id' => $jurusanModel->id,
                        'nama' => $prodiName
                    ]);
                }

                $mhs = Mahasiswa::create([
                    'id' => $newId,
                    'qr_code_id' => $newId,
                    'name' => trim($data[0]),
                    'kompi' => '-', // Default kompi, will be assigned via shuffle
                    'jurusan' => $jurusanName,
                    'prodi' => $prodiName,
                    'tanggal_lahir' => $tglLahir,
                    'email' => isset($data[4]) ? trim($data[4]) : null,
                    'no_telp_mahasiswa' => isset($data[5]) ? trim($data[5]) : null,
                    'no_telp_ortu' => isset($data[6]) ? trim($data[6]) : null,
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
            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->with('error', 'Gagal memproses file CSV: ' . $e->getMessage());
        }
        
        fclose($handle);
        return back()->with('success', "Berhasil mengimpor $count mahasiswa dari CSV.");
    }

    public function updateMahasiswa(Request $request, $id)
    {
        $mahasiswa = Mahasiswa::findOrFail($id);

        $validated = $request->validate([
            'nim' => 'required|string|unique:mahasiswa,nim,' . $id . ',id',
            'name' => 'required|string|max:255',
            'kompi' => 'required|string',
            'jurusan' => 'required|string',
            'prodi' => 'nullable|string|max:100',
            'email' => 'nullable|email|unique:mahasiswa,email,' . $id . ',id',
            'tanggal_lahir' => 'nullable|date',
            'no_telp_mahasiswa' => 'nullable|string',
            'no_telp_ortu' => 'nullable|string',
        ]);

        $mahasiswa->update($validated);

        // Sync user account
        $user = User::where('mahasiswa_id', $mahasiswa->id)->first();
        if ($user) {
            $user->update([
                'username' => $validated['nim'],
                'full_name' => $validated['name'],
                'email' => $validated['email'] ?? $user->email,
            ]);
        }

        return redirect()->route('admin.mahasiswa')->with('success', "Data mahasiswa {$mahasiswa->name} berhasil diperbarui.");
    }

    public function deleteMahasiswa($id)
    {
        $mahasiswa = Mahasiswa::findOrFail($id);
        $name = $mahasiswa->name;
        \Illuminate\Support\Facades\Cache::forget('qr_svg_' . $mahasiswa->id);
        User::where('mahasiswa_id', $id)->delete();
        $mahasiswa->delete();

        return redirect()->route('admin.mahasiswa')->with('success', "Mahasiswa {$name} berhasil dihapus.");
    }

    // ─── MAHASISWA SAYA (GARDA) ──────────────────────────────────────────────
    public function mahasiswaSaya()
    {
        $user = auth()->user();
        $query = Mahasiswa::query();

        if ($user->assigned_kompi) {
            $query->where('kompi', $user->assigned_kompi);
        }

        $allKegiatan = \App\Models\Kegiatan::orderBy('tanggal_pelaksanaan')->get();
        $mahasiswaList = $query->with('attendances')->orderBy('name')->get();

        return view('admin.mahasiswa-saya', compact('mahasiswaList', 'allKegiatan'));
    }

    // ─── KOMPI MANAGEMENT ────────────────────────────────────────────────────
    public function kompiManagement(Request $request)
    {
        $mahasiswaList = Mahasiswa::orderBy('kompi')->orderBy('name')->get();
        $kompiOptions = \App\Models\Kompi::pluck('nama')->sort()->values();

        return view('admin.kompi-management', compact('mahasiswaList', 'kompiOptions'));
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

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        if ($filter === 'alpha') {
            $attendances = Mahasiswa::select(
                "$mhsTable.name", "$mhsTable.kompi", "$mhsTable.id as mahasiswa_id",
                DB::raw('null as check_in'), DB::raw('null as check_out'), DB::raw('null as date'),
                DB::raw("'alpha' as status")
            )->whereNotExists(function ($q) use ($table, $start, $end, $mhsTable) {
                $q->select(DB::raw(1))->from($table)
                    ->whereColumn("$table.mahasiswa_id", "$mhsTable.id")
                    ->whereBetween("$table.date", [$start, $end]);
            })->paginate(50)->withQueryString();
        } else {
            $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->whereBetween("$table.date", [$start, $end])
                ->orderBy("$table.date", 'desc')
                ->orderBy("$table.check_in", 'desc')
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi");

            if (in_array($filter, ['izin', 'sakit'])) {
                $query->where("$table.status", $filter);
            }

            $attendances = $query->paginate(50)->withQueryString();
        }

        return view('admin.history', compact('attendances', 'start', 'end', 'filter'));
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
        $kegiatanList = Kegiatan::orderBy('tanggal_pelaksanaan', 'desc')->get();
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

        $mahasiswaPaginator = $query->paginate(50)->withQueryString();
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
            $query->where('prodi', $request->prodi);
        }
        if ($request->filled('jurusan')) {
            $query->where('jurusan', $request->jurusan);
        }
        
        $count = $query->count();
        $query->update(['sertifikat_status' => $request->sertifikat_status]);
        
        return redirect()->back()->with('success', "Status sertifikat untuk $count mahasiswa berhasil diperbarui menjadi: " . $request->sertifikat_status);
    }

    // ─── IZIN TIMDIS ─────────────────────────────────────────────────────────
    public function izinTimdis(Request $request)
    {
        $izinTable = (new IzinSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();
        $filterStatus = $request->get('status', '');

        $query = IzinSubmission::join($mhsTable, "$izinTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$izinTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$izinTable.created_at", 'desc');

        if ($filterStatus) {
            $query->where("$izinTable.status", $filterStatus);
        }

        $submissions = $query->paginate(50)->withQueryString();
        $stats = [
            'pending' => IzinSubmission::where('status', 'pending')->count(),
            'approved' => IzinSubmission::where('status', 'approved')->count(),
            'rejected' => IzinSubmission::where('status', 'rejected')->count(),
        ];

        return view('admin.izin-timdis', compact('submissions', 'stats', 'filterStatus'));
    }

    public function verifyIzin(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = IzinSubmission::findOrFail($validated['submission_id']);
        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $submission->verified_by = auth()->user()->username;
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
        return redirect()->route('admin.izin-timdis')->with('success', $msg);
    }

    // ─── KEHADIRAN TIMDIS ────────────────────────────────────────────────────
    public function kehadiranTimdis(Request $request)
    {
        $khdTable = (new KehadiranSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();
        $filterStatus = $request->get('status', '');

        $query = KehadiranSubmission::join($mhsTable, "$khdTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$khdTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$khdTable.created_at", 'desc');

        if ($filterStatus) {
            $query->where("$khdTable.status", $filterStatus);
        }

        $submissions = $query->paginate(50)->withQueryString();
        $stats = [
            'pending' => KehadiranSubmission::where('status', 'pending')->count(),
            'approved' => KehadiranSubmission::where('status', 'approved')->count(),
            'rejected' => KehadiranSubmission::where('status', 'rejected')->count(),
        ];

        return view('admin.kehadiran-timdis', compact('submissions', 'stats', 'filterStatus'));
    }

    public function verifyKehadiran(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject',
            'reject_reason' => 'nullable|string',
        ]);

        $submission = KehadiranSubmission::findOrFail($validated['submission_id']);
        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $submission->verified_by = auth()->user()->username;
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
        return redirect()->route('admin.kehadiran-timdis')->with('success', $msg);
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

        $usersList = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();
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
        $mahasiswa = Mahasiswa::findOrFail($id);
        
        $qrSvg = \Illuminate\Support\Facades\Cache::remember('qr_svg_' . $mahasiswa->id, 300, function() use ($mahasiswa) {
            $svg = '';
            if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
                $svg = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->generate($mahasiswa->qr_code_id);
                $svg = str_replace(['fill="#ffffff"', 'fill="#fff"'], 'fill="transparent"', $svg);
            }
            return $svg;
        });
        
        return response()->json([
            'success' => true, 
            'data' => [
                'qr_code_id' => $mahasiswa->qr_code_id, 
                'qr_svg' => $qrSvg
            ]
        ]);
    }
}
