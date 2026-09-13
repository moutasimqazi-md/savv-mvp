<?php

namespace Savv\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Savv\Support\HmacSigner;

/**
 * Signed HTTP client for the Node.js Playwright runner's internal API. The
 * runner binds to 127.0.0.1 only and is never exposed publicly - see
 * /runner/src/security/verifySignature.js for the matching verification.
 */
final class RunnerClient
{
    private string $baseUrl;

    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('savv.runner.base_url'), '/');
        $this->secret = (string) config('savv.runner.shared_secret');

        if ($this->secret === '') {
            throw new RuntimeException('RUNNER_SHARED_SECRET is not configured.');
        }
    }

    public function startSession(string $sessionId, string $provider): array
    {
        return $this->post("/internal/sessions", [
            'sessionId' => $sessionId,
            'provider' => $provider,
        ]);
    }

    public function getSessionStatus(string $sessionId): array
    {
        return $this->get("/internal/sessions/{$sessionId}");
    }

    public function scan(string $sessionId): array
    {
        return $this->post("/internal/sessions/{$sessionId}/scan", []);
    }

    /**
     * Registers a single-use, short-lived view token with the runner's
     * shared websockify broker so the noVNC viewer can connect to this
     * session's isolated browser. No-op on the runner side in headed-local
     * (Windows/dev) mode - see runner/src/sessionManager.js.
     */
    public function registerViewToken(string $sessionId, string $token, int $ttlSeconds): array
    {
        return $this->post("/internal/sessions/{$sessionId}/view-token", [
            'token' => $token,
            'ttlSeconds' => $ttlSeconds,
        ]);
    }

    public function stop(string $sessionId): array
    {
        return $this->post("/internal/sessions/{$sessionId}/stop", []);
    }

    public function health(string $sessionId): array
    {
        return $this->get("/internal/sessions/{$sessionId}/health");
    }

    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    private function get(string $path): array
    {
        return $this->request('GET', $path, []);
    }

    private function request(string $method, string $path, array $body): array
    {
        $rawBody = $method === 'GET' ? '' : json_encode($body, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $nonce = HmacSigner::newNonce();
        $bodyHash = HmacSigner::bodyHash($rawBody);
        $signature = HmacSigner::sign($method, $path, $timestamp, $nonce, $bodyHash, $this->secret);

        $headers = [
            'X-Savv-Timestamp' => $timestamp,
            'X-Savv-Nonce' => $nonce,
            'X-Savv-Body-Hash' => $bodyHash,
            'X-Savv-Signature' => $signature,
        ];

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders($headers)
                ->timeout((int) config('savv.runner.request_timeout_seconds', 10))
                ->{strtolower($method)}($path, $method === 'GET' ? [] : $body);

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException|ConnectionException $e) {
            throw new RuntimeException('Runner request failed: '.$e->getMessage(), previous: $e);
        }
    }
}
