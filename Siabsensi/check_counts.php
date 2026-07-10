<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Mahasiswa;
use App\Models\User;

$m_count = Mahasiswa::count();
$u_count = User::where('role', 'mahasiswa')->count();
$total_u = User::count();

echo "Mahasiswa count: $m_count\n";
echo "User (mahasiswa) count: $u_count\n";
echo "User (total) count: $total_u\n";
