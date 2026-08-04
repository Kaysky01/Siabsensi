<?php

namespace App\Http\Controllers\Acara;

use App\Http\Controllers\Controller;
use App\Models\PkkmbSchedule;
use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PkkmbScheduleController extends Controller
{
    public function index()
    {
        $schedules = PkkmbSchedule::orderBy('hari_ke')->orderBy('tanggal')->get();
        $gracePeriod = SystemConfig::getGracePeriodMinutes();
        
        return view('acara.pkkmb-schedule', compact('schedules', 'gracePeriod'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hari_ke' => 'required|string|max:50',
            'tanggal' => 'required|date|unique:pkkmb_schedules,tanggal',
            'check_in_start' => 'required|date_format:H:i',
            'check_in_end' => 'required|date_format:H:i|after:check_in_start',
            'check_out_start' => 'required|date_format:H:i|after:check_in_end',
            'check_out_end' => 'required|date_format:H:i|after:check_out_start',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        DB::beginTransaction();
        try {
            PkkmbSchedule::create($validated);
            DB::commit();
            
            $this->invalidateScheduleCache();
            
            return redirect()->back()->with('success', 'Jadwal PKKMB Hari ke-' . $validated['hari_ke'] . ' berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan jadwal: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $schedule = PkkmbSchedule::findOrFail($id);
        
        $validated = $request->validate([
            'hari_ke' => 'required|string|max:50',
            'tanggal' => 'required|date|unique:pkkmb_schedules,tanggal,' . $id,
            'check_in_start' => 'required|date_format:H:i',
            'check_in_end' => 'required|date_format:H:i|after:check_in_start',
            'check_out_start' => 'required|date_format:H:i|after:check_in_end',
            'check_out_end' => 'required|date_format:H:i|after:check_out_start',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        DB::beginTransaction();
        try {
            $schedule->update($validated);
            DB::commit();
            
            $this->invalidateScheduleCache();
            
            return redirect()->back()->with('success', 'Jadwal PKKMB berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui jadwal: ' . $e->getMessage());
        }
    }

    public function toggleActive($id)
    {
        $schedule = PkkmbSchedule::findOrFail($id);
        $schedule->is_active = !$schedule->is_active;
        $schedule->save();

        $status = $schedule->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $this->invalidateScheduleCache();

        return redirect()->back()->with('success', "Jadwal PKKMB Hari ke-{$schedule->hari_ke} berhasil {$status}");
    }

    public function destroy($id)
    {
        $schedule = PkkmbSchedule::findOrFail($id);
        $hariKe  = $schedule->hari_ke;

        DB::beginTransaction();
        try {
            $sesiIds = DB::table('kegiatan_sesi')->where('pkkmb_schedule_id', $id)->pluck('id');

            if ($sesiIds->isNotEmpty()) {
                DB::table('attendance_sesi')->whereIn('sesi_id', $sesiIds)->delete();
            }

            DB::table('kegiatan_sesi')->where('pkkmb_schedule_id', $id)->delete();
            DB::table('attendance')->whereDate('date', $schedule->tanggal->format('Y-m-d'))->whereNull('kegiatan_id')->delete();

            $schedule->delete();
            DB::commit();

            $this->invalidateScheduleCache();

            return redirect()->back()->with('success', "Jadwal PKKMB Hari ke-{$hariKe} berhasil dihapus.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus jadwal: ' . $e->getMessage());
        }
    }

    public function updateGracePeriod(Request $request)
    {
        $validated = $request->validate([
            'grace_period_minutes' => 'required|integer|min:0|max:120',
        ]);

        try {
            SystemConfig::setGracePeriodMinutes($validated['grace_period_minutes']);
            $this->invalidateScheduleCache();
            return redirect()->back()->with('success', 'Grace period berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memperbarui grace period: ' . $e->getMessage());
        }
    }

    protected function invalidateScheduleCache()
    {
        try {
            $cacheFile = base_path('../python_backend/data/.schedule_cache_version');
            file_put_contents($cacheFile, now()->timestamp);
        } catch (\Exception $e) {
            Log::warning("Failed to invalidate schedule cache: " . $e->getMessage());
        }
    }
}
