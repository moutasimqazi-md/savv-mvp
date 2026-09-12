<?php

namespace Savv\Support;

use Illuminate\Support\Str;

/**
 * Signs outgoing requests to the Node.js Playwright runner. The runner
 * verifies the signature, timestamp skew, and nonce independently - see
 * /runner/src/security/verifySignature.js, which must stay in lock-step
 * with the string-to-sign format used here.
 */
final class HmacSigner
{
    public static function stringToSign(string $method, string $path, string $timestamp, string $nonce, string $bodyHash): string
    {
        return implode("\n", [strtoupper($method), $path, $timestamp, $nonce, $bodyHash]);
    }

    public static function sign(string $method, string $path, string $timestamp, string $nonce, string $bodyHash, string $secret): string
    {
        return hash_hmac('sha256', self::stringToSign($method, $path, $timestamp, $nonce, $bodyHash), $secret);
    }

    public static function bodyHash(string $rawBody): string
    {
        return hash('sha256', $rawBody);
    }

    public static function newNonce(): string
    {
        return (string) Str::uuid();
    }
}
