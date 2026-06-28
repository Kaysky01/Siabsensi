<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Kolom-kolom yang diizinkan untuk diisi secara massal (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'full_name',
        'email',
        'role',
        'assigned_kompi',
        'mahasiswa_id',
        'is_active',
        'last_login',
    ];

    /**
     * Kolom-kolom yang disembunyikan saat model diubah menjadi Array atau JSON.
     * (Berguna agar password tidak bocor saat mengambil data dari API).
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Konversi (casting) tipe data saat data diambil atau disimpan.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed', // Laravel akan otomatis menggunakan Bcrypt untuk kolom ini
            'is_active' => 'boolean',
            'last_login' => 'datetime',
        ];
    }



    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id');
    }
}
