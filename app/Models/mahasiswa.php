<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
        'kelompok',
        'jurusan',
        'email',
        'no_telp_mahasiswa',
        'no_telp_ortu',
        'qr_code_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        
        $attendanceCount = $this->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['present', 'izin'])
            ->count();
        
        $alphaCount = $totalDays - $attendanceCount;
        
        return max(0, $alphaCount);
    }

    public function canGetCertificate($startDate, $endDate)
    {
        $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        
        $attendanceCount = $this->attendances()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['present', 'izin', 'hadir'])
            ->count();
        
        $persentase = $totalDays > 0 ? ($attendanceCount / $totalDays) * 100 : 0;
        
        return $persentase >= 80;
    }

    public function getTodayAttendanceStatus()
    {
        $today = Carbon::today()->format('Y-m-d');
        
        $attendance = $this->attendances()
            ->where('date', $today)
            ->first();
        
        if (!$attendance) {
            return [
                'status' => 'pending',
                'message' => 'Belum diabsen oleh admin',
                'has_attended' => false
            ];
        }
        
        return [
            'status' => $attendance->status,
            'message' => $attendance->status === 'alpha' 
                ? 'Alpha (tidak hadir)' 
                : ($attendance->status === 'hadir' || $attendance->status === 'present' 
                    ? 'Hadir via QR Scan' 
                    : 'Izin/Sakit'),
            'has_attended' => in_array($attendance->status, ['hadir', 'present', 'izin']),
            'check_in' => $attendance->check_in,
            'check_out' => $attendance->check_out
        ];
    }
}