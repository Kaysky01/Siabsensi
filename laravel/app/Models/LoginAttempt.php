<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    use HasFactory;

    protected $table = 'login_attempts';

    public const CREATED_AT = 'attempted_at';

    public const UPDATED_AT = null;

    protected $fillable = [
        'username',
        'ip_address',
        'success',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];
}
