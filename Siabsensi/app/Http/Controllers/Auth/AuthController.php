<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = trim($request->username);
        $password = trim($request->password);
        $remember = $request->boolean('remember');

        // Coba login normal (username + password)
        if (Auth::attempt([
            'username' => $username,
            'password' => $password,
        ], $remember)) {

            /** @var User $user */
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi Administrator.',
                ])->onlyInput('username');
            }

            $request->session()->regenerate();
            $user->update(['last_login' => now()]);

            return match ($user->role) {
                'admin'     => redirect()->route('admin.dashboard'),
                'timdis'    => redirect()->route('timdis.dashboard'),
                'garda'     => redirect()->route('garda.dashboard'),
                'mahasiswa' => redirect()->route('mahasiswa.dashboard'),
                default     => redirect('/login'),
            };
        }

        // Fallback: coba login mahasiswa dengan nomor registrasi + tanggal lahir
        // Jika input password memiliki spasi, garis miring, dll, kita bersihkan hanya angkanya
        $cleanPassword = preg_replace('/[^0-9]/', '', $password);
        
        $userByNim = \App\Models\User::where('username', $username)
            ->where('role', 'mahasiswa')
            ->with('mahasiswa')
            ->first();

        if ($userByNim && $userByNim->mahasiswa && $userByNim->mahasiswa->tanggal_lahir) {
            $tglLahir = \Carbon\Carbon::parse($userByNim->mahasiswa->tanggal_lahir)->format('dmY'); // ddmmyyyy
            
            // Allow matching original password (if exact) or the cleaned password (numbers only)
            if ($password === $tglLahir || $cleanPassword === $tglLahir) {
                // Password cocok → set password di DB jika belum (migrasi dari default)
                if (!\Illuminate\Support\Facades\Hash::check($tglLahir, $userByNim->password)) {
                    $userByNim->update(['password' => \Illuminate\Support\Facades\Hash::make($tglLahir)]);
                }
                
                // Login manual
                Auth::login($userByNim, $remember);

                if (! $userByNim->is_active) {
                    Auth::logout();
                    return back()->withErrors([
                        'username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi Administrator.',
                    ])->onlyInput('username');
                }

                $request->session()->regenerate();
                $userByNim->update(['last_login' => now()]);

                return redirect()->route('mahasiswa.dashboard');
            }
        }

        return back()->withErrors([
            'username' => 'Nomor Registrasi atau password yang Anda masukkan salah.',
        ])->onlyInput('username');
    }


    public function logout(Request $request)
    {
        Auth::logout();

        // Hapus session dan regenerasi token CSRF untuk keamanan
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Mengambil data profil user yang sedang login
     */
    public function me()
    {
        // Ambil user yang sedang login
        /** @var User $user */
        $user = Auth::user();

        // Kondisi jika user belum login atau sesi telah kadaluwarsa
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak valid atau belum login.',
            ], 401);
        }

        // Kembalikan data user dalam format JSON
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'mahasiswa' => $user->mahasiswa,
                'permissions' => [
                    'can_manage_users' => $user->role === 'admin',
                    'can_edit_settings' => $user->role === 'admin',
                    'can_manage_mahasiswa' => $user->role === 'admin',
                    'can_verify_submissions' => in_array($user->role, ['timdis', 'garda'], true),
                ],
            ],
        ]);
    }
}
