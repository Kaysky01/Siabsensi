<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KegiatanAbsensi extends Model
{
    protected $table = 'kegiatan_absensi';

    public $timestamps = false;

    protected $fillable = [
        'kegiatan_id',
        'mahasiswa_id',
        'status',
        'absen_at',
    ];

    protected $casts = [
        'absen_at' => 'datetime',
    ];

    public function kegiatan()
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id');
    }
}
