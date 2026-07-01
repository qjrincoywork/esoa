<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Non-CSP headers — applied in every environment
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // CSP is only enforced in production.
        // Vite's dev server (HMR, dynamic imports, eval) makes strict CSP
        // impractical in local — the other headers still protect non-prod.
        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy', $this->productionCsp());
        }

        return $response;
    }

    private function productionCsp(): string
    {
        $directives = [
            "default-src 'self'",

            // 'unsafe-inline' covers:
            //   • the dark-mode inline <script> in app.blade.php
            //   • Inertia's data-page attribute bootstrap
            // Remove 'unsafe-inline' and adopt nonces/hashes once templates are updated.
            "script-src 'self' 'unsafe-inline'",

            // fonts.bunny.net is the GDPR-friendly font CDN used by this app (see app.blade.php).
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net",

            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }
}
