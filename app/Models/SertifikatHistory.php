<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SertifikatHistory extends Model
{
    use HasFactory;

    protected $table = 'sertifikat_history';
    
    public const UPDATED_AT = null;

    protected $fillable = [
        'mahasiswa_id',
        'periode',
        'template',
        'total_hadir',
        'persentase',
    ];

    protected $casts = [
        'persentase' => 'decimal:2',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id');
    }
}