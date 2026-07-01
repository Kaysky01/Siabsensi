<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    use HasFactory;

    protected $table = 'system_config';

    protected $fillable = [
        'config_key',
        'config_value',
        'description',
    ];

    /**
     * Get config value by key
     */
    public static function get(string $key, $default = null)
    {
        // Cache config for 1 hour
        return Cache::remember("system_config_{$key}", 3600, function () use ($key, $default) {
            $config = self::where('config_key', $key)->first();
            return $config ? $config->config_value : $default;
        });
    }

    /**
     * Set config value
     */
    public static function set(string $key, $value, ?string $description = null): self
    {
        $config = self::updateOrCreate(
            ['config_key' => $key],
            [
                'config_value' => $value,
                'description' => $description ?? "Config for {$key}",
            ]
        );

        // Invalidate cache
        Cache::forget("system_config_{$key}");

        return $config;
    }

    /**
     * Get grace period in minutes
     */
    public static function getGracePeriodMinutes(): int
    {
        return (int) self::get('attendance_grace_period_minutes', 40);
    }

    /**
     * Set grace period in minutes
     */
    public static function setGracePeriodMinutes(int $minutes): void
    {
        if ($minutes < 0 || $minutes > 120) {
            throw new \InvalidArgumentException('Grace period must be between 0 and 120 minutes');
        }

        self::set(
            'attendance_grace_period_minutes',
            (string) $minutes,
            'Waktu toleransi (dalam menit) setelah batas check-in dimana mahasiswa masih bisa absen tetapi dianggap telat'
        );
    }

    /**
     * Clear all config cache
     */
    public static function clearCache(): void
    {
        $configs = self::all();
        foreach ($configs as $config) {
            Cache::forget("system_config_{$config->config_key}");
        }
    }
}
