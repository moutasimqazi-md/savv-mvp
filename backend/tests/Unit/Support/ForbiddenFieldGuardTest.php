<?php

namespace Savv\Tests\Unit\Support;

use RuntimeException;
use Savv\Support\ForbiddenFieldGuard;
use Savv\Tests\TestCase;

class ForbiddenFieldGuardTest extends TestCase
{
    public function test_allows_a_clean_normalized_payload(): void
    {
        ForbiddenFieldGuard::assertSafe([
            'provider_order_id' => 'AMZ-1',
            'items' => [['title' => 'Synthetic Mouse']],
        ]);

        $this->addToAssertionCount(1);
    }

    /** @dataProvider forbiddenKeyProvider */
    public function test_rejects_payloads_with_credential_shaped_keys(string $key): void
    {
        $this->expectException(RuntimeException::class);
        ForbiddenFieldGuard::assertSafe(['provider_order_id' => 'AMZ-1', $key => 'value']);
    }

    public static function forbiddenKeyProvider(): array
    {
        return [
            ['cookie'], ['session_id'], ['password'], ['passwd'], ['otp'], ['csrf'],
            ['Authorization'], ['bearer_token'], ['access_token'], ['refresh_token'], ['localStorage'],
        ];
    }

    public function test_rejects_forbidden_keys_nested_deep_inside_arrays(): void
    {
        $this->expectException(RuntimeException::class);
        ForbiddenFieldGuard::assertSafe([
            'items' => [
                ['title' => 'Synthetic', 'meta' => ['session_id' => 'abc']],
            ],
        ]);
    }
}
