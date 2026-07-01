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
    ];

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
