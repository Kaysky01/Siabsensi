<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\KegiatanAbsensi;
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
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'wajib_hadir' => 'boolean',
        ]);

        $kegiatan = Kegiatan::create($validated);

        return response()->json(['success' => true, 'data' => $kegiatan]);
    }

    public function update(Request $request, $id)
    {
        $kegiatan = Kegiatan::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'tanggal_pelaksanaan' => 'required|date',
            'jam_mulai' => 'required',
            'jam_selesai' => 'required',
            'wajib_hadir' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $kegiatan->update($validated);

        return response()->json(['success' => true, 'data' => $kegiatan]);
    }

    public function destroy($id)
    {
        Kegiatan::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    // ─── Admin: Monitoring Kehadiran per Kegiatan ───────────────────────────

    public function rekap(Request $request, $id)
    {
        $kegiatan = Kegiatan::with('absensi.mahasiswa')->findOrFail($id);

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
        if ($request->has('status') && $request->status === 'hadir') {
            $query->whereIn('id', $kegiatan->absensi->pluck('mahasiswa_id'));
        }
        if ($request->has('status') && $request->status === 'belum') {
            $query->whereNotIn('id', $kegiatan->absensi->pluck('mahasiswa_id'));
        }

        $semuaMahasiswa = $query->get();
        $hadirIds = $kegiatan->absensi->pluck('mahasiswa_id')->toArray();

        $rekap = $semuaMahasiswa->map(function ($mhs) use ($hadirIds, $kegiatan) {
            $isHadir = in_array($mhs->id, $hadirIds);

            return [
                'id' => $mhs->id,
                'name' => $mhs->name,
                'prodi' => $mhs->prodi,
                'kompi' => $mhs->kompi,
                'jurusan' => $mhs->jurusan,
                'status' => $isHadir ? 'hadir' : 'belum',
                'absen_at' => $isHadir ? optional($kegiatan->absensi->firstWhere('mahasiswa_id', $mhs->id))->absen_at : null,
            ];
        });

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
            ->where('jam_mulai', '<=', $waktu)
            ->where('jam_selesai', '>=', $waktu)
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
        if ($kegiatan->jam_mulai > $waktu || $kegiatan->jam_selesai < $waktu) {
            return response()->json(['success' => false, 'message' => 'Kegiatan belum dimulai atau sudah selesai'], 400);
        }

        // Cek duplikasi
        $existing = KegiatanAbsensi::where('kegiatan_id', $validated['kegiatan_id'])
            ->where('mahasiswa_id', $validated['mahasiswa_id'])
            ->exists();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Anda sudah absen pada kegiatan ini'], 400);
        }

        KegiatanAbsensi::create([
            'kegiatan_id' => $validated['kegiatan_id'],
            'mahasiswa_id' => $validated['mahasiswa_id'],
            'status' => 'hadir',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absensi kegiatan berhasil dicatat',
            'kegiatan' => $kegiatan->nama,
        ]);
    }

    public function riwayatMahasiswa($mahasiswaId)
    {
        $absensi = KegiatanAbsensi::where('mahasiswa_id', $mahasiswaId)
            ->with('kegiatan')
            ->orderBy('absen_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'kegiatan_id' => $item->kegiatan_id,
                    'nama_kegiatan' => $item->kegiatan->nama,
                    'tanggal' => $item->kegiatan->tanggal_pelaksanaan->format('Y-m-d'),
                    'jam_mulai' => $item->kegiatan->jam_mulai->format('H:i'),
                    'jam_selesai' => $item->kegiatan->jam_selesai->format('H:i'),
                    'absen_at' => $item->absen_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $absensi]);
    }
}
