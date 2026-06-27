<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kompi extends Model
{
    use HasFactory;

    protected $table = 'kompi';
    protected $fillable = ['nama', 'garda_id'];

    public function garda()
    {
        return $this->belongsTo(User::class, 'garda_id', 'username');
    }
}
