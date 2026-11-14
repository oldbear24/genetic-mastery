<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Discord signs every interaction with Ed25519 over timestamp + raw body.
 * An invalid or missing signature gets 401; Discord tests this regularly
 * and disconnects endpoints that accept bad signatures.
 */
class VerifyDiscordSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = (string) $request->header('X-Signature-Ed25519', '');
        $timestamp = (string) $request->header('X-Signature-Timestamp', '');
        $publicKey = (string) config('services.discord.public_key', '');

        if (! $this->valid($signature, $timestamp, $request->getContent(), $publicKey)) {
            return response()->json(['message' => 'invalid request signature'], 401);
        }

        return $next($request);
    }

    private function valid(string $signature, string $timestamp, string $body, string $publicKey): bool
    {
        if ($timestamp === '' || ! ctype_xdigit($signature) || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES * 2
            || ! ctype_xdigit($publicKey) || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES * 2) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached(hex2bin($signature), $timestamp.$body, hex2bin($publicKey));
        } catch (\SodiumException) {
            return false;
        }
    }
}
