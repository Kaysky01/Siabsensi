<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('role', 'mahasiswa')->has('mahasiswa')->first();
$m = $user->mahasiswa;
$pw = \Carbon\Carbon::parse($m->tanggal_lahir)->format('dmY');
echo "Username: " . $user->username . "\n";
echo "Password: " . $pw . "\n";
