<?php

namespace App\Http\Controllers\Acara;

use App\Http\Controllers\Controller;
use App\Models\PkkmbSchedule;
use App\Models\KegiatanSesi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PkkmbSesiController extends Controller
{
    public function index()
    {
        $schedules = PkkmbSchedule::with(['sesi' => function($query) {
            $query->orderBy('jam_mulai');
        }])
        ->orderBy('hari_ke')
        ->orderBy('tanggal')
        ->get();
        
        return view('acara.pkkmb-sesi', compact('schedules'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pkkmb_schedule_id' => 'required|exists:pkkmb_schedules,id',
            'nama_sesi' => 'required|string|max:255',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;
        $validated['kegiatan_id'] = null;

        DB::beginTransaction();
        try {
            KegiatanSesi::create($validated);
            DB::commit();
            
            return redirect()->back()->with('success', 'Sesi berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menambahkan sesi: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $sesiId)
    {
        $sesi = KegiatanSesi::findOrFail($sesiId);
        
        $validated = $request->validate([
            'nama_sesi' => 'required|string|max:255',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        DB::beginTransaction();
        try {
            $sesi->update($validated);
            DB::commit();
            
            return redirect()->back()->with('success', 'Sesi berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui sesi: ' . $e->getMessage());
        }
    }

    public function toggleActive($sesiId)
    {
        $sesi = KegiatanSesi::findOrFail($sesiId);
        $sesi->is_active = !$sesi->is_active;
        $sesi->save();

        $status = $sesi->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with('success', "Sesi berhasil {$status}");
    }

    public function destroy($sesiId)
    {
        $sesi = KegiatanSesi::findOrFail($sesiId);
        DB::table('attendance_sesi')->where('sesi_id', $sesiId)->delete();
        $sesi->delete();

        return redirect()->back()->with('success', 'Sesi dan seluruh data absensinya berhasil dihapus');
    }
}
