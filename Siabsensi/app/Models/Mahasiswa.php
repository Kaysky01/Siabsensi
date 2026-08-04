<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Mahasiswa extends Model
{
    use HasFactory;

    protected $table = 'mahasiswa';

    // Konfigurasi Primary Key String
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    // Hanya ada created_at di tabel
    public const UPDATED_AT = null;

    protected $fillable = [
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
        'photo_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tanggal_lahir' => 'date',
    ];

    // Relasi
    public function user()
    {
        return $this->hasOne(User::class, 'mahasiswa_id', 'id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'mahasiswa_id', 'id');
    }

    public function sessionAttendances()
    {
        return $this->hasMany(AttendanceSesi::class, 'mahasiswa_id', 'id');
    }

    public function izinSubmissions()
    {
        return $this->hasMany(IzinSubmission::class, 'mahasiswa_id', 'id');
    }

    public function kehadiranSubmissions()
    {
        return $this->hasMany(KehadiranSubmission::class, 'mahasiswa_id', 'id');
    }

    public function sertifikatHistories()
    {
        return $this->hasMany(SertifikatHistory::class, 'mahasiswa_id', 'id');
    }

    public function getRequiredProfileFields(): array
    {
        return [
            'id' => 'Nomor registrasi',
            'name' => 'Nama lengkap',
            'jurusan' => 'Jurusan Polinela',
            'prodi' => 'Prodi Polinela',
            'tanggal_lahir' => 'Tanggal lahir',
            'email' => 'Email',
            'no_telp_mahasiswa' => 'No. telp mahasiswa',
            'no_telp_ortu' => 'No. telp orang tua',
            'photo_path' => 'Foto profil',
        ];
    }

    public function getMissingProfileFields(): array
    {
        $missingFields = [];

        foreach ($this->getRequiredProfileFields() as $field => $label) {
            if ($field === 'photo_path') {
                if (!$this->hasValidProfilePhoto()) {
                    $missingFields[$field] = $label;
                }

                continue;
            }

            $value = $this->{$field} ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if (blank($value)) {
                $missingFields[$field] = $label;
            }
        }

        return $missingFields;
    }

    public function hasValidProfilePhoto(): bool
    {
        $photoPath = is_string($this->photo_path) ? trim($this->photo_path) : null;

        if (blank($photoPath)) {
            return false;
        }

        if (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')) {
            return true;
        }

        $cleanPath = ltrim($photoPath, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        } elseif (str_starts_with($cleanPath, 'public/')) {
            $cleanPath = substr($cleanPath, 7);
        }

        if (Storage::disk('public')->exists($cleanPath)) {
            return true;
        }

        if (file_exists(public_path($cleanPath)) || file_exists(public_path($photoPath)) || file_exists(storage_path('app/public/' . $cleanPath))) {
            return true;
        }

        return false;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        $photoPath = is_string($this->photo_path) ? trim($this->photo_path) : null;

        if (blank($photoPath)) {
            return null;
        }

        if (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')) {
            return $photoPath;
        }

        $cleanPath = ltrim($photoPath, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            $cleanPath = substr($cleanPath, 8);
        } elseif (str_starts_with($cleanPath, 'public/')) {
            $cleanPath = substr($cleanPath, 7);
        }

        if (Storage::disk('public')->exists($cleanPath) || file_exists(storage_path('app/public/' . $cleanPath))) {
            return url('/file-bukti/' . $cleanPath);
        }

        if (file_exists(public_path($cleanPath))) {
            return asset($cleanPath);
        }

        if (file_exists(public_path($photoPath))) {
            return asset($photoPath);
        }

        return null;
    }

    public function hasCompleteProfile(): bool
    {
        return empty($this->getMissingProfileFields());
    }

    public function calculateAlphaCount($startDate, $endDate)
    {
        $totalDays = \App\Models\Kegiatan::whereBetween('tanggal_pelaksanaan', [$startDate, $endDate])->count();
        if ($totalDays == 0) {
            $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        }

        $attendanceCount = $this->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['present', 'izin'])
            ->count();

        $alphaCount = $totalDays - $attendanceCount;

        return max(0, $alphaCount);
    }

    public function getCertificateStats()
    {
        if ($this->sertifikat_status === 'locked') {
            return [
                'can_get'    => false,
                'persentase' => 0,
                'total_sesi' => 0,
                'hadir_sesi' => 0,
                'reason'     => 'Dikunci manual oleh Admin'
            ];
        }

        if ($this->sertifikat_status === 'unlocked') {
            return [
                'can_get'    => true,
                'persentase' => 100,
                'total_sesi' => 100,
                'hadir_sesi' => 100,
                'reason'     => 'Di-unlock manual oleh Admin'
            ];
        }

        // 1. Cek berdasarkan Jadwal PKKMB (PkkmbSchedule)
        $pkkmbSchedules = \App\Models\PkkmbSchedule::where('is_active', 1)->get();
        $totalDays = $pkkmbSchedules->count();

        if ($totalDays > 0) {
            $pkkmbDates = $pkkmbSchedules->pluck('tanggal')->map(function($d) {
                return Carbon::parse($d)->format('Y-m-d');
            })->toArray();

            // Count days student has checked in (Absen Masuk Harian) on active PKKMB schedule dates
            $hadirDays = $this->attendances()
                ->daily()
                ->whereIn('date', $pkkmbDates)
                ->where(function($q) {
                    $q->whereNotNull('check_in')
                      ->orWhereIn('status', ['present', 'hadir']);
                })
                ->distinct('date')
                ->count('date');

            $persentase = round(($hadirDays / $totalDays) * 100, 1);
            return [
                'can_get'    => $persentase >= 80,
                'persentase' => $persentase,
                'total_sesi' => $totalDays,
                'hadir_sesi' => $hadirDays,
                'type'       => 'hari',
                'reason'     => $persentase >= 80 
                    ? 'Memenuhi syarat kelulusan (>= 80%)' 
                    : "Kehadiran harian PKKMB {$persentase}% (Kurang dari minimal 80%)"
            ];
        }

        // 2. Fallback jika belum ada PkkmbSchedule, hitung berdasarkan Sesi PKKMB
        $totalSesi = \App\Models\KegiatanSesi::where('is_active', 1)->count();
        if ($totalSesi > 0) {
            $hadirSesi = \App\Models\AttendanceSesi::where('mahasiswa_id', $this->id)
                ->whereIn('status', ['present', 'hadir'])
                ->count();

            $persentase = round(($hadirSesi / $totalSesi) * 100, 1);
            return [
                'can_get'    => $persentase >= 80,
                'persentase' => $persentase,
                'total_sesi' => $totalSesi,
                'hadir_sesi' => $hadirSesi,
                'type'       => 'sesi',
                'reason'     => $persentase >= 80 ? 'Memenuhi syarat kelulusan (>= 80%)' : "Kehadiran sesi {$persentase}% (Kurang dari minimal 80%)"
            ];
        }

        return [
            'can_get'    => false,
            'persentase' => 0,
            'total_sesi' => 0,
            'hadir_sesi' => 0,
            'type'       => 'hari',
            'reason'     => 'Jadwal PKKMB belum diatur'
        ];
    }

    public function canGetCertificate($startDate = null, $endDate = null)
    {
        $stats = $this->getCertificateStats();
        return $stats['can_get'];
    }

    public function getTodayAttendanceStatus()
    {
        $today = Carbon::today()->format('Y-m-d');

        $attendance = $this->attendances()
            ->where('date', $today)
            ->first();

        if (! $attendance) {
            return [
                'status' => 'pending',
                'message' => 'Belum diabsen oleh admin',
                'has_attended' => false,
            ];
        }

        return [
            'status' => $attendance->status,
            'message' => $attendance->status === 'alpha'
                ? 'Alpha (tidak hadir)'
                : ($attendance->status === 'hadir' || $attendance->status === 'present'
                    ? 'Hadir via QR Scan'
                    : 'Izin/Sakit'),
            'has_attended' => in_array($attendance->status, ['hadir', 'present', 'izin'], true),
            'check_in' => $attendance->check_in,
            'check_out' => $attendance->check_out,
        ];
    }

    /**
     * Resolve ID Card template for this Mahasiswa instance
     */
    public function getIdCardTemplate(): ?array
    {
        return self::resolveIdCardTemplate($this->jurusan);
    }

    /**
     * Smart case-insensitive and extension-agnostic ID Card Template Resolver
     */
    public static function resolveIdCardTemplate(?string $jurusan): ?array
    {
        if (empty($jurusan)) {
            return null;
        }

        $basePath = public_path('static/img');
        if (!is_dir($basePath)) {
            return null;
        }

        $rawJurusan = trim($jurusan);
        
        $aliases = [
            'ti' => 'Teknologi Informasi',
            'jti' => 'Teknologi Informasi',
            'informatika' => 'Teknologi Informasi',
            'tanaman pangan' => 'Budidaya Tanaman Pangan',
            'tanaman perkebunan' => 'Budidaya Tanaman Perkebunan',
            'perikanan' => 'Perikanan dan Kelautan',
            'kelautan' => 'Perikanan dan Kelautan',
        ];

        $lowerKey = strtolower($rawJurusan);
        if (isset($aliases[$lowerKey])) {
            $rawJurusan = $aliases[$lowerKey];
        }

        $targetDir = $basePath . DIRECTORY_SEPARATOR . $rawJurusan;
        $foundDir = null;

        if (is_dir($targetDir)) {
            $foundDir = basename($targetDir);
        } else {
            // Case-insensitive / fuzzy match directory
            $dirs = glob($basePath . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
            $cleanSearch = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $rawJurusan));

            foreach ($dirs as $dir) {
                $dirName = basename($dir);
                $cleanDir = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $dirName));

                if ($cleanDir === $cleanSearch || 
                    stripos($cleanDir, $cleanSearch) !== false || 
                    stripos($cleanSearch, $cleanDir) !== false) {
                    $targetDir = $dir;
                    $foundDir = $dirName;
                    break;
                }
            }
        }

        if (!$foundDir || !is_dir($targetDir)) {
            return null;
        }

        // Candidates for front and back images
        $depanCandidates = ['Depan.jpg', 'depan.jpg', 'Depan.png', 'depan.png', 'Depan.jpeg', 'depan.jpeg', 'DEPAN.JPG'];
        $belakangCandidates = ['Belakang.jpg', 'belakang.jpg', 'Belakang.png', 'belakang.png', 'Belakang.jpeg', 'belakang.jpeg', 'BELAKANG.JPG'];

        $depanFile = null;
        $belakangFile = null;

        foreach ($depanCandidates as $candidate) {
            if (file_exists($targetDir . DIRECTORY_SEPARATOR . $candidate)) {
                $depanFile = $candidate;
                break;
            }
        }

        foreach ($belakangCandidates as $candidate) {
            if (file_exists($targetDir . DIRECTORY_SEPARATOR . $candidate)) {
                $belakangFile = $candidate;
                break;
            }
        }

        if (!$depanFile || !$belakangFile) {
            return null;
        }

        $relativeDepan = "static/img/{$foundDir}/{$depanFile}";
        $relativeBelakang = "static/img/{$foundDir}/{$belakangFile}";

        return [
            'folder_name'        => $foundDir,
            'depan_full_path'    => $targetDir . DIRECTORY_SEPARATOR . $depanFile,
            'belakang_full_path' => $targetDir . DIRECTORY_SEPARATOR . $belakangFile,
            'template_depan'     => $relativeDepan,
            'template_belakang'  => $relativeBelakang,
            'depan_url'          => asset($relativeDepan),
            'belakang_url'       => asset($relativeBelakang),
        ];
    }
}

