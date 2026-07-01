<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceSchedule extends Model
{
    use HasFactory;

    protected $table = 'attendance_schedules';

    protected $fillable = [
        'day_of_week',
        'check_in_start',
        'check_in_end',
        'check_out_start',
        'check_out_end',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'check_in_start' => 'datetime:H:i',
        'check_in_end' => 'datetime:H:i',
        'check_out_start' => 'datetime:H:i',
        'check_out_end' => 'datetime:H:i',
    ];

    /**
     * Day of week constants (ISO-8601)
     * 1 = Monday, 2 = Tuesday, ..., 7 = Sunday
     */
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;
    public const SUNDAY = 7;

    /**
     * Get day name in Indonesian
     */
    public static function getDayName(int $dayOfWeek): string
    {
        $days = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];

        return $days[$dayOfWeek] ?? 'Unknown';
    }

    /**
     * Get all day names
     */
    public static function getAllDays(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
    }

    /**
     * Get schedule for specific day
     */
    public static function getScheduleForDay(int $dayOfWeek): ?self
    {
        return self::where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get schedule for today
     */
    public static function getTodaySchedule(): ?self
    {
        $today = Carbon::now()->dayOfWeekIso; // 1 = Monday, 7 = Sunday
        return self::getScheduleForDay($today);
    }

    /**
     * Check if schedule exists for a specific day
     */
    public static function hasScheduleForDay(int $dayOfWeek): bool
    {
        return self::where('day_of_week', $dayOfWeek)
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
     * Get day name attribute
     */
    public function getDayNameAttribute(): string
    {
        return self::getDayName($this->day_of_week);
    }

    /**
     * Scope to get only active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get schedule for specific day
     */
    public function scopeForDay($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
}
