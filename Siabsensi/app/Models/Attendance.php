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
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'date' => 'date',
        'yolo_confidence' => 'double',
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
}
