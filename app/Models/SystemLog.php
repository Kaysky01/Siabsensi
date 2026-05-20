<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasFactory;

    protected $table = 'system_logs';
    
    // Custom Timestamp mapping
    public const CREATED_AT = 'timestamp';
    public const UPDATED_AT = null;

    protected $fillable = [
        'level',
        'message',
        'camera_id',
    ];

    public function camera()
    {
        return $this->belongsTo(CameraStream::class, 'camera_id', 'id');
    }
}
