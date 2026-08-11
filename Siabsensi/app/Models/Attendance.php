<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    public const UPDATED_AT = null;

    protected $fillable = [
        'mahasiswa_id',
        'check_in',
        'check_out',
        'date',
        'status',
        'camera_id',
        'snapshot_path',
        'yolo_confidence',
        'notes',
        'check_in_time',
        'check_out_time',
        'kegiatan_id',
        'sesi_id',
        'absen_by',
        'absen_at',
        'is_late',
        'late_duration',
        'late_overridden',
        'overridden_by',
        'override_reason',
        'override_timestamp',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'date' => 'date',
        'yolo_confidence' => 'double',
        'is_late' => 'boolean',
        'late_overridden' => 'boolean',
        'override_timestamp' => 'datetime',
        'absen_at' => 'datetime',
    ];

    public function scopeDaily($query)
    {
        return $query->whereNull('kegiatan_id')
            ->whereNull('sesi_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id');
    }

    public function camera()
    {
        return $this->belongsTo(CameraStream::class, 'camera_id', 'id');
    }

    public function kegiatan()
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id', 'id');
    }

    public function sesi()
    {
        return $this->belongsTo(KegiatanSesi::class, 'sesi_id', 'id');
    }

    public function absenBy()
    {
        return $this->belongsTo(User::class, 'absen_by', 'username');
    }

    public function sessionAttendances()
    {
        return $this->hasMany(AttendanceSesi::class, 'attendance_id', 'id');
    }

    /**
     * Helper to check if attendance is complete (both check_in and check_out present)
     */
    public function isLengkap(): bool
    {
        return !empty($this->check_in) && !empty($this->check_out);
    }

    /**
     * Helper to check if student is still in location (check_in present, check_out null)
     */
    public function isMasihDiLokasi(): bool
    {
        return !empty($this->check_in) && empty($this->check_out);
    }

    /**
     * Get exact status badge styling and text based on standard color chart
     */
    public function getStatusBadgeData(): array
    {
        $statusLower = strtolower($this->status ?? 'alpha');

        if ($statusLower === 'izin') {
            return [
                'label' => 'Izin',
                'bg' => '#dbeafe',
                'color' => '#1d4ed8',
                'border' => '#bfdbfe',
                'dot' => '#3b82f6',
            ];
        }

        if ($statusLower === 'sakit') {
            return [
                'label' => 'Sakit',
                'bg' => '#fef9c3',
                'color' => '#a16207',
                'border' => '#fef08a',
                'dot' => '#eab308',
            ];
        }

        if ($statusLower === 'alpha') {
            return [
                'label' => 'Alpha',
                'bg' => '#fee2e2',
                'color' => '#b91c1c',
                'border' => '#fecaca',
                'dot' => '#ef4444',
            ];
        }

        // If status is present / hadir / manual
        if ($this->isLengkap()) {
            return [
                'label' => 'Lengkap / Hadir',
                'bg' => '#dcfce7',
                'color' => '#15803d',
                'border' => '#bbf7d0',
                'dot' => '#10b981',
            ];
        }

        if ($this->isMasihDiLokasi()) {
            return [
                'label' => 'Masuk (belum keluar)',
                'bg' => '#1e293b',
                'color' => '#ffffff',
                'border' => '#0f172a',
                'dot' => '#1f2937',
            ];
        }

        return [
            'label' => 'Belum ada',
            'bg' => '#f1f5f9',
            'color' => '#64748b',
            'border' => '#e2e8f0',
            'dot' => '#94a3b8',
        ];
    }

    /**
     * Check if this attendance is late and not overridden
     */
    public function isEffectivelyLate(): bool
    {
        return $this->is_late && !$this->late_overridden;
    }

    /**
     * Get late status display text
     */
    public function getLateStatusText(): string
    {
        if (!$this->is_late) {
            return 'Tepat Waktu';
        }

        if ($this->late_overridden) {
            return 'Telat (Di-override)';
        }

        return "Telat {$this->late_duration} menit";
    }

    /**
     * Get late badge HTML
     */
    public function getLateBadgeHtml(): string
    {
        if (!$this->isEffectivelyLate()) {
            return '';
        }

        return "<span class='badge bg-danger'>TELAT {$this->late_duration} menit</span>";
    }

    /**
     * Scope to get only late attendances
     */
    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    /**
     * Scope to get only overridden attendances
     */
    public function scopeOverridden($query)
    {
        return $query->where('late_overridden', true);
    }

    /**
     * Scope to get effectively late (late and not overridden)
     */
    public function scopeEffectivelyLate($query)
    {
        return $query->where('is_late', true)
                     ->where('late_overridden', false);
    }
}
