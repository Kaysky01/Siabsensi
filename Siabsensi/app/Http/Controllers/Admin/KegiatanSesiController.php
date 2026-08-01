<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\KegiatanSesi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KegiatanSesiController extends Controller
{
    /**
     * Display sesi for specific kegiatan
     */
    public function index($kegiatanId)
    {
        $kegiatan = Kegiatan::with(['sesi' => function($query) {
            $query->orderBy('jam_mulai');
        }])->findOrFail($kegiatanId);
        
        return view('admin.kegiatan-sesi', compact('kegiatan'));
    }

    /**
     * Store new sesi
     */
    public function store(Request $request, $kegiatanId)
    {
        $kegiatan = Kegiatan::findOrFail($kegiatanId);
        
        $validated = $request->validate([
            'nama_sesi' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('kegiatan_sesi')->where(function ($query) use ($kegiatanId) {
                    return $query->where('kegiatan_id', $kegiatanId);
                })
            ],
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active' => 'nullable|boolean',
        ], [
            'nama_sesi.required' => 'Nama sesi wajib diisi',
            'nama_sesi.unique' => 'Nama sesi tersebut sudah ada pada kegiatan ini. Silakan gunakan nama lain.',
            'jam_mulai.date_format' => 'Format jam mulai tidak valid (gunakan format HH:MM)',
            'jam_selesai.date_format' => 'Format jam selesai tidak valid (gunakan format HH:MM)',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai',
        ]);

        $validated['kegiatan_id'] = $kegiatanId;
        $validated['is_active'] = $request->has('is_active') ? true : false;

        DB::beginTransaction();
        try {
            KegiatanSesi::create($validated);
            DB::commit();
            
            return redirect()->route('admin.kegiatan-sesi.index', $kegiatanId)
                ->with('success', 'Sesi berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.kegiatan-sesi.index', $kegiatanId)
                ->with('error', 'Gagal menambahkan sesi: ' . $e->getMessage());
        }
    }

    /**
     * Update existing sesi
     */
    public function update(Request $request, $kegiatanId, $sesiId)
    {
        $sesi = KegiatanSesi::where('kegiatan_id', $kegiatanId)->findOrFail($sesiId);
        
        $validated = $request->validate([
            'nama_sesi' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('kegiatan_sesi')->where(function ($query) use ($kegiatanId) {
                    return $query->where('kegiatan_id', $kegiatanId);
                })->ignore($sesiId)
            ],
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active' => 'nullable|boolean',
        ], [
            'nama_sesi.required' => 'Nama sesi wajib diisi',
            'nama_sesi.unique' => 'Nama sesi tersebut sudah ada pada kegiatan ini. Silakan gunakan nama lain.',
            'jam_mulai.date_format' => 'Format jam mulai tidak valid (gunakan format HH:MM)',
            'jam_selesai.date_format' => 'Format jam selesai tidak valid (gunakan format HH:MM)',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai',
        ]);

        $validated['is_active'] = $request->has('is_active') ? true : false;

        DB::beginTransaction();
        try {
            $sesi->update($validated);
            DB::commit();
            
            return redirect()->route('admin.kegiatan-sesi.index', $kegiatanId)
                ->with('success', 'Sesi berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.kegiatan-sesi.index', $kegiatanId)
                ->with('error', 'Gagal memperbarui sesi: ' . $e->getMessage());
        }
    }

    /**
     * Toggle sesi active status
     */
    public function toggleActive($kegiatanId, $sesiId)
    {
        $sesi = KegiatanSesi::where('kegiatan_id', $kegiatanId)->findOrFail($sesiId);
        
        $sesi->is_active = !$sesi->is_active;
        $sesi->save();

        $status = $sesi->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.kegiatan-sesi.index', $kegiatanId)
            ->with('success', "Sesi berhasil {$status}");
    }

    /**
     * Delete sesi
     */
    public function destroy($kegiatanId, $sesiId)
    {
        $sesi = KegiatanSesi::where('kegiatan_id', $kegiatanId)->findOrFail($sesiId);
        
        // Delete all attendance records for this sesi
        DB::table('attendance_sesi')->where('sesi_id', $sesiId)->delete();
        
        $sesi->delete();

        return redirect()->route('admin.kegiatan-sesi.index', $kegiatanId)
            ->with('success', 'Sesi dan seluruh data absensinya berhasil dihapus');
    }
}
