<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KegiatanSesi extends Model
{
    use HasFactory;

    protected $table = 'kegiatan_sesi';

    protected $fillable = [
        'kegiatan_id',
        'pkkmb_schedule_id',
        'nama_sesi',
        'jam_mulai',
        'jam_selesai',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke Kegiatan (optional - for backward compatibility)
     */
    public function kegiatan()
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id', 'id');
    }

    /**
     * Relasi ke PKKMB Schedule
     */
    public function pkkmbSchedule()
    {
        return $this->belongsTo(PkkmbSchedule::class, 'pkkmb_schedule_id', 'id');
    }

    /**
     * Relasi ke Attendance (absensi yang tercatat untuk sesi ini)
     */
    public function attendances()
    {
        return $this->hasMany(AttendanceSesi::class, 'sesi_id', 'id');
    }

    /**
     * Get total hadir untuk sesi ini
     */
    public function getTotalHadirAttribute(): int
    {
        return $this->attendances()
            ->count();
    }

    /**
     * Scope to get only active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get sessions for specific kegiatan
     */
    public function scopeForKegiatan($query, $kegiatanId)
    {
        return $query->where('kegiatan_id', $kegiatanId);
    }

    /**
     * Scope to get sessions for specific PKKMB schedule
     */
    public function scopeForPkkmbSchedule($query, $pkkmbScheduleId)
    {
        return $query->where('pkkmb_schedule_id', $pkkmbScheduleId);
    }

    /**
     * Get display name (PKKMB Hari ke-X or Kegiatan name)
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->pkkmbSchedule) {
            return "PKKMB Hari ke-{$this->pkkmbSchedule->hari_ke} - {$this->nama_sesi}";
        }
        
        if ($this->kegiatan) {
            return "{$this->kegiatan->nama} - {$this->nama_sesi}";
        }
        
        return $this->nama_sesi;
    }

    /**
     * Get tanggal from PKKMB schedule or kegiatan
     */
    public function getTanggalAttribute()
    {
        if ($this->pkkmbSchedule) {
            return $this->pkkmbSchedule->tanggal;
        }
        
        if ($this->kegiatan) {
            return $this->kegiatan->tanggal_pelaksanaan;
        }
        
        return null;
    }
}
