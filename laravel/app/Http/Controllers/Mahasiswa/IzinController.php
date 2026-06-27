<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\IzinSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IzinController extends Controller
{
    // Mengambil riwayat izin mahasiswa
    public function history($id)
    {
        $submissions = IzinSubmission::where('mahasiswa_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['submissions' => $submissions],
        ]);
    }

    // Submit pengajuan izin baru
    public function submit(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'mahasiswa_id' => 'required',
            'type' => 'required',
            'date' => 'required|date',
            'keterangan' => 'required|min:10',
            'bukti' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240|mimetypes:image/jpeg,image/png,application/pdf',
        ]);

        // Simpan file bukti ke folder storage/app/public/bukti
        $path = $request->file('bukti')->store('izin/bukti', 'public');

        // Simpan ke database
        IzinSubmission::create([
            'mahasiswa_id' => $request->mahasiswa_id,
            'submission_type' => $request->type,
            'date' => $request->date,
            'keterangan' => $request->keterangan,
            'bukti_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Pengajuan berhasil dikirim']);
    }

    public function getBukti($filename)
    {
        // Sesuaikan dengan nama folder
        $path = 'izin/bukti/'.$filename;

        // Cek apakah file benar-benar ada di storage/app/public/izin/bukti
        if (! Storage::disk('public')->exists($path)) {
            abort(404, 'File bukti tidak ditemukan.');
        }

        // Ambil path fisik lengkapnya di dalam server
        $fullPath = storage_path('app/public/'.$path);

        // Mengirimkan file tersebut langsung ke browser
        return response()->file($fullPath);
    }
}
