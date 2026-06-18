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
        'password_hash',
        'full_name',
        'email',
        'role',
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
        'password_hash',
    ];

    /**
     * Konversi (casting) tipe data saat data diambil atau disimpan.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed', // Laravel akan otomatis menggunakan Bcrypt untuk kolom ini
            'is_active' => 'boolean',
            'last_login' => 'datetime',
        ];
    }

    /**
     * OVERRIDE PENTING:
     * Beri tahu sistem Auth Laravel untuk menggunakan 'password_hash'
     * sebagai ganti 'password' bawaan.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id');
    }
}
