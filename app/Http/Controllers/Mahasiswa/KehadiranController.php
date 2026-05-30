<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KehadiranSubmission; // Import Model yang baru saja kamu kirim
use Illuminate\Support\Facades\Storage;

class KehadiranController extends Controller
{
    // Mengambil riwayat kehadiran mahasiswa
    public function history($id)
    {
        $submissions = KehadiranSubmission::where('mahasiswa_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['submissions' => $submissions]
        ]);
    }

    // Submit pengajuan kehadiran manual
    public function submit(Request $request)
    {
        // Validasi input dari JavaScript
        $request->validate([
            'mahasiswa_id'   => 'required',
            'date'           => 'required|date',
            'check_in_time'  => 'required',
            'check_out_time' => 'required',
            'keterangan'     => 'required|min:10',
            'bukti'          => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        // Simpan file bukti ke folder storage/app/public/kehadiran/bukti
        $path = $request->file('bukti')->store('kehadiran/bukti', 'public');

        // Simpan ke database sesuai dengan kolom $fillable di Model KehadiranSubmission
        KehadiranSubmission::create([
            'mahasiswa_id'   => $request->mahasiswa_id,
            'date'           => $request->date,
            'check_in_time'  => $request->check_in_time,
            'check_out_time' => $request->check_out_time,
            'keterangan'     => $request->keterangan,
            'bukti_path'     => $path,
            'status'         => 'pending' // Default status
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Pengajuan kehadiran berhasil dikirim'
        ]);
    }

    // Agar foto bukti tidak error 404/500 (sama seperti Izin)
    public function getBukti($filename)
    {
        $path = 'kehadiran/bukti/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'File bukti tidak ditemukan.');
        }

        $fullPath = storage_path('app/public/' . $path);
        return response()->file($fullPath);
    }
}