<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSesi extends Model
{
    use HasFactory;

    protected $table = 'attendance_sesi';

    public const UPDATED_AT = null;

    protected $fillable = [
        'attendance_id',
        'sesi_id',
        'mahasiswa_id',
        'status',
        'absen_by',
        'absen_at',
    ];

    protected $casts = [
        'absen_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id', 'id');
    }

    public function sesi()
    {
        return $this->belongsTo(KegiatanSesi::class, 'sesi_id', 'id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id');
    }

    public function absenBy()
    {
        return $this->belongsTo(User::class, 'absen_by', 'username');
    }
}
