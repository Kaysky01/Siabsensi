<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use App\Models\Prodi;
use App\Models\Kompi;
use App\Models\User;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    // --- JURUSAN & PRODI ---
    public function indexJurusanProdi()
    {
        $jurusanList = Jurusan::with('prodi')->orderBy('nama')->get();
        return view('admin.master-jurusan-prodi', compact('jurusanList'));
    }

    public function storeJurusan(Request $request)
    {
        $request->validate(['nama' => 'required|string|max:255']);
        Jurusan::create(['nama' => $request->nama]);
        \Illuminate\Support\Facades\Cache::forget('master_jurusan');
        \Illuminate\Support\Facades\Cache::forget('master_jurusan_prodi');
        return back()->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function updateJurusan(Request $request, $id)
    {
        $request->validate(['nama' => 'required|string|max:255']);
        Jurusan::findOrFail($id)->update(['nama' => $request->nama]);
        \Illuminate\Support\Facades\Cache::forget('master_jurusan');
        \Illuminate\Support\Facades\Cache::forget('master_jurusan_prodi');
        return back()->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function destroyJurusan($id)
    {
        Jurusan::findOrFail($id)->delete();
        \Illuminate\Support\Facades\Cache::forget('master_jurusan');
        \Illuminate\Support\Facades\Cache::forget('master_jurusan_prodi');
        \Illuminate\Support\Facades\Cache::forget('master_prodi');
        return back()->with('success', 'Jurusan beserta Prodinya berhasil dihapus.');
    }

    public function storeProdi(Request $request)
    {
        $request->validate([
            'jurusan_id' => 'required|exists:jurusan,id',
            'nama' => 'required|string|max:255'
        ]);
        Prodi::create($request->only('jurusan_id', 'nama'));
        \Illuminate\Support\Facades\Cache::forget('master_prodi');
        \Illuminate\Support\Facades\Cache::forget('master_jurusan_prodi');
        return back()->with('success', 'Prodi berhasil ditambahkan.');
    }

    public function updateProdi(Request $request, $id)
    {
        $request->validate([
            'jurusan_id' => 'required|exists:jurusan,id',
            'nama' => 'required|string|max:255'
        ]);
        Prodi::findOrFail($id)->update($request->only('jurusan_id', 'nama'));
        \Illuminate\Support\Facades\Cache::forget('master_prodi');
        \Illuminate\Support\Facades\Cache::forget('master_jurusan_prodi');
        return back()->with('success', 'Prodi berhasil diperbarui.');
    }

    public function destroyProdi($id)
    {
        Prodi::findOrFail($id)->delete();
        \Illuminate\Support\Facades\Cache::forget('master_prodi');
        \Illuminate\Support\Facades\Cache::forget('master_jurusan_prodi');
        return back()->with('success', 'Prodi berhasil dihapus.');
    }

    // --- KOMPI ---
    public function indexKompi()
    {
        $kompiList = Kompi::orderBy('nama')->get();
        $gardaUsers = User::where('role', 'garda')->orderBy('full_name')->get();

        foreach ($kompiList as $k) {
            $k->gardas = User::where('role', 'garda')->where('assigned_kompi', $k->nama)->get();
        }

        return view('admin.master-kompi', compact('kompiList', 'gardaUsers'));
    }

    public function storeKompi(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255|unique:kompi,nama',
            'garda_ids' => 'nullable|array|max:5',
            'garda_ids.*' => 'exists:users,username'
        ], [
            'garda_ids.max' => 'Maksimal penanggung jawab adalah 5 garda.'
        ]);
        
        $kompi = Kompi::create($request->only('nama'));

        if (!empty($request->garda_ids)) {
            User::whereIn('username', $request->garda_ids)->update(['assigned_kompi' => $kompi->nama]);
        }

        \Illuminate\Support\Facades\Cache::forget('master_kompi');
        return back()->with('success', 'Kompi berhasil ditambahkan.');
    }

    public function updateKompi(Request $request, $id)
    {
        $request->validate([
            'nama' => 'required|string|max:255|unique:kompi,nama,' . $id,
            'garda_ids' => 'nullable|array|max:5',
            'garda_ids.*' => 'exists:users,username'
        ], [
            'garda_ids.max' => 'Maksimal penanggung jawab adalah 5 garda.'
        ]);
        $kompi = Kompi::findOrFail($id);
        $oldNama = $kompi->nama;

        // Clear previous assignment
        User::where('assigned_kompi', $oldNama)->update(['assigned_kompi' => null]);

        $kompi->update($request->only('nama'));

        // Assign to new gardas if provided
        if (!empty($request->garda_ids)) {
            User::whereIn('username', $request->garda_ids)->update(['assigned_kompi' => $kompi->nama]);
        }

        \Illuminate\Support\Facades\Cache::forget('master_kompi');
        return back()->with('success', 'Kompi berhasil diperbarui.');
    }

    public function destroyKompi($id)
    {
        $kompi = Kompi::findOrFail($id);
        User::where('assigned_kompi', $kompi->nama)->update(['assigned_kompi' => null]);
        $kompi->delete();

        \Illuminate\Support\Facades\Cache::forget('master_kompi');
        return back()->with('success', 'Kompi berhasil dihapus.');
    }
}
