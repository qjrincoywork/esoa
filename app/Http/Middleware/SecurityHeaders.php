<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a per-request CSP nonce before the view renders. Laravel's
        // Vite helper stamps this nonce onto the @vite tags automatically, and
        // the inline dark-mode <script> reads it via Vite::cspNonce(), so the
        // CSP no longer needs 'unsafe-inline' for scripts.
        $nonce = Vite::useCspNonce();

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
            $response->headers->set('Content-Security-Policy', $this->productionCsp($nonce));
        }

        return $response;
    }

    private function productionCsp(string $nonce): string
    {
        $directives = [
            "default-src 'self'",

            // Scripts are allow-listed by per-request nonce (see handle()); no
            // 'unsafe-inline'. 'strict-dynamic' lets the nonced Vite entry load
            // its hashed chunks without each needing its own nonce.
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'",

            // Inline styles are still required by Vue/Tailwind runtime styling.
            // fonts.bunny.net is the GDPR-friendly font CDN used by this app.
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
