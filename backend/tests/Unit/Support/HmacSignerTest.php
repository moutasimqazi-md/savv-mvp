<?php

namespace Savv\Tests\Unit\Support;

use Savv\Support\HmacSigner;
use Savv\Tests\TestCase;

class HmacSignerTest extends TestCase
{
    public function test_signature_is_deterministic_for_the_same_inputs(): void
    {
        $sig1 = HmacSigner::sign('POST', '/internal/sessions', '1000', 'nonce-1', 'bodyhash', 'secret');
        $sig2 = HmacSigner::sign('POST', '/internal/sessions', '1000', 'nonce-1', 'bodyhash', 'secret');

        $this->assertSame($sig1, $sig2);
    }

    public function test_signature_changes_when_any_component_changes(): void
    {
        $base = HmacSigner::sign('POST', '/internal/sessions', '1000', 'nonce-1', 'bodyhash', 'secret');

        $this->assertNotSame($base, HmacSigner::sign('GET', '/internal/sessions', '1000', 'nonce-1', 'bodyhash', 'secret'));
        $this->assertNotSame($base, HmacSigner::sign('POST', '/internal/sessions/x', '1000', 'nonce-1', 'bodyhash', 'secret'));
        $this->assertNotSame($base, HmacSigner::sign('POST', '/internal/sessions', '1001', 'nonce-1', 'bodyhash', 'secret'));
        $this->assertNotSame($base, HmacSigner::sign('POST', '/internal/sessions', '1000', 'nonce-2', 'bodyhash', 'secret'));
        $this->assertNotSame($base, HmacSigner::sign('POST', '/internal/sessions', '1000', 'nonce-1', 'other-hash', 'secret'));
        $this->assertNotSame($base, HmacSigner::sign('POST', '/internal/sessions', '1000', 'nonce-1', 'bodyhash', 'other-secret'));
    }

    public function test_body_hash_matches_sha256_of_body(): void
    {
        $this->assertSame(hash('sha256', '{"a":1}'), HmacSigner::bodyHash('{"a":1}'));
    }

    public function test_nonces_are_unique(): void
    {
        $this->assertNotSame(HmacSigner::newNonce(), HmacSigner::newNonce());
    }
}
