<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kegiatan extends Model
{
    protected $table = 'kegiatan';

    protected $fillable = [
        'nama',
        'tanggal_pelaksanaan',
        'jam_mulai',
        'jam_selesai',
        'wajib_hadir',
        'is_active',
    ];

    protected $casts = [
        'tanggal_pelaksanaan' => 'date:Y-m-d',
        'wajib_hadir' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function absensi()
    {
        return $this->hasMany(KegiatanAbsensi::class);
    }
}
