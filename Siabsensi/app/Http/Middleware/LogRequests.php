<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        // Log all POST requests to admin routes
        if ($request->method() === 'POST' && str_starts_with($request->path(), 'admin/')) {
            \Log::info('Admin POST Request', [
                'path' => $request->path(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user' => auth()->check() ? auth()->user()->username : 'guest',
                'role' => auth()->check() ? auth()->user()->role : 'none',
                'input_keys' => array_keys($request->all()),
                'has_csrf' => $request->has('_token'),
                'session_id' => $request->session()->getId(),
            ]);
        }
        
        return $next($request);
    }
}
