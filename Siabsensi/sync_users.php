<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

$mahasiswas = Mahasiswa::doesntHave('user')->get();
$count = 0;

foreach ($mahasiswas as $m) {
    $password = '00000000';
    if ($m->tanggal_lahir) {
        $password = Carbon::parse($m->tanggal_lahir)->format('dmY');
    }
    
    User::create([
        'username' => $m->id,
        'password' => Hash::make($password),
        'full_name' => $m->name,
        'email' => $m->email,
        'role' => 'mahasiswa',
        'mahasiswa_id' => $m->id,
        'is_active' => 1
    ]);
    $count++;
}

echo "Created $count missing users.\n";

$orphanedUsers = User::where('role', 'mahasiswa')->doesntHave('mahasiswa')->count();
echo "Found $orphanedUsers users without mahasiswa.\n";
