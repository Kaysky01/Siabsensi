<?php

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Attendance;
use App\Models\Kegiatan;

// Fix attendance records with missing kegiatan_id
$kegiatanList = Kegiatan::orderBy('tanggal_pelaksanaan', 'asc')->get();
$fixedCount = 0;

foreach (Attendance::whereNull('kegiatan_id')->get() as $att) {
    if (!$att->date) continue;
    
    // Find closest kegiatan
    $closest = null;
    $minDiff = PHP_INT_MAX;
    $attDate = \Carbon\Carbon::parse($att->date);
    
    foreach ($kegiatanList as $k) {
        $kegDate = \Carbon\Carbon::parse($k->tanggal_pelaksanaan);
        $diff = abs($attDate->diffInDays($kegDate));
        if ($diff < $minDiff) {
            $minDiff = $diff;
            $closest = $k;
        }
    }
    
    if ($closest && $minDiff <= 3) {
        $att->kegiatan_id = $closest->id;
        $att->save();
        $fixedCount++;
    }
}

echo "Fixed $fixedCount attendance records.\n";
