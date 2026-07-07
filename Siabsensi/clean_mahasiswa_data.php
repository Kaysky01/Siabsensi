<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;

echo "Memulai pembersihan data jurusan dan prodi di tabel mahasiswa...\n";

$mahasiswaList = Mahasiswa::all();
$updatedCount = 0;

foreach ($mahasiswaList as $mahasiswa) {
    $originalJurusan = $mahasiswa->jurusan;
    $originalProdi = $mahasiswa->prodi;

    $cleanJurusan = $originalJurusan ? trim($originalJurusan) : null;
    $cleanProdi = $originalProdi ? trim($originalProdi) : null;

    if ($originalJurusan !== $cleanJurusan || $originalProdi !== $cleanProdi) {
        $mahasiswa->jurusan = $cleanJurusan;
        $mahasiswa->prodi = $cleanProdi;
        $mahasiswa->save();
        $updatedCount++;
        echo "  - Mahasiswa ID: {$mahasiswa->id}, Jurusan lama: '{$originalJurusan}' -> '{$cleanJurusan}', Prodi lama: '{$originalProdi}' -> '{$cleanProdi}'\n";
    }
}

echo "\nPembersihan selesai. Total {$updatedCount} mahasiswa diperbarui.\n";

// Clear cache to ensure new data is loaded for filters
\Illuminate\Support\Facades\Artisan::call('cache:clear');
echo "Cache aplikasi dibersihkan.\n";
