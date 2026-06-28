<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Attendance;
use App\Models\Mahasiswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KegiatanController extends Controller
{
    // ─── Admin: CRUD Kegiatan ───────────────────────────────────────────────

    public function index()
    {
        $kegiatan = Kegiatan::orderBy('tanggal_pelaksanaan', 'desc')
            ->orderBy('jam_mulai', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $kegiatan]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'tanggal_pelaksanaan' => 'required|date',
            'wajib_hadir' => 'boolean',
        ]);
        
        $validated['jam_mulai'] = $request->input('jam_mulai', '00:00:00');
        $validated['jam_selesai'] = $request->input('jam_selesai', '23:59:59');

        $kegiatan = Kegiatan::create($validated);

        return back()->with('success', 'Kegiatan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $kegiatan = Kegiatan::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'tanggal_pelaksanaan' => 'required|date',
            'wajib_hadir' => 'boolean',
            'is_active' => 'boolean',
        ]);
        
        if ($request->has('jam_mulai')) $validated['jam_mulai'] = $request->jam_mulai;
        if ($request->has('jam_selesai')) $validated['jam_selesai'] = $request->jam_selesai;

        $kegiatan->update($validated);

        return back()->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $kegiatan = Kegiatan::findOrFail($id);
        
        // Hapus semua data absensi yang terkait kegiatan ini
        \App\Models\Attendance::where('kegiatan_id', $id)->delete();
        
        $kegiatan->delete();

        return back()->with('success', 'Kegiatan dan seluruh data absensinya berhasil dihapus.');
    }

    public function toggleActive($id)
    {
        $kegiatan = Kegiatan::findOrFail($id);
        $kegiatan->is_active = !$kegiatan->is_active;
        $kegiatan->save();

        return back()->with('success', 'Status kegiatan berhasil diubah.');
    }

    // ─── Admin: Monitoring Kehadiran per Kegiatan ───────────────────────────

    public function rekap(Request $request, $id)
    {
        $kegiatan = Kegiatan::findOrFail($id);

        $query = Mahasiswa::where('is_active', 1);

        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }
        if ($request->has('kompi') && $request->kompi) {
            $query->where('kompi', $request->kompi);
        }
        if ($request->has('prodi') && $request->prodi) {
            $query->where('prodi', $request->prodi);
        }
        
        // Ambil data absensi dari tabel attendance yang memiliki kegiatan_id ini
        $attendances = \App\Models\Attendance::where('kegiatan_id', $id)->get()->keyBy('mahasiswa_id');
        $hadirIds = $attendances->keys()->toArray();

        if ($request->has('status') && $request->status === 'hadir') {
            $query->whereIn('id', $hadirIds);
        }
        if ($request->has('status') && $request->status === 'belum') {
            $query->whereNotIn('id', $hadirIds);
        }

        $semuaMahasiswa = $query->get();

        $rekap = $semuaMahasiswa->map(function ($mhs) use ($attendances) {
            $att = $attendances->get($mhs->id);
            $isHadir = $att ? true : false;

            return [
                'id' => $mhs->id,
                'name' => $mhs->name,
                'prodi' => $mhs->prodi,
                'kompi' => $mhs->kompi,
                'jurusan' => $mhs->jurusan,
                'status' => $isHadir ? 'hadir' : 'belum',
                'check_in' => $att ? $att->check_in : null,
                'check_out' => $att ? $att->check_out : null,
            ];
        });

        // Calculate total hadir for filtered data
        $totalHadir = collect($hadirIds)->intersect($semuaMahasiswa->pluck('id'))->count();

        return response()->json([
            'success' => true,
            'data' => [
                'kegiatan' => $kegiatan,
                'total_hadir' => $totalHadir,
                'total_mahasiswa' => $semuaMahasiswa->count(),
                'rekap' => $rekap,
            ],
        ]);
    }

    // ─── Mahasiswa: Lihat kegiatan berlangsung & absen ──────────────────────

    public function aktif()
    {
        $sekarang = Carbon::now();
        $hariIni = $sekarang->format('Y-m-d');
        $waktu = $sekarang->format('H:i:s');

        $kegiatan = Kegiatan::where('is_active', true)
            ->where('tanggal_pelaksanaan', $hariIni)
            ->get();

        return response()->json(['success' => true, 'data' => $kegiatan]);
    }

    public function absen(Request $request)
    {
        $user = Auth::user();
        $mahasiswaId = $request->input('mahasiswa_id');

        if ($user->role !== 'admin' && (int) $user->mahasiswa_id !== (int) $mahasiswaId) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'kegiatan_id' => 'required|exists:kegiatan,id',
            'mahasiswa_id' => 'required|exists:mahasiswa,id',
        ]);

        $kegiatan = Kegiatan::findOrFail($validated['kegiatan_id']);

        // Cek apakah kegiatan sedang berlangsung
        $sekarang = Carbon::now();
        $hariIni = $sekarang->format('Y-m-d');
        $waktu = $sekarang->format('H:i:s');

        if ($kegiatan->tanggal_pelaksanaan->format('Y-m-d') !== $hariIni) {
            return response()->json(['success' => false, 'message' => 'Kegiatan tidak berlangsung hari ini'], 400);
        }

        // Cek duplikasi check_in
        $existing = Attendance::where('kegiatan_id', $validated['kegiatan_id'])
            ->where('mahasiswa_id', $validated['mahasiswa_id'])
            ->where('date', $hariIni)
            ->first();

        if ($existing) {
            if ($existing->check_out) {
                return response()->json(['success' => false, 'message' => 'Anda sudah absen masuk dan keluar pada kegiatan ini'], 400);
            }
            
            // Lakukan check-out
            $existing->update([
                'check_out' => Carbon::now()->toDateTimeString()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Absen Keluar (Check-out) berhasil dicatat',
                'kegiatan' => $kegiatan->nama,
            ]);
        }

        Attendance::create([
            'kegiatan_id' => $validated['kegiatan_id'],
            'mahasiswa_id' => $validated['mahasiswa_id'],
            'date' => $hariIni,
            'status' => 'present',
            'check_in' => Carbon::now()->toDateTimeString()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absensi kegiatan berhasil dicatat',
            'kegiatan' => $kegiatan->nama,
        ]);
    }

    public function riwayatMahasiswa($mahasiswaId)
    {
        $absensi = Attendance::where('mahasiswa_id', $mahasiswaId)
            ->whereNotNull('kegiatan_id')
            ->with('kegiatan')
            ->orderBy('check_in', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'kegiatan_id' => $item->kegiatan_id,
                    'nama_kegiatan' => optional($item->kegiatan)->nama ?? 'Kegiatan Dihapus',
                    'tanggal' => $item->date,
                    'check_in' => $item->check_in,
                    'check_out' => $item->check_out,
                ];
            });

        return response()->json(['success' => true, 'data' => $absensi]);
    }
}
