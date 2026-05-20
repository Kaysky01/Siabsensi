<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CameraStream extends Model
{
    use HasFactory;

    protected $table = 'camera_streams';
    
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    
    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'name',
        'rtsp_url',
        'location',
        'is_active',
        'last_seen',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen' => 'datetime',
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'camera_id', 'id');
    }

    public function systemLogs()
    {
        return $this->hasMany(SystemLog::class, 'camera_id', 'id');
    }
}
