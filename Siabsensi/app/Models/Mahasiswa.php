<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function canGetCertificate($startDate = null, $endDate = null)
    {
        if ($this->sertifikat_status === 'locked') return false;
        if ($this->sertifikat_status === 'unlocked') return true;

        $totalDays = \App\Models\Kegiatan::count();
        
        // Prevent auto-unlock if no activities exist
        if ($totalDays == 0) return false;

        $attendanceCount = $this->attendances()
            ->where(function ($query) {
                $query->whereIn('status', ['izin', 'sakit'])
                      ->orWhere(function ($q) {
                          $q->whereIn('status', ['present', 'hadir'])
                            ->whereNotNull('check_in')
                            ->whereNotNull('check_out');
                      });
            })
            ->count();

        // Prevent auto-unlock when there are 0 valid attendances
        if ($attendanceCount == 0) return false;

        $persentase = ($attendanceCount / $totalDays) * 100;

        return $persentase >= 80;
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
}
