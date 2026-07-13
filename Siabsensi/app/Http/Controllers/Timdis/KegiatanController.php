<?php

namespace App\Http\Controllers\Timdis;

use App\Http\Controllers\Controller;
use App\Models\PkkmbSchedule;
use App\Models\KegiatanSesi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class KegiatanController extends Controller
{
    /**
     * Display main page with all PKKMB schedules and their sessions
     */
    public function index()
    {
        // Get all PKKMB schedules with their sessions
        $schedules = PkkmbSchedule::with(['sesi' => function($query) {
            $query->orderBy('jam_mulai');
        }])
        ->orderBy('hari_ke')
        ->orderBy('tanggal')
        ->get();
        
        return view('timdis.pkkmb-sesi', compact('schedules'));
    }

    /**
     * Store new sesi for specific PKKMB schedule
     */
    public function store(Request $request)
    {
        // Log request for debugging
        Log::info('PkkmbSesiController@store - Request data:', $request->all());
        
        $validated = $request->validate([
            'pkkmb_schedule_id' => 'required|exists:pkkmb_schedules,id',
            'nama_sesi' => 'required|string|max:255',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active' => 'nullable|boolean',
        ], [
            'pkkmb_schedule_id.required' => 'PKKMB Hari ke- wajib dipilih',
            'pkkmb_schedule_id.exists' => 'PKKMB Hari ke- tidak valid',
            'nama_sesi.required' => 'Nama sesi wajib diisi',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai',
        ]);

        Log::info('PkkmbSesiController@store - Validated data:', $validated);

        $validated['is_active'] = $request->has('is_active') ? true : false;
        $validated['kegiatan_id'] = null; // Not related to old kegiatan

        DB::beginTransaction();
        try {
            $sesi = KegiatanSesi::create($validated);
            Log::info('PkkmbSesiController@store - Sesi created:', ['id' => $sesi->id]);
            DB::commit();
            
            return redirect()->route('timdis.kegiatan')
                ->with('success', 'Sesi berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PkkmbSesiController@store - Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('timdis.kegiatan')
                ->with('error', 'Gagal menambahkan sesi: ' . $e->getMessage());
        }
    }

    /**
     * Update existing sesi
     */
    public function update(Request $request, $sesiId)
    {
        $sesi = KegiatanSesi::findOrFail($sesiId);
        
        $validated = $request->validate([
            'nama_sesi' => 'required|string|max:255',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active' => 'nullable|boolean',
        ], [
            'nama_sesi.required' => 'Nama sesi wajib diisi',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        DB::beginTransaction();
        try {
            $sesi->update($validated);
            DB::commit();
            
            return redirect()->route('timdis.kegiatan')
                ->with('success', 'Sesi berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('timdis.kegiatan')
                ->with('error', 'Gagal memperbarui sesi: ' . $e->getMessage());
        }
    }

    /**
     * Toggle sesi active status
     */
    public function toggleActive($sesiId)
    {
        $sesi = KegiatanSesi::findOrFail($sesiId);
        
        $sesi->is_active = !$sesi->is_active;
        $sesi->save();

        $status = $sesi->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('timdis.kegiatan')
            ->with('success', "Sesi berhasil {$status}");
    }

    /**
     * Delete sesi
     */
    public function destroy($sesiId)
    {
        $sesi = KegiatanSesi::findOrFail($sesiId);
        
        // Delete all attendance records for this sesi
        DB::table('attendance_sesi')->where('sesi_id', $sesiId)->delete();
        
        $sesi->delete();

        return redirect()->route('timdis.kegiatan')
            ->with('success', 'Sesi dan seluruh data absensinya berhasil dihapus');
    }
}


