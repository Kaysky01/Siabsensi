<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PkkmbSchedule extends Model
{
    use HasFactory;

    protected $table = 'pkkmb_schedules';

    protected $fillable = [
        'hari_ke',
        'tanggal',
        'check_in_start',
        'check_in_end',
        'check_out_start',
        'check_out_end',
        'is_active',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'check_in_start' => 'datetime:H:i',
        'check_in_end' => 'datetime:H:i',
        'check_out_start' => 'datetime:H:i',
        'check_out_end' => 'datetime:H:i',
        'is_active' => 'boolean',
    ];

    /**
     * Get schedule for specific date
     */
    public static function getScheduleForDate($date): ?self
    {
        if ($date instanceof Carbon) {
            $date = $date->format('Y-m-d');
        }
        
        return self::where('tanggal', $date)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get schedule for today
     */
    public static function getTodaySchedule(): ?self
    {
        return self::getScheduleForDate(Carbon::today());
    }

    /**
     * Check if schedule exists for a specific date
     */
    public static function hasScheduleForDate($date): bool
    {
        if ($date instanceof Carbon) {
            $date = $date->format('Y-m-d');
        }
        
        return self::where('tanggal', $date)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Validate time order: check_in_start < check_in_end < check_out_start < check_out_end
     */
    public function validateTimeOrder(): bool
    {
        $checkInStart = Carbon::parse($this->check_in_start);
        $checkInEnd = Carbon::parse($this->check_in_end);
        $checkOutStart = Carbon::parse($this->check_out_start);
        $checkOutEnd = Carbon::parse($this->check_out_end);

        return $checkInStart->lt($checkInEnd) &&
               $checkInEnd->lt($checkOutStart) &&
               $checkOutStart->lt($checkOutEnd);
    }

    /**
     * Get formatted date attribute
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->tanggal->format('d M Y');
    }

    /**
     * Scope to get only active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get schedule for specific date
     */
    public function scopeForDate($query, $date)
    {
        if ($date instanceof Carbon) {
            $date = $date->format('Y-m-d');
        }
        return $query->where('tanggal', $date);
    }

    /**
     * Scope to order by hari_ke
     */
    public function scopeOrderByHariKe($query)
    {
        return $query->orderBy('hari_ke');
    }

    /**
     * Relasi ke Sesi
     */
    public function sesi()
    {
        return $this->hasMany(KegiatanSesi::class, 'pkkmb_schedule_id', 'id');
    }

    /**
     * Get active sesi
     */
    public function sesiAktif()
    {
        return $this->hasMany(KegiatanSesi::class, 'pkkmb_schedule_id', 'id')
            ->where('is_active', true);
    }
}
