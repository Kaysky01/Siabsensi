<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RateLimitService;
use Symfony\Component\HttpFoundation\Response;

class LoginRateLimiter
{
    protected $rateLimitService;

    /**
     * Create a new middleware instance.
     *
     * @param RateLimitService $rateLimitService
     */
    public function __construct(RateLimitService $rateLimitService)
    {
        $this->rateLimitService = $rateLimitService;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->input('username', '');
        $ip = $request->ip();

        // Check if user is locked out
        if ($this->rateLimitService->isLocked($username, $ip)) {
            $remainingSeconds = $this->rateLimitService->getRemainingLockoutTime($username, $ip);

            return back()->withErrors([
                'username' => "Terlalu banyak percobaan login gagal. Silakan tunggu {$remainingSeconds} detik lagi.",
            ])->withInput($request->only('username'))
              ->with('lockout_seconds', $remainingSeconds);
        }

        return $next($request);
    }
}
