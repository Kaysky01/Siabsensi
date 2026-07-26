<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RateLimitService
{
    /**
     * Maximum login attempts before lockout
     */
    const MAX_ATTEMPTS = 5;

    /**
     * Lockout duration in seconds (2 minutes)
     */
    const LOCKOUT_DURATION = 120;

    /**
     * Get the cache key for storing login attempts
     *
     * @param string $username
     * @param string $ip
     * @return string
     */
    protected function getAttemptKey(string $username, string $ip): string
    {
        return "login_attempts:{$username}:{$ip}";
    }

    /**
     * Get the cache key for storing lockout timestamp
     *
     * @param string $username
     * @param string $ip
     * @return string
     */
    protected function getLockoutKey(string $username, string $ip): string
    {
        return "login_lockout:{$username}:{$ip}";
    }

    /**
     * Check if the user is currently locked out
     *
     * @param string $username
     * @param string $ip
     * @return bool
     */
    public function isLocked(string $username, string $ip): bool
    {
        return Cache::has($this->getLockoutKey($username, $ip));
    }

    /**
     * Get remaining lockout time in seconds
     *
     * @param string $username
     * @param string $ip
     * @return int
     */
    public function getRemainingLockoutTime(string $username, string $ip): int
    {
        $lockoutKey = $this->getLockoutKey($username, $ip);

        if (!Cache::has($lockoutKey)) {
            return 0;
        }

        $lockedUntil = Cache::get($lockoutKey);
        $remaining = $lockedUntil - time();

        return max(0, $remaining);
    }

    /**
     * Get the current number of failed attempts
     *
     * @param string $username
     * @param string $ip
     * @return int
     */
    public function getAttempts(string $username, string $ip): int
    {
        return (int) Cache::get($this->getAttemptKey($username, $ip), 0);
    }

    /**
     * Record a failed login attempt
     *
     * @param string $username
     * @param string $ip
     * @return int Current attempt count
     */
    public function recordFailedAttempt(string $username, string $ip): int
    {
        $attemptKey = $this->getAttemptKey($username, $ip);
        $lockoutKey = $this->getLockoutKey($username, $ip);

        $attempts = $this->getAttempts($username, $ip) + 1;

        // Store attempt count with TTL equal to lockout duration
        Cache::put($attemptKey, $attempts, self::LOCKOUT_DURATION);

        // Lock if max attempts reached
        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockedUntil = time() + self::LOCKOUT_DURATION;
            Cache::put($lockoutKey, $lockedUntil, self::LOCKOUT_DURATION);
        }

        return $attempts;
    }

    /**
     * Reset attempts counter (called on successful login)
     *
     * @param string $username
     * @param string $ip
     * @return void
     */
    public function resetAttempts(string $username, string $ip): void
    {
        Cache::forget($this->getAttemptKey($username, $ip));
        Cache::forget($this->getLockoutKey($username, $ip));
    }

    /**
     * Get remaining login attempts before lockout
     *
     * @param string $username
     * @param string $ip
     * @return int
     */
    public function getRemainingAttempts(string $username, string $ip): int
    {
        return max(0, self::MAX_ATTEMPTS - $this->getAttempts($username, $ip));
    }
}
