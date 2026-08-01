<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RecaptchaService;
use App\Services\RateLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login()
    {
        return view('login');
    }

    public function auth(Request $request, RecaptchaService $recaptchaService, RateLimitService $rateLimitService)
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'g-recaptcha-response' => 'required',
        ], [
            'g-recaptcha-response.required' => 'Silakan centang kotak "Saya bukan robot".',
        ]);

        $username = trim($request->username);
        $password = trim($request->password);
        $remember = $request->boolean('remember');
        $recaptchaToken = $request->input('g-recaptcha-response');
        $ip = $request->ip();

        // Validate reCAPTCHA
        if (!$recaptchaService->validate($recaptchaToken, $ip)) {
            return back()->withErrors([
                'username' => 'Verifikasi reCAPTCHA gagal. Silakan coba lagi.',
            ])->onlyInput('username');
        }

        // Coba login normal (username + password)
        $loginAttemptSuccess = false;
        try {
            if (Auth::attempt([
                'username' => $username,
                'password' => $password,
            ], $remember)) {
                $loginAttemptSuccess = true;
            }
        } catch (\Throwable $e) {
            $loginAttemptSuccess = false;
        }

        // Jika Auth::attempt gagal (bisa jadi karena hash di DB format MD5 / plaintext / md5(NIM))
        if (!$loginAttemptSuccess) {
            $legacyUser = User::where('username', $username)->first();
            if ($legacyUser) {
                if (
                    md5($password) === $legacyUser->password ||
                    $password === $legacyUser->password ||
                    md5($username) === $legacyUser->password
                ) {
                    $legacyUser->update(['password' => \Illuminate\Support\Facades\Hash::make($password)]);
                    Auth::login($legacyUser, $remember);
                    $loginAttemptSuccess = true;
                }
            }
        }

        if ($loginAttemptSuccess) {
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

            // SUCCESS: Reset rate limit
            $rateLimitService->resetAttempts($username, $ip);

            $request->session()->regenerate();
            $user->update(['last_login' => now()]);

            return match ($user->role) {
                'admin'     => redirect()->route('admin.dashboard'),
                'timdis'    => redirect()->route('timdis.dashboard'),
                'garda'     => redirect()->route('garda.dashboard'),
                'acara'     => redirect()->route('acara.dashboard'),
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

        if ($userByNim) {
            $tglLahir = ($userByNim->mahasiswa && $userByNim->mahasiswa->tanggal_lahir)
                ? \Carbon\Carbon::parse($userByNim->mahasiswa->tanggal_lahir)->format('dmY')
                : null;
            
            $isPasswordMatch = (
                ($tglLahir && ($password === $tglLahir || $cleanPassword === $tglLahir)) ||
                ($legacyPass = $userByNim->password) && (
                    md5($password) === $legacyPass ||
                    md5($cleanPassword) === $legacyPass ||
                    ($tglLahir && md5($tglLahir) === $legacyPass) ||
                    md5($username) === $legacyPass ||
                    $password === $legacyPass
                )
            );

            if ($isPasswordMatch) {
                $newPass = ($tglLahir && ($password === $tglLahir || $cleanPassword === $tglLahir)) ? $tglLahir : $password;
                $userByNim->update(['password' => \Illuminate\Support\Facades\Hash::make($newPass)]);
                
                // Login manual
                Auth::login($userByNim, $remember);

                if (! $userByNim->is_active) {
                    Auth::logout();
                    return back()->withErrors([
                        'username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi Administrator.',
                    ])->onlyInput('username');
                }

                // SUCCESS: Reset rate limit
                $rateLimitService->resetAttempts($username, $ip);

                $request->session()->regenerate();
                $userByNim->update(['last_login' => now()]);

                return redirect()->route('mahasiswa.dashboard');
            }
        }

        // FAILED: Record failed attempt
        $attempts = $rateLimitService->recordFailedAttempt($username, $ip);
        $remainingAttempts = $rateLimitService->getRemainingAttempts($username, $ip);

        if ($remainingAttempts > 0) {
            return back()->withErrors([
                'username' => "Nomor Registrasi atau password yang Anda masukkan salah. Sisa percobaan: {$remainingAttempts} kali.",
            ])->onlyInput('username');
        } else {
            return back()->withErrors([
                'username' => 'Terlalu banyak percobaan login gagal. Silakan tunggu 2 menit.',
            ])->onlyInput('username')
              ->with('lockout_seconds', RateLimitService::LOCKOUT_DURATION);
        }
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
