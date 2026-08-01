<?php

namespace App\Http\Controllers\Acara;

use App\Http\Controllers\Controller;
use App\Models\PkkmbSchedule;
use App\Models\KegiatanSesi;
use App\Models\SystemConfig;
use Illuminate\Http\Request;

class AcaraController extends Controller
{
    public function dashboard()
    {
        $totalSchedules = PkkmbSchedule::count();
        $activeSchedules = PkkmbSchedule::where('is_active', true)->count();
        $totalSesi = KegiatanSesi::count();
        $activeSesi = KegiatanSesi::where('is_active', true)->count();
        $gracePeriod = SystemConfig::getGracePeriodMinutes();

        $upcomingSchedules = PkkmbSchedule::with('sesi')
            ->orderBy('hari_ke')
            ->get();

        return view('acara.dashboard', compact(
            'totalSchedules',
            'activeSchedules',
            'totalSesi',
            'activeSesi',
            'gracePeriod',
            'upcomingSchedules'
        ));
    }
}
