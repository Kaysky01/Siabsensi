<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers middleware.
     * Menambahkan header keamanan pada setiap response HTTP.
     * Ini penting untuk mendapatkan skor tinggi di securityheaders.com
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // ─────────────────────────────────────────────
        // 1. Strict-Transport-Security (HSTS)
        //    Paksa browser selalu pakai HTTPS selama 1 tahun.
        //    includeSubDomains = berlaku juga untuk subdomain.
        // ─────────────────────────────────────────────
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );

        // ─────────────────────────────────────────────
        // 2. X-Content-Type-Options
        //    Cegah browser "sniffing" MIME type.
        //    Mitigasi serangan XSS via file upload.
        // ─────────────────────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ─────────────────────────────────────────────
        // 3. X-Frame-Options
        //    Cegah halaman di-embed dalam iframe (clickjacking).
        //    SAMEORIGIN = hanya boleh di-iframe oleh domain sendiri.
        // ─────────────────────────────────────────────
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // ─────────────────────────────────────────────
        // 4. Referrer-Policy
        //    Kontrol seberapa banyak info referrer yang dikirim.
        //    strict-origin-when-cross-origin = kirim full URL
        //    hanya untuk same-origin, origin saja untuk cross-origin.
        // ─────────────────────────────────────────────
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ─────────────────────────────────────────────
        // 5. Permissions-Policy (dulu Feature-Policy)
        //    Batasi akses ke fitur browser seperti kamera,
        //    mikrofon, geolocation, dll.
        //    () = disabled, (self) = hanya domain sendiri.
        // ─────────────────────────────────────────────
        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            'autoplay=()',
            'camera=(self)',          // Izinkan kamera untuk absensi face detection
            'cross-origin-isolated=()',
            'display-capture=()',
            'encrypted-media=()',
            'fullscreen=(self)',
            'geolocation=(self)',     // Izinkan geolocation untuk absensi lokasi
            'gyroscope=()',
            'keyboard-map=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'picture-in-picture=()',
            'publickey-credentials-get=()',
            'screen-wake-lock=()',
            'sync-xhr=(self)',
            'usb=()',
            'xr-spatial-tracking=()',
        ]));

        // ─────────────────────────────────────────────
        // 6. Content-Security-Policy (CSP)
        //    Header terpenting untuk mencegah XSS.
        //    Definisi sumber yang diizinkan per tipe resource.
        // ─────────────────────────────────────────────
        $cspDirectives = [
            // Sumber default: hanya domain sendiri
            "default-src 'self'",

            // Script: izinkan self, nonce untuk inline, dan CDN yang dipakai
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://www.google.com https://www.gstatic.com",

            // Style: izinkan self, inline style (banyak framework butuh ini), dan CDN
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",

            // Font: Google Fonts dan CDN
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com data:",

            // Gambar: izinkan self, data URI, dan blob (untuk kamera/canvas)
            "img-src 'self' data: blob: https:",

            // Connect: API calls ke domain sendiri
            "connect-src 'self' https://www.google.com",

            // Media: audio/video dari domain sendiri
            "media-src 'self' blob:",

            // Frame: reCAPTCHA
            "frame-src 'self' https://www.google.com https://www.gstatic.com",

            // Form action: hanya ke domain sendiri
            "form-action 'self'",

            // Base URI: cegah base tag hijacking
            "base-uri 'self'",

            // Frame ancestors: siapa yang boleh embed (sama seperti X-Frame-Options)
            "frame-ancestors 'self'",

            // Object: blokir plugin lama (Flash, Java, dll)
            "object-src 'none'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        // ─────────────────────────────────────────────
        // 7. X-Permitted-Cross-Domain-Policies
        //    Cegah Adobe Flash/PDF dari cross-domain loading.
        // ─────────────────────────────────────────────
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // ─────────────────────────────────────────────
        // 8. Hapus header yang membocorkan info server
        // ─────────────────────────────────────────────
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
