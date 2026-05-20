<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mahasiswa extends Model
{
    use HasFactory;

    protected $table = 'mahasiswa';
    
    // Konfigurasi Primary Key String
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    
    // Hanya ada created_at di tabel
    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'name',
        'kelompok',
        'jurusan',
        'email',
        'no_telp_mahasiswa',
        'no_telp_ortu',
        'qr_code_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relasi
    public function user()
    {
        return $this->hasOne(User::class, 'mahasiswa_id', 'id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'mahasiswa_id', 'id');
    }

    public function izinSubmissions()
    {
        return $this->hasMany(IzinSubmission::class, 'mahasiswa_id', 'id');
    }

    public function kehadiranSubmissions()
    {
        return $this->hasMany(KehadiranSubmission::class, 'mahasiswa_id', 'id');
    }
}