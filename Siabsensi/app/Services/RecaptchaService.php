<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    /**
     * Validate reCAPTCHA token
     *
     * @param string|null $token
     * @param string $remoteIp
     * @return bool
     */
    public function validate(?string $token, string $remoteIp): bool
    {
        if (empty($token)) {
            return false;
        }

        $secretKey = config('recaptcha.secret_key');
        
        if (empty($secretKey)) {
            Log::warning('reCAPTCHA secret key not configured');
            // Graceful degradation: allow login if not configured
            return true;
        }

        try {
            $response = Http::asForm()->post(config('recaptcha.verify_url'), [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success'] === true) {
                    return true;
                }
                
                Log::warning('reCAPTCHA validation failed', [
                    'error_codes' => $data['error-codes'] ?? [],
                    'hostname' => $data['hostname'] ?? null,
                ]);
                
                return false;
            }

            Log::error('reCAPTCHA API request failed', [
                'status' => $response->status(),
            ]);

            // Graceful degradation: allow login if reCAPTCHA service is down
            return true;

        } catch (\Exception $e) {
            Log::error('reCAPTCHA validation exception', [
                'message' => $e->getMessage(),
            ]);

            // Graceful degradation: allow login on exception
            return true;
        }
    }

    /**
     * Get reCAPTCHA site key
     *
     * @return string|null
     */
    public function getSiteKey(): ?string
    {
        return config('recaptcha.site_key');
    }
}
