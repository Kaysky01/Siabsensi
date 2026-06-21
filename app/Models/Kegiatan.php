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
        'tanggal_pelaksanaan' => 'date',
        'jam_mulai' => 'datetime:H:i',
        'jam_selesai' => 'datetime:H:i',
        'wajib_hadir' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function absensi()
    {
        return $this->hasMany(KegiatanAbsensi::class);
    }

    public function getTotalPesertaAttribute()
    {
        return Mahasiswa::where('is_active', 1)->count();
    }

    public function getTotalHadirAttribute()
    {
        return $this->absensi()->count();
    }
}
