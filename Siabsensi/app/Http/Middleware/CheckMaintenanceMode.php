<?php

namespace App\Http\Middleware;

use App\Models\SystemConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request when Maintenance Mode is active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (SystemConfig::isMaintenanceMode()) {
            $user = $request->user();

            // 1. Jika user sudah login:
            if ($user) {
                // Role 'admin' BEBAS akses seluruh fitur sistem
                if ($user->role === 'admin') {
                    return $next($request);
                }

                // Untuk role non-admin (mahasiswa, garda, timdis, acara)
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sistem sedang dalam mode pemeliharaan (Maintenance Mode). Akses dibatasi khusus Admin.',
                    ], 503);
                }

                // Izinkan rute maintenance dan logout untuk non-admin
                if ($request->routeIs('maintenance') || $request->routeIs('logout')) {
                    return $next($request);
                }

                return redirect()->route('maintenance');
            }

            // 2. Jika user belum login (Guest):
            // Izinkan rute root (/), login, auth, logout, maintenance, & api hardware
            if ($request->is('/') || $request->routeIs(['maintenance', 'login', 'auth', 'logout']) || $request->is('api/*') || $request->is('file-bukti/*')) {
                return $next($request);
            }

            // Rute web terproteksi lainnya dialihkan ke maintenance
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sistem sedang dalam mode pemeliharaan (Maintenance Mode).',
                ], 503);
            }

            return redirect()->route('maintenance');
        }

        return $next($request);
    }
}
