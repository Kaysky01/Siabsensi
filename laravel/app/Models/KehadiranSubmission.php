<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KehadiranSubmission extends Model
{
    use HasFactory;

    protected $table = 'kehadiran_submissions';

    protected $fillable = [
        'mahasiswa_id',
        'date',
        'check_in_time',
        'check_out_time',
        'keterangan',
        'bukti_path',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id');
    }
}
