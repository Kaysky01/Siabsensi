<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login()
    {
        return view('login');
    }

    public function auth(Request $request)
    {
        // Validasi input dari form
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Cek apakah user menceklis "Ingat Saya"
        $remember = $request->has('remember');

        // Coba melakukan autentikasi ke database
        if (Auth::attempt($credentials, $remember)) {
            
            // Hindari serangan Session Fixation
            $request->session()->regenerate();

            // Ambil data user yang berhasil login
            $user = Auth::user();

            // dd('Login Berhasil! Role user ini adalah: ' . $user->role);

            // Redirect berdasarkan role
            if ($user->role === 'admin') {
                // Arahkan admin secara spesifik ke route 'admin.dashboard' (/admin)
                return redirect()->route('admin.dashboard'); 
            } else if ($user->role === 'timdis') {
                // Arahkan timdis ke rute yang sesuai (silakan disesuaikan nanti)
                return redirect()->intended('/'); 
            } else if ($user->role === 'mahasiswa') {
                // Arahkan mahasiswa ke portal mahasiswa
                return redirect()->route('mahasiswa.dashboard'); 
            }
        }

        // Jika login gagal (username/password salah)
        return back()->withErrors([
            'username' => 'Username atau password yang Anda masukkan salah.',
        ])->onlyInput('username'); // Kembalikan username agar user tidak perlu mengetik ulang
    }

    public function logout(Request $request)
    {
        Auth::logout();

        // Hapus session dan regenerasi token CSRF untuk keamanan
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
