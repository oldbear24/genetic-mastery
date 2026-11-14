<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (SEC-5). The CSP needs no
 * 'unsafe-inline' or 'unsafe-eval': Alpine runs in its CSP build, progress
 * bars are SVG and there are no inline scripts or style attributes.
 * Responses that set their own CSP (evidence images) keep it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        return $response;
    }

    public function contentSecurityPolicy(): string
    {
        $script = ["'self'"];
        $style = ["'self'"];
        $connect = ["'self'"];

        // `npm run dev`: allow the Vite dev server and its injected styles.
        if (app()->isLocal() && Vite::isRunningHot()) {
            $hot = rtrim((string) @file_get_contents(public_path('hot')));
            $ws = preg_replace('#^http#', 'ws', $hot);
            $script[] = $hot;
            $style[] = $hot;
            $style[] = "'unsafe-inline'";
            $connect[] = $hot;
            $connect[] = $ws;
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            // blob: for upload previews, Discord CDN for avatars.
            "img-src 'self' data: blob: https://cdn.discordapp.com",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
