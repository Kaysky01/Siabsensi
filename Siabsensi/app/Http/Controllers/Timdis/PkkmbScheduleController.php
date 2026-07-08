<?php

namespace App\Http\Controllers\Timdis;

use App\Http\Controllers\Controller;
use App\Models\PkkmbSchedule;
use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PkkmbScheduleController extends Controller
{
    /**
     * Display PKKMB schedule management page
     */
    public function index()
    {
        $schedules = PkkmbSchedule::orderBy('hari_ke')->orderBy('tanggal')->get();
        $gracePeriod = SystemConfig::getGracePeriodMinutes();
        
        return view('timdis.pkkmb-schedule', compact('schedules', 'gracePeriod'));
    }

    /**
     * Store new PKKMB schedule
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'hari_ke' => 'required|integer|min:1',
            'tanggal' => 'required|date|unique:pkkmb_schedules,tanggal',
            'check_in_start' => 'required|date_format:H:i',
            'check_in_end' => 'required|date_format:H:i|after:check_in_start',
            'check_out_start' => 'required|date_format:H:i|after:check_in_end',
            'check_out_end' => 'required|date_format:H:i|after:check_out_start',
            'is_active' => 'nullable|boolean',
        ], [
            'hari_ke.required' => 'Hari ke- wajib diisi',
            'hari_ke.min' => 'Hari ke- minimal 1',
            'tanggal.required' => 'Tanggal wajib diisi',
            'tanggal.unique' => 'Tanggal sudah ada dalam jadwal',
            'check_in_start.required' => 'Waktu mulai check-in wajib diisi',
            'check_in_end.required' => 'Batas check-in wajib diisi',
            'check_in_end.after' => 'Batas check-in harus setelah waktu mulai',
            'check_out_start.required' => 'Waktu minimal check-out wajib diisi',
            'check_out_start.after' => 'Waktu check-out harus setelah batas check-in',
            'check_out_end.required' => 'Waktu maksimal check-out wajib diisi',
            'check_out_end.after' => 'Batas akhir check-out harus setelah waktu mulai check-out',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        DB::beginTransaction();
        try {
            PkkmbSchedule::create($validated);
            DB::commit();
            
            $this->invalidateScheduleCache();
            
            return redirect()->route('timdis.pkkmb-schedule.index')
                ->with('success', 'Jadwal PKKMB Hari ke-' . $validated['hari_ke'] . ' berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('timdis.pkkmb-schedule.index')
                ->with('error', 'Gagal menyimpan jadwal: ' . $e->getMessage());
        }
    }

    /**
     * Update existing PKKMB schedule
     */
    public function update(Request $request, $id)
    {
        $schedule = PkkmbSchedule::findOrFail($id);
        
        $validated = $request->validate([
            'hari_ke' => 'required|integer|min:1',
            'tanggal' => 'required|date|unique:pkkmb_schedules,tanggal,' . $id,
            'check_in_start' => 'required|date_format:H:i',
            'check_in_end' => 'required|date_format:H:i|after:check_in_start',
            'check_out_start' => 'required|date_format:H:i|after:check_in_end',
            'check_out_end' => 'required|date_format:H:i|after:check_out_start',
            'is_active' => 'nullable|boolean',
        ], [
            'hari_ke.required' => 'Hari ke- wajib diisi',
            'tanggal.required' => 'Tanggal wajib diisi',
            'tanggal.unique' => 'Tanggal sudah ada dalam jadwal',
            'check_in_start.required' => 'Waktu mulai check-in wajib diisi',
            'check_in_end.after' => 'Batas check-in harus setelah waktu mulai',
            'check_out_start.after' => 'Waktu check-out harus setelah batas check-in',
            'check_out_end.after' => 'Batas akhir check-out harus setelah waktu mulai check-out',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        DB::beginTransaction();
        try {
            $schedule->update($validated);
            DB::commit();
            
            $this->invalidateScheduleCache();
            
            return redirect()->route('timdis.pkkmb-schedule.index')
                ->with('success', 'Jadwal PKKMB berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('timdis.pkkmb-schedule.index')
                ->with('error', 'Gagal memperbarui jadwal: ' . $e->getMessage());
        }
    }

    /**
     * Toggle schedule active status
     */
    public function toggleActive($id)
    {
        $schedule = PkkmbSchedule::findOrFail($id);
        
        $schedule->is_active = !$schedule->is_active;
        $schedule->save();

        $status = $schedule->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $this->invalidateScheduleCache();

        return redirect()->route('timdis.pkkmb-schedule.index')
            ->with('success', "Jadwal PKKMB Hari ke-{$schedule->hari_ke} berhasil {$status}");
    }

    /**
     * Delete PKKMB schedule
     */
    public function destroy($id)
    {
        $schedule = PkkmbSchedule::findOrFail($id);
        $hariKe = $schedule->hari_ke;
        
        $schedule->delete();
        $this->invalidateScheduleCache();

        return redirect()->route('timdis.pkkmb-schedule.index')
            ->with('success', "Jadwal PKKMB Hari ke-{$hariKe} berhasil dihapus");
    }

    /**
     * Update grace period
     */
    public function updateGracePeriod(Request $request)
    {
        $validated = $request->validate([
            'grace_period_minutes' => 'required|integer|min:0|max:120',
        ], [
            'grace_period_minutes.required' => 'Grace period wajib diisi',
            'grace_period_minutes.integer' => 'Grace period harus berupa angka',
            'grace_period_minutes.min' => 'Grace period minimal 0 menit',
            'grace_period_minutes.max' => 'Grace period maksimal 120 menit',
        ]);

        try {
            SystemConfig::setGracePeriodMinutes($validated['grace_period_minutes']);
            $this->invalidateScheduleCache();
            return redirect()->route('timdis.pkkmb-schedule.index')->with('success', 'Grace period berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->route('timdis.pkkmb-schedule.index')->with('error', 'Gagal memperbarui grace period: ' . $e->getMessage());
        }
    }

    /**
     * Invalidate schedule cache for Python Backend
     */
    protected function invalidateScheduleCache()
    {
        try {
            $cacheFile = base_path('../python_backend/data/.schedule_cache_version');
            $timestamp = now()->timestamp;
            file_put_contents($cacheFile, $timestamp);
            Log::info("PKKMB Schedule cache invalidated at timestamp: {$timestamp}");
        } catch (\Exception $e) {
            Log::warning("Failed to invalidate schedule cache: " . $e->getMessage());
        }
    }
}



