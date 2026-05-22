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
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Pastikan user sudah login dan rolenya ada dalam daftar parameter di route
        if ($request->user() && in_array($request->user()->role, $roles)) {
            return $next($request);
        }

        // JIKA ROLE TIDAK SESUAI: 
        // Kembalikan user ke halaman sebelumnya (previous URL) dengan membawa pesan error
        return back()->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk mengakses halaman tersebut.');
    }
}