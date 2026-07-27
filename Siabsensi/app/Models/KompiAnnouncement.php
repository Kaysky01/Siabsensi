<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KompiAnnouncement extends Model
{
    use HasFactory;

    protected $table = 'kompi_announcements';

    protected $fillable = [
        'kompi',
        'judul',
        'pesan',
        'link_wa',
        'is_active',
        'updated_by',
    ];
}
