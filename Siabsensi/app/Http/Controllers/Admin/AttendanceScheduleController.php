<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSchedule;
use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceScheduleController extends Controller
{
    /**
     * Display attendance schedule management page
     */
    public function index()
    {
        // Get all schedules, grouped by day
        $schedules = AttendanceSchedule::orderBy('day_of_week')->get();
        
        // Create array with all days (Monday-Sunday)
        $allDays = AttendanceSchedule::getAllDays();
        $schedulesArray = [];
        
        foreach ($allDays as $dayNumber => $dayName) {
            $schedule = $schedules->firstWhere('day_of_week', $dayNumber);
            $schedulesArray[] = [
                'day_of_week' => $dayNumber,
                'day_name' => $dayName,
                'schedule' => $schedule,
            ];
        }
        
        // Get grace period
        $gracePeriod = SystemConfig::getGracePeriodMinutes();
        
        return view('admin.attendance-schedule', compact('schedulesArray', 'gracePeriod'));
    }

    /**
     * Store or update schedule for a specific day
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'day_of_week' => 'required|integer|between:1,7',
            'check_in_start' => 'required|date_format:H:i',
            'check_in_end' => 'required|date_format:H:i|after:check_in_start',
            'check_out_start' => 'required|date_format:H:i|after:check_in_end',
            'check_out_end' => 'required|date_format:H:i|after:check_out_start',
            'is_active' => 'required|boolean',
        ], [
            'day_of_week.required' => 'Hari wajib dipilih',
            'day_of_week.between' => 'Hari tidak valid (1-7)',
            'check_in_start.required' => 'Waktu mulai check-in wajib diisi',
            'check_in_start.date_format' => 'Format waktu tidak valid (HH:MM)',
            'check_in_end.required' => 'Batas check-in wajib diisi',
            'check_in_end.after' => 'Batas check-in harus setelah waktu mulai',
            'check_out_start.required' => 'Waktu minimal check-out wajib diisi',
            'check_out_start.after' => 'Waktu check-out harus setelah batas check-in',
            'check_out_end.required' => 'Waktu maksimal check-out wajib diisi',
            'check_out_end.after' => 'Batas akhir check-out harus setelah waktu mulai check-out',
        ]);

        DB::beginTransaction();
        try {
            // Find existing schedule for this day
            $schedule = AttendanceSchedule::where('day_of_week', $validated['day_of_week'])->first();

            if ($schedule) {
                // Update existing schedule
                $schedule->update($validated);
                $message = 'Jadwal untuk ' . AttendanceSchedule::getDayName($validated['day_of_week']) . ' berhasil diperbarui';
            } else {
                // Create new schedule
                AttendanceSchedule::create($validated);
                $message = 'Jadwal untuk ' . AttendanceSchedule::getDayName($validated['day_of_week']) . ' berhasil ditambahkan';
            }

            DB::commit();
            
            // Invalidate schedule cache for Python Backend
            $this->invalidateScheduleCache();
            
            return redirect()->route('admin.schedule.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.schedule.index')->with('error', 'Gagal menyimpan jadwal: ' . $e->getMessage());
        }
    }

    /**
     * Bulk update all schedules at once
     */
    public function bulkUpdate(Request $request)
    {
        \Log::info('BulkUpdate called - START', [
            'user' => auth()->user()->username ?? 'unknown',
            'role' => auth()->user()->role ?? 'unknown',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'schedules_received' => $request->input('schedules'),
        ]);
        
        try {
            $request->validate([
                'schedules' => 'required|array',
                'schedules.*.day_of_week' => 'required|integer|between:1,7',
                'schedules.*.check_in_start' => 'nullable|date_format:H:i',
                'schedules.*.check_in_end' => 'nullable|date_format:H:i',
                'schedules.*.check_out_start' => 'nullable|date_format:H:i',
                'schedules.*.check_out_end' => 'nullable|date_format:H:i',
                'schedules.*.is_active' => 'required|in:0,1',
            ]);
            
            \Log::info('Validation passed in bulkUpdate');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed in bulkUpdate', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            return redirect()->route('admin.schedule.index')
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            \Log::error('Error in bulkUpdate validation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('admin.schedule.index')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }

        DB::beginTransaction();
        try {
            $updatedCount = 0;

            foreach ($request->schedules as $scheduleData) {
                $dayOfWeek = $scheduleData['day_of_week'];
                $isActive = (bool) intval($scheduleData['is_active']); // Convert '0' atau '1' to boolean

                // If active, validate all time fields are present
                if ($isActive) {
                    if (empty($scheduleData['check_in_start']) || 
                        empty($scheduleData['check_in_end']) ||
                        empty($scheduleData['check_out_start']) ||
                        empty($scheduleData['check_out_end'])) {
                        throw new \Exception('Semua waktu harus diisi untuk jadwal yang aktif (Hari: ' . AttendanceSchedule::getDayName($dayOfWeek) . ')');
                    }

                    // Validate time order
                    $checkInStart = Carbon::createFromFormat('H:i', $scheduleData['check_in_start']);
                    $checkInEnd = Carbon::createFromFormat('H:i', $scheduleData['check_in_end']);
                    $checkOutStart = Carbon::createFromFormat('H:i', $scheduleData['check_out_start']);
                    $checkOutEnd = Carbon::createFromFormat('H:i', $scheduleData['check_out_end']);

                    if (!($checkInStart->lt($checkInEnd) && 
                          $checkInEnd->lt($checkOutStart) && 
                          $checkOutStart->lt($checkOutEnd))) {
                        throw new \Exception('Urutan waktu tidak valid untuk ' . AttendanceSchedule::getDayName($dayOfWeek) . ': check_in_start < check_in_end < check_out_start < check_out_end');
                    }
                }

                // Update or create schedule
                AttendanceSchedule::updateOrCreate(
                    ['day_of_week' => $dayOfWeek],
                    [
                        'check_in_start' => $scheduleData['check_in_start'] ?? null,
                        'check_in_end' => $scheduleData['check_in_end'] ?? null,
                        'check_out_start' => $scheduleData['check_out_start'] ?? null,
                        'check_out_end' => $scheduleData['check_out_end'] ?? null,
                        'is_active' => $isActive,
                    ]
                );

                $updatedCount++;
            }

            DB::commit();
            
            // Invalidate schedule cache for Python Backend
            $this->invalidateScheduleCache();
            
            \Log::info('BulkUpdate SUCCESS', [
                'updated_count' => $updatedCount,
            ]);
            
            // Check if AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Berhasil memperbarui {$updatedCount} jadwal",
                    'updated_count' => $updatedCount
                ]);
            }
            
            return redirect()->route('admin.schedule.index')->with('success', "Berhasil memperbarui {$updatedCount} jadwal");
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('BulkUpdate FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check if AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui jadwal: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->route('admin.schedule.index')->with('error', 'Gagal memperbarui jadwal: ' . $e->getMessage());
        }
    }

    /**
     * Toggle schedule active status
     */
    public function toggleActive(Request $request, $dayOfWeek)
    {
        $schedule = AttendanceSchedule::where('day_of_week', $dayOfWeek)->firstOrFail();
        
        $schedule->is_active = !$schedule->is_active;
        $schedule->save();

        $status = $schedule->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $dayName = AttendanceSchedule::getDayName($dayOfWeek);

        // Invalidate schedule cache for Python Backend
        $this->invalidateScheduleCache();

        return redirect()->route('admin.schedule.index')->with('success', "Jadwal {$dayName} berhasil {$status}");
    }

    /**
     * Delete schedule for a specific day
     */
    public function destroy($dayOfWeek)
    {
        $schedule = AttendanceSchedule::where('day_of_week', $dayOfWeek)->firstOrFail();
        $dayName = AttendanceSchedule::getDayName($dayOfWeek);
        
        $schedule->delete();

        // Invalidate schedule cache for Python Backend
        $this->invalidateScheduleCache();

        return redirect()->route('admin.schedule.index')->with('success', "Jadwal {$dayName} berhasil dihapus");
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
            return redirect()->route('admin.schedule.index')->with('success', 'Grace period berhasil diperbarui');
        } catch (\Exception $e) {
            return redirect()->route('admin.schedule.index')->with('error', 'Gagal memperbarui grace period: ' . $e->getMessage());
        }
    }

    /**
     * Invalidate schedule cache for Python Backend
     * Writes a timestamp file that Python checks to invalidate its cache
     */
    protected function invalidateScheduleCache()
    {
        try {
            $cacheFile = base_path('../python_backend/data/.schedule_cache_version');
            $timestamp = now()->timestamp;
            file_put_contents($cacheFile, $timestamp);
            \Log::info("Schedule cache invalidated at timestamp: {$timestamp}");
        } catch (\Exception $e) {
            \Log::warning("Failed to invalidate schedule cache: " . $e->getMessage());
        }
    }
}
