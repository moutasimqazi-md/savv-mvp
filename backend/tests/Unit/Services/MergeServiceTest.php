<?php

namespace Savv\Tests\Unit\Services;

use Savv\Services\MergeService;
use Savv\Tests\TestCase;

class MergeServiceTest extends TestCase
{
    public function test_incoming_values_win_when_there_is_no_conflict(): void
    {
        $result = MergeService::mergeAttributes(
            ['total_minor' => 1000],
            ['total_minor' => 2000],
        );

        $this->assertSame(2000, $result['attributes']['total_minor']);
        $this->assertEmpty($result['conflicts']);
    }

    public function test_empty_incoming_value_never_overwrites_a_populated_one(): void
    {
        $result = MergeService::mergeAttributes(
            ['original_status' => 'Delivered'],
            ['original_status' => null],
        );

        $this->assertArrayNotHasKey('original_status', $result['attributes']);
    }

    public function test_corrected_fields_are_never_overwritten_and_are_reported_as_conflicts(): void
    {
        $result = MergeService::mergeAttributes(
            ['total_minor' => 99900],
            ['total_minor' => 149900],
            correctedFields: ['total_minor'],
        );

        $this->assertArrayNotHasKey('total_minor', $result['attributes']);
        $this->assertContains('total_minor', $result['conflicts']);
    }

    public function test_a_correction_that_matches_the_incoming_value_is_not_a_conflict(): void
    {
        $result = MergeService::mergeAttributes(
            ['total_minor' => 149900],
            ['total_minor' => 149900],
            correctedFields: ['total_minor'],
        );

        $this->assertEmpty($result['conflicts']);
    }
}
