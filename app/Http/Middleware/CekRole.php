<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CekRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Pastikan user sudah login dan rolenya sesuai dengan parameter di route
        if ($request->user() && $request->user()->role === $role) {
            return $next($request);
        }

        // JIKA ROLE TIDAK SESUAI: 
        // Kembalikan user ke halaman sebelumnya (previous URL) dengan membawa pesan error
        return back()->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk mengakses halaman tersebut.');
    }
}