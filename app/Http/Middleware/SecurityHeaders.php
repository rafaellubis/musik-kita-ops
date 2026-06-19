<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware keamanan: menambahkan HTTP security headers ke setiap response.
 *
 * Header yang ditambahkan:
 * - X-Frame-Options       → cegah clickjacking (iframe embedding)
 * - X-Content-Type-Options → cegah MIME-type sniffing
 * - X-XSS-Protection      → proteksi XSS tambahan di browser lama
 * - Referrer-Policy        → batasi info referrer ke origin saja
 * - Permissions-Policy     → nonaktifkan akses kamera/mikrofon/geolokasi
 * - Strict-Transport-Security → paksa HTTPS (hanya jika koneksi sudah secure)
 *
 * Didaftarkan global di bootstrap/app.php → withMiddleware().
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS — hanya aktif jika request sudah melalui HTTPS.
        // max-age 1 tahun (31536000 detik) sesuai best practice.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
