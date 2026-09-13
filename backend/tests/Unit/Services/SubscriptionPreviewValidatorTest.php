<?php

namespace Savv\Tests\Unit\Services;

use InvalidArgumentException;
use Savv\Enums\Provider;
use Savv\Services\SubscriptionPreviewValidator;
use Savv\Tests\TestCase;

class SubscriptionPreviewValidatorTest extends TestCase
{
    private function validSubscription(array $overrides = []): array
    {
        return array_merge([
            'provider_subscription_id' => 'SYNTH-SUB-0001',
            'plan_name' => 'Claude Pro (Synthetic)',
            'original_status' => 'Active',
            'price' => '20.00',
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'renewal_at' => '2026-10-15',
            'official_billing_url' => 'https://claude.ai/settings/billing',
            'observed_at' => '2026-09-14T10:00:00Z',
        ], $overrides);
    }

    public function test_accepts_a_well_formed_subscription(): void
    {
        $result = SubscriptionPreviewValidator::validateScanResult([$this->validSubscription()], Provider::Claude);

        $this->assertCount(1, $result['subscriptions']);
        $this->assertSame('Claude Pro (Synthetic)', $result['subscriptions'][0]['plan_name']);
        $this->assertSame(2000, $result['subscriptions'][0]['price']);
        $this->assertSame('active', $result['subscriptions'][0]['normalized_status']);
        $this->assertSame('monthly', $result['subscriptions'][0]['billing_cycle']);
    }

    public function test_rejects_more_subscriptions_than_the_allowed_maximum(): void
    {
        $many = array_fill(0, 26, $this->validSubscription());

        $this->expectException(InvalidArgumentException::class);
        SubscriptionPreviewValidator::validateScanResult($many, Provider::Claude);
    }

    public function test_rejects_unknown_top_level_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SubscriptionPreviewValidator::validateScanResult([
            $this->validSubscription(['payment_method' => 'should never be here']),
        ], Provider::Claude);
    }

    public function test_rejects_forbidden_field_names_anywhere_in_the_payload(): void
    {
        $this->expectException(\RuntimeException::class);
        SubscriptionPreviewValidator::validateScanResult([
            array_merge($this->validSubscription(), ['session_id' => 'abc']),
        ], Provider::Claude);
    }

    public function test_rejects_subscription_missing_plan_name(): void
    {
        $subscription = $this->validSubscription();
        unset($subscription['plan_name']);

        $this->expectException(InvalidArgumentException::class);
        SubscriptionPreviewValidator::validateScanResult([$subscription], Provider::Claude);
    }

    public function test_strips_html_from_plan_name(): void
    {
        $subscription = $this->validSubscription(['plan_name' => '<img src=x onerror=alert(1)>Claude Pro']);

        $result = SubscriptionPreviewValidator::validateScanResult([$subscription], Provider::Claude);

        $this->assertSame('Claude Pro', $result['subscriptions'][0]['plan_name']);
    }

    public function test_drops_disallowed_billing_url_but_keeps_the_rest(): void
    {
        $subscription = $this->validSubscription(['official_billing_url' => 'javascript:alert(1)']);

        $result = SubscriptionPreviewValidator::validateScanResult([$subscription], Provider::Claude);

        $this->assertNull($result['subscriptions'][0]['official_billing_url']);
        $this->assertSame('Claude Pro (Synthetic)', $result['subscriptions'][0]['plan_name']);
    }

    public function test_unparsable_price_is_warned_and_left_blank_not_rejected(): void
    {
        $subscription = $this->validSubscription(['price' => 'free forever']);

        $result = SubscriptionPreviewValidator::validateScanResult([$subscription], Provider::Claude);

        $this->assertNull($result['subscriptions'][0]['price']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_unknown_billing_cycle_falls_back_to_unknown(): void
    {
        $subscription = $this->validSubscription(['billing_cycle' => 'fortnightly']);

        $result = SubscriptionPreviewValidator::validateScanResult([$subscription], Provider::Claude);

        $this->assertSame('unknown', $result['subscriptions'][0]['billing_cycle']);
    }
}
