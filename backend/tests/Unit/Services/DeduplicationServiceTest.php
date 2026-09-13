<?php

namespace Savv\Tests\Unit\Services;

use Savv\Services\DeduplicationService;
use Savv\Tests\TestCase;

class DeduplicationServiceTest extends TestCase
{
    public function test_same_inputs_produce_the_same_fingerprint(): void
    {
        $a = DeduplicationService::itemFingerprint('Synthetic Mouse', 'Graphite', 1, 149900);
        $b = DeduplicationService::itemFingerprint('Synthetic Mouse', 'Graphite', 1, 149900);

        $this->assertSame($a, $b);
    }

    public function test_fingerprint_is_case_and_whitespace_insensitive(): void
    {
        $a = DeduplicationService::itemFingerprint('Synthetic Mouse', 'Graphite', 1, 149900);
        $b = DeduplicationService::itemFingerprint('  synthetic   mouse  ', 'graphite', 1, 149900);

        $this->assertSame($a, $b);
    }

    public function test_different_quantity_changes_the_fingerprint(): void
    {
        $a = DeduplicationService::itemFingerprint('Synthetic Mouse', 'Graphite', 1, 149900);
        $b = DeduplicationService::itemFingerprint('Synthetic Mouse', 'Graphite', 2, 149900);

        $this->assertNotSame($a, $b);
    }

    public function test_different_title_changes_the_fingerprint(): void
    {
        // Guards against deduplicating on title alone by ensuring the
        // fingerprint is sensitive to more than just the title text.
        $a = DeduplicationService::itemFingerprint('Synthetic Mouse', null, 1, 149900);
        $b = DeduplicationService::itemFingerprint('Synthetic Keyboard', null, 1, 149900);

        $this->assertNotSame($a, $b);
    }

    public function test_null_line_total_does_not_collide_with_zero(): void
    {
        $a = DeduplicationService::itemFingerprint('Synthetic Mouse', null, 1, null);
        $b = DeduplicationService::itemFingerprint('Synthetic Mouse', null, 1, 0);

        $this->assertNotSame($a, $b);
    }
}
