<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CameraStream;
use App\Models\IzinSubmission;
use App\Models\KehadiranSubmission;
use App\Models\Mahasiswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash; // <-- untuk query builder relasi tabel
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AdminController extends Controller
{
    public function dashboard_admin()
    {
        return view('admin.dashboard');
    }

    public function processVideo(Request $request)
    {
        try {
            $validated = $request->validate([
                'video' => 'required|file|mimes:mp4,mov,avi,wmv|max:51200|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-ms-wmv',
                'action' => 'required|in:check_in,check_out',
            ]);

            $file = $validated['video'];
            $fileName = time().'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            // Simpan ke storage/app/public/videos
            $filePath = $file->storeAs('public/videos', $fileName);
            $absolutePath = storage_path('app/'.$filePath);

            // Kirim absolute path ke Python Backend
            $response = Http::post('http://127.0.0.1:5000/api/python/process-video', [
                'video_path' => $absolutePath,
                'action' => $validated['action'],
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses video di backend Python: '.$response->body(),
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat upload/proses video: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getDashboardData(Request $request)
    {
        $today = Carbon::today()->toDateString();

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        // 1. Hitung Statistik Utama
        $totalMahasiswa = Mahasiswa::count();
        $presentToday = Attendance::whereDate('date', $today)->distinct()->count('mahasiswa_id');
        $stillIn = Attendance::whereDate('date', $today)->whereNotNull('check_in')->whereNull('check_out')->count();
        $absent = max(0, $totalMahasiswa - $presentToday);

        // 2. Ambil data Absensi Terkini (Hari ini)
        $recentAttendances = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereDate("$table.date", $today)
            ->orderBy("$table.check_in", 'desc')
            ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi")
            ->take(8)
            ->get()
            ->map(function ($att) {
                return [
                    'name' => $att->name,
                    'kompi' => $att->kompi,
                    'check_in' => $att->check_in,
                    'check_out' => $att->check_out,
                    'status' => $att->status ?? 'present', // fallback status
                    'camera_id' => $att->camera_id ?? null,
                    'yolo_confidence' => $att->yolo_confidence ?? null,
                ];
            });

        // 3. Ambil data Tren 7 Hari Terakhir
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $count = Attendance::whereDate('date', $date)->distinct()->count('mahasiswa_id');
            $trend[] = [
                'date' => $date,
                'present' => $count,
            ];
        }

        // 4. Hitung Kehadiran Berdasarkan kompi Hari Ini
        $bykompi = DB::table($table)
            ->join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
            ->whereDate("$table.date", $today)
            ->select("$mhsTable.kompi", DB::raw("count(DISTINCT $table.mahasiswa_id) as count"))
            ->groupBy("$mhsTable.kompi")
            ->get();

        // Kembalikan ke format JSON untuk Javascript
        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_mahasiswa' => $totalMahasiswa,
                    'present' => $presentToday,
                    'absent' => $absent,
                    'still_in' => $stillIn,
                ],
                'recent' => $recentAttendances,
                'trend' => $trend,
                'by_kompi' => $bykompi,
            ],
        ]);
    }

    // Mengambil Data Absensi Hari Ini (Tabel)
    public function getAttendanceToday(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $filter = $request->query('filter', 'all');

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        if ($filter === 'alpha') {
            $attendances = Mahasiswa::select(
                "$mhsTable.name",
                "$mhsTable.kompi",
                DB::raw('null as check_in'),
                DB::raw('null as check_out'),
                DB::raw('null as date'),
                DB::raw("'alpha' as status"),
                DB::raw('null as camera_id'),
                DB::raw('null as yolo_confidence'),
                "$mhsTable.id as mahasiswa_id"
            )
                ->whereNotExists(function ($q) use ($table, $today, $mhsTable) {
                    $q->select(DB::raw(1))
                        ->from($table)
                        ->whereColumn("$table.mahasiswa_id", "$mhsTable.id")
                        ->whereDate("$table.date", $today);
                })
                ->get();
        } elseif ($filter === 'all') {
            $attendances = Mahasiswa::leftJoin($table, function ($join) use ($table, $mhsTable, $today) {
                $join->on("$table.mahasiswa_id", '=', "$mhsTable.id")
                    ->whereDate("$table.date", $today);
            })
                ->select(
                    "$mhsTable.name",
                    "$mhsTable.kompi",
                    "$table.check_in",
                    "$table.check_out",
                    "$table.date",
                    "$table.camera_id",
                    "$table.yolo_confidence",
                    "$mhsTable.id as mahasiswa_id",
                    DB::raw("COALESCE($table.status, 'alpha') as status")
                )
                ->orderBy("$table.check_in", 'desc')
                ->get();
        } else {
            $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->whereDate("$table.date", $today)
                ->orderBy("$table.check_in", 'desc')
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi");

            if (in_array($filter, ['izin', 'sakit'], true)) {
                $query->where("$table.status", $filter);
            }

            $attendances = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $attendances,
        ]);
    }

    // Mengambil Riwayat Absensi Berdasarkan Rentang Tanggal
    public function getAttendanceHistory(Request $request)
    {
        $start = $request->query('start');
        $end = $request->query('end');
        $filter = $request->query('filter', 'all');

        $table = (new Attendance)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        if ($filter === 'alpha') {
            $attendances = Mahasiswa::select(
                "$mhsTable.name",
                "$mhsTable.kompi",
                DB::raw('null as check_in'),
                DB::raw('null as check_out'),
                DB::raw('null as date'),
                DB::raw("'alpha' as status"),
                DB::raw('null as camera_id'),
                DB::raw('null as yolo_confidence'),
                "$mhsTable.id as mahasiswa_id"
            )
                ->whereNotExists(function ($q) use ($table, $start, $end, $mhsTable) {
                    $q->select(DB::raw(1))
                        ->from($table)
                        ->whereColumn("$table.mahasiswa_id", "$mhsTable.id")
                        ->whereBetween("$table.date", [$start, $end]);
                })
                ->get();
        } elseif ($filter === 'all' && $start && $end) {
            $attendances = Mahasiswa::leftJoin($table, function ($join) use ($table, $mhsTable, $start, $end) {
                $join->on("$table.mahasiswa_id", '=', "$mhsTable.id")
                    ->whereBetween("$table.date", [$start, $end]);
            })
                ->select(
                    "$mhsTable.name",
                    "$mhsTable.kompi",
                    "$table.check_in",
                    "$table.check_out",
                    "$table.date",
                    "$table.camera_id",
                    "$table.yolo_confidence",
                    "$mhsTable.id as mahasiswa_id",
                    DB::raw("COALESCE($table.status, 'alpha') as status")
                )
                ->orderBy("$table.date", 'desc')
                ->orderBy("$table.check_in", 'desc')
                ->get();
        } else {
            $query = Attendance::join($mhsTable, "$table.mahasiswa_id", '=', "$mhsTable.id")
                ->select("$table.*", "$mhsTable.name", "$mhsTable.kompi")
                ->orderBy("$table.date", 'desc')
                ->orderBy("$table.check_in", 'desc');

            if ($start && $end) {
                $query->whereBetween("$table.date", [$start, $end]);
            }

            if (in_array($filter, ['izin', 'sakit'], true)) {
                $query->where("$table.status", $filter);
            }

            $attendances = $query->get();
        }

        return response()->json([
            'success' => true,
            'data' => $attendances,
        ]);
    }

    // Export Absensi ke Excel
    public function exportAttendance(Request $request)
    {
        $start = $request->query('start');
        $end = $request->query('end');

        return Excel::download(new AttendanceExport($start, $end), 'absensi_'.date('Y-m-d').'.xlsx');
    }

    // Mengambil Data Kelulusan Berdasarkan Prodi / Jurusan
    public function getKelulusan(Request $request)
    {
        $query = Mahasiswa::query()->where('is_active', 1);

        if ($request->has('prodi') && $request->prodi) {
            $query->where('prodi', $request->prodi);
        }
        if ($request->has('jurusan') && $request->jurusan) {
            $query->where('jurusan', $request->jurusan);
        }

        $mahasiswa = $query->get();

        $startDate = $request->query('start', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->query('end', Carbon::now()->format('Y-m-d'));
        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;

        // Pre-load all attendances in the date range to avoid N+1
        $allAttendances = Attendance::whereBetween('date', [$startDate, $endDate])
            ->whereIn('mahasiswa_id', $mahasiswa->pluck('id'))
            ->selectRaw('mahasiswa_id, COUNT(*) as total_hadir')
            ->whereIn('status', ['present', 'hadir', 'izin'])
            ->groupBy('mahasiswa_id')
            ->pluck('total_hadir', 'mahasiswa_id');

        $data = $mahasiswa->map(function ($m) use ($totalDays, $allAttendances) {
            $hadir = (int) ($allAttendances->get($m->id, 0));

            $persentase = $totalDays > 0 ? round(($hadir / $totalDays) * 100, 2) : 0;

            return [
                'id' => $m->id,
                'name' => $m->name,
                'kompi' => $m->kompi,
                'jurusan' => $m->jurusan,
                'prodi' => $m->prodi,
                'total_hari' => $totalDays,
                'total_hadir' => $hadir,
                'persentase' => $persentase,
                'status' => $persentase >= 80 ? 'Lulus' : 'Tidak Lulus',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // Mengambil Semua Data Mahasiswa
    public function getAllMahasiswa(Request $request)
    {
        $query = Mahasiswa::query();

        if ($request->has('kompi') && $request->kompi) {
            $query->where('kompi', $request->kompi);
        }

        $mahasiswa = $query->get();

        $today = Carbon::today()->format('Y-m-d');
        $attendances = Attendance::where('date', $today)->get()->keyBy('mahasiswa_id');

        $mahasiswa->map(function ($m) use ($attendances) {
            $att = $attendances->get($m->id);

            if ($att) {
                $m->today_check_in = $att->check_in ? Carbon::parse($att->check_in)->format('H:i') : null;
                $m->today_check_out = $att->check_out ? Carbon::parse($att->check_out)->format('H:i') : null;
                $m->today_status = $att->status;
            } else {
                $m->today_check_in = null;
                $m->today_check_out = null;
                $m->today_status = 'absent';
            }

            return $m;
        });

        return response()->json([
            'success' => true,
            'data' => $mahasiswa,
        ]);
    }

    // ─── CRUD MAHASISWA ───────────────────────────────────────────
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

        $lastMahasiswa = Mahasiswa::orderBy('id', 'desc')->first();
        $nextId = $lastMahasiswa ? (int) substr($lastMahasiswa->id, 3) + 1 : 1;
        $validated['id'] = 'MHS'.str_pad($nextId, 3, '0', STR_PAD_LEFT);

        $validated['qr_code_id'] = $validated['id'];
        $mahasiswa = Mahasiswa::create($validated);

        // Generate password default dari tanggal_lahir (ddmmyyyy)
        $dob = Carbon::parse($mahasiswa->tanggal_lahir);
        $defaultPassword = $dob->format('dmY');

        // Auto-create user account for this mahasiswa
        User::create([
            'username' => $mahasiswa->id,
            'password_hash' => Hash::make($defaultPassword),
            'full_name' => $mahasiswa->name,
            'email' => $mahasiswa->email,
            'role' => 'mahasiswa',
            'mahasiswa_id' => $mahasiswa->id,
            'is_active' => 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'qr_code_id' => $mahasiswa->qr_code_id,
                'qr_image_base64' => $this->generateQrBase64($mahasiswa->qr_code_id, 200),
            ],
            'message' => 'Mahasiswa dan akun user berhasil dibuat. Username: '.$mahasiswa->id.', Password default: '.$defaultPassword,
        ]);
    }

    public function checkMahasiswa(Request $request)
    {
        $id = $request->query('id');
        $email = $request->query('email');

        $errors = [];

        if ($id) {
            $exists = Mahasiswa::where('id', $id)->exists();
            if ($exists) {
                $errors['id'] = "ID Mahasiswa '{$id}' sudah terdaftar";
            }
        }

        if ($email) {
            $exists = Mahasiswa::where('email', $email)->exists();
            if ($exists) {
                $errors['email'] = "Email '{$email}' sudah terdaftar";
            }
        }

        return response()->json([
            'success' => count($errors) === 0,
            'errors' => $errors,
        ]);
    }

    // ─── BULK UPDATE KOMPI (Panel Pengaturan Kompi) ───────────────
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

        return response()->json([
            'success' => true,
            'message' => "Kompi berhasil diperbarui untuk {$updated} mahasiswa",
            'updated' => $updated,
        ]);
    }

    public function deleteMahasiswa($id)
    {
        $mahasiswa = Mahasiswa::find($id);
        if ($mahasiswa) {
            $mahasiswa->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }

    public function updateMahasiswa(Request $request, $id)
    {
        $mahasiswa = Mahasiswa::find($id);
        if (! $mahasiswa) {
            return response()->json(['success' => false, 'message' => 'Mahasiswa tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'kompi' => 'sometimes|required|string',
            'jurusan' => 'sometimes|required|string',
            'prodi' => 'nullable|string|max:100',
            'email' => 'sometimes|required|email|unique:mahasiswa,email,'.$id.',id',
            'no_telp_mahasiswa' => 'nullable|string',
            'no_telp_ortu' => 'nullable|string',
        ]);

        $mahasiswa->update($validated);

        // Update user account associated with this mahasiswa
        $user = User::where('mahasiswa_id', $mahasiswa->id)->first();
        if ($user) {
            $updateData = [];
            if (isset($validated['name']) && $validated['name'] !== $user->full_name) {
                $updateData['full_name'] = $validated['name'];
            }
            if (isset($validated['email']) && $validated['email'] !== $user->email) {
                $updateData['email'] = $validated['email'];
            }
            if (! empty($updateData)) {
                $user->update($updateData);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $mahasiswa,
        ]);
    }

    public function getMahasiswaQR($id)
    {
        $mahasiswa = Mahasiswa::find($id);
        if (! $mahasiswa) {
            return response()->json(['success' => false], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'qr_code_id' => $mahasiswa->qr_code_id,
                'qr_image_base64' => $this->generateQrBase64($mahasiswa->qr_code_id, 300),
            ],
        ]);
    }

    private function generateQrBase64($data, $size)
    {
        // Gunakan package Laravel QR jika ada
        if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
            return base64_encode(QrCode::format('png')->size($size)->generate($data));
        }
        // Fallback URL jika package tidak ada (menggunakan API eksternal)
        try {
            $img = @file_get_contents("https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=".urlencode($data));
            if ($img) {
                return base64_encode($img);
            }
        } catch (\Exception $e) {
        }

        return '';
    }

    // ─── CRUD USERS MANAGEMENT ────────────────────────────────────
    public function getAllUsers()
    {
        return response()->json(['success' => true, 'data' => User::all()]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|unique:users,username',
            'full_name' => 'required|string',
            'email' => 'nullable|email',
            'role' => 'required|in:admin,timdis,garda,mahasiswa',
            'password' => 'required|string|min:6',
            'mahasiswa_id' => 'nullable|string',
            'assigned_kompi' => 'nullable|string|max:100',
        ]);

        $data['password_hash'] = Hash::make($data['password']);
        unset($data['password']);

        $user = User::create($data);

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['success' => false], 404);
        }

        $fields = $request->only(['full_name', 'email', 'mahasiswa_id']);
        if ($request->has('assigned_kompi')) {
            $fields['assigned_kompi'] = $request->assigned_kompi;
        }

        $user->update($fields);

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function activateUser($id)
    {
        User::where('id', $id)->update(['is_active' => 1]);

        return response()->json(['success' => true]);
    }

    public function deactivateUser($id)
    {
        User::where('id', $id)->update(['is_active' => 0]);

        return response()->json(['success' => true]);
    }

    public function resetUserPassword(Request $request, $id)
    {
        User::where('id', $id)->update(['password_hash' => Hash::make($request->new_password)]);

        return response()->json(['success' => true]);
    }

    // ─── VERIFIKASI IZIN (TIMDIS & ADMIN) ────────────────────────────────
    public function getIzinSubmissions(Request $request)
    {
        $izinTable = (new IzinSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        $query = IzinSubmission::join($mhsTable, "$izinTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$izinTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$izinTable.created_at", 'desc');

        if ($request->has('status') && $request->status) {
            $query->where("$izinTable.status", $request->status);
        }

        $submissions = $query->get();
        $stats = [
            'pending' => IzinSubmission::where('status', 'pending')->count(),
            'approved' => IzinSubmission::where('status', 'approved')->count(),
            'rejected' => IzinSubmission::where('status', 'rejected')->count(),
        ];

        return response()->json(['success' => true, 'data' => ['submissions' => $submissions, 'stats' => $stats]]);
    }

    public function verifyIzin(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject',
            'verified_by' => 'required|string',
            'rejection_reason' => 'nullable|string',
        ]);

        $submission = IzinSubmission::find($validated['submission_id']);
        if (! $submission) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $submission->verified_by = $validated['verified_by'];
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

        return response()->json(['success' => true]);
    }

    // ─── VERIFIKASI KEHADIRAN (TIMDIS & ADMIN) ───────────────────────────
    public function getKehadiranSubmissions(Request $request)
    {
        $khdTable = (new KehadiranSubmission)->getTable();
        $mhsTable = (new Mahasiswa)->getTable();

        $query = KehadiranSubmission::join($mhsTable, "$khdTable.mahasiswa_id", '=', "$mhsTable.id")
            ->select("$khdTable.*", "$mhsTable.name", "$mhsTable.kompi")
            ->orderBy("$khdTable.created_at", 'desc');

        if ($request->has('status') && $request->status) {
            $query->where("$khdTable.status", $request->status);
        }

        $submissions = $query->get();
        $stats = [
            'pending' => KehadiranSubmission::where('status', 'pending')->count(),
            'approved' => KehadiranSubmission::where('status', 'approved')->count(),
            'rejected' => KehadiranSubmission::where('status', 'rejected')->count(),
        ];

        return response()->json(['success' => true, 'data' => ['submissions' => $submissions, 'stats' => $stats]]);
    }

    public function verifyKehadiran(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'action' => 'required|in:approve,reject',
            'verified_by' => 'required|string',
            'reject_reason' => 'nullable|string',
        ]);

        $submission = KehadiranSubmission::find($validated['submission_id']);
        if (! $submission) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $submission->status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $submission->verified_by = $validated['verified_by'];
        $submission->verified_at = Carbon::now();
        if ($validated['action'] === 'reject') {
            $submission->rejection_reason = $validated['reject_reason'];
        }
        $submission->save();

        if ($validated['action'] === 'approve') {
            // Ambil format Y-m-d saja agar tidak terjadi penumpukan dengan waktu bawaan (00:00:00)
            $dateOnly = Carbon::parse($submission->date)->format('Y-m-d');

            Attendance::updateOrCreate(
                ['mahasiswa_id' => $submission->mahasiswa_id, 'date' => $dateOnly],
                [
                    'check_in' => $dateOnly.' '.$submission->check_in_time,
                    'check_out' => $submission->check_out_time ? $dateOnly.' '.$submission->check_out_time : null,
                    'status' => 'present',
                ]
            );
        }

        return response()->json(['success' => true]);
    }

    // ─── CRUD KAMERA (STREAM) ─────────────────────────────────────
    public function getCameras()
    {
        return response()->json(['success' => true, 'data' => CameraStream::all()]);
    }

    public function getAvailableWebcams()
    {
        // Return available webcam indices (0, 1, 2, etc.)
        // In a real implementation, this would detect actual webcams connected to the server
        // For now, return a list of common webcam indices
        $webcams = [
            ['index' => 0, 'name' => 'Webcam 0', 'resolution' => '640x480'],
            ['index' => 1, 'name' => 'Webcam 1', 'resolution' => '640x480'],
            ['index' => 2, 'name' => 'Webcam 2', 'resolution' => '640x480'],
        ];

        return response()->json(['success' => true, 'data' => $webcams]);
    }

    public function storeCamera(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|string|max:50|unique:camera_streams,id',
            'name' => 'required|string|max:255',
            'camera_index' => 'required|integer|min:0|max:10',
            'location' => 'nullable|string|max:255',
        ]);

        try {
            $data = [
                'id' => $validated['id'],
                'name' => $validated['name'],
                'rtsp_url' => (string) $validated['camera_index'],
                'location' => $validated['location'] ?? null,
                'is_active' => 0,
            ];
            $c = CameraStream::create($data);

            return response()->json(['success' => true, 'data' => $c]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateCamera(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'camera_index' => 'sometimes|required|integer|min:0|max:10',
            'location' => 'nullable|string|max:255',
        ]);

        try {
            $data = [];
            if (isset($validated['camera_index'])) {
                $data['rtsp_url'] = (string) $validated['camera_index'];
            }
            if (isset($validated['name'])) {
                $data['name'] = $validated['name'];
            }
            if (isset($validated['location'])) {
                $data['location'] = $validated['location'];
            }

            CameraStream::where('id', $id)->update($data);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteCamera($id)
    {
        CameraStream::destroy($id);

        return response()->json(['success' => true]);
    }

    public function saveRtspSettings(Request $request)
    {
        try {
            $settings = $request->only([
                'frame_width',
                'frame_height',
                'frame_fps',
                'reconnect_delay',
                'confidence_threshold',
                'qr_cooldown',
            ]);

            // Save settings to a JSON file or database
            $settingsFile = base_path('rtsp_settings.json');
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

            // Reload Python backend settings
            try {
                Http::post('http://127.0.0.1:5000/api/python/reload-settings');
            } catch (\Exception $e) {
                // Ignore if Python backend is not running
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengaturan RTSP disimpan',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getYoloSettings()
    {
        try {
            $settingsFile = base_path('yolo_settings.json');
            if (! file_exists($settingsFile)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Settings file not found',
                ], 404);
            }

            $settings = json_decode(file_get_contents($settingsFile), true);

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function saveYoloSettings(Request $request)
    {
        try {
            $settings = $request->only([
                'model_path',
                'confidence',
                'qr_cooldown',
            ]);

            // Normalize model path to use relative path from python_backend directory
            if (isset($settings['model_path'])) {
                // Convert Laravel-relative path (models/...) to Python-backend-relative path (../models/...)
                if (strpos($settings['model_path'], 'models/') === 0) {
                    $settings['model_path'] = '../'.$settings['model_path'];
                }
                // Remove any duplicate ../ prefixes
                $settings['model_path'] = preg_replace('/^\.+\/+/', '../', $settings['model_path']);
            }

            // Save settings to a JSON file or database
            $settingsFile = base_path('yolo_settings.json');
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));

            // Reload Python backend settings
            try {
                Http::post('http://127.0.0.1:5000/api/python/reload-settings');
            } catch (\Exception $e) {
                // Ignore if Python backend is not running
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengaturan YOLO disimpan',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
