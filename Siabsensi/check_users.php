<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = App\Models\User::count();
$mhsCount = App\Models\User::where('role', 'mahasiswa')->count();
echo "Total users: " . $count . PHP_EOL;
echo "Mahasiswa users: " . $mhsCount . PHP_EOL;

$u = App\Models\User::where('role', 'mahasiswa')->first();
if ($u) {
    echo "Sample username: " . $u->username . PHP_EOL;
    $m = clone $u->mahasiswa;
    if ($m) {
        echo "Tgl lahir: " . $m->tanggal_lahir . PHP_EOL;
        echo "Password seharusnya (dmY): " . Carbon\Carbon::parse($m->tanggal_lahir)->format('dmY') . PHP_EOL;
    }
}
