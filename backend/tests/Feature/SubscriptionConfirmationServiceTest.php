<?php

namespace Savv\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Savv\Enums\ImportSessionStatus;
use Savv\Enums\Provider;
use Savv\Models\ImportSession;
use Savv\Models\Subscription;
use Savv\Models\User;
use Savv\Models\UserCorrection;
use Savv\Services\SubscriptionConfirmationService;
use Savv\Tests\TestCase;

class SubscriptionConfirmationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeImportSession(User $user): ImportSession
    {
        return ImportSession::create([
            'user_id' => $user->id,
            'provider' => Provider::Claude,
            'status' => ImportSessionStatus::PreviewReady,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    private function preview(ImportSession $session, array $payload): \Savv\Models\SubscriptionPreview
    {
        return $session->subscriptionPreviews()->create([
            'provider_subscription_key' => $payload['provider_subscription_id'] ?? hash('sha256', $payload['plan_name']),
            'selected' => true,
            'parser_version' => 'claude@0.1.0-synthetic',
            'observed_at' => now(),
            'normalized_payload' => $payload,
        ]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'claude',
            'provider_subscription_id' => 'SYNTH-SUB-0001',
            'plan_name' => 'Claude Pro (Synthetic)',
            'original_status' => 'Active',
            'normalized_status' => 'active',
            'price' => 2000,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'renewal_at' => now()->addMonth()->toIso8601String(),
            'started_at' => null,
            'official_billing_url' => 'https://claude.ai/settings/billing',
            'observed_at' => now()->toIso8601String(),
        ], $overrides);
    }

    public function test_confirming_a_preview_creates_a_subscription(): void
    {
        $user = User::factory()->create();
        $session = $this->makeImportSession($user);
        $preview = $this->preview($session, $this->basePayload());

        app(SubscriptionConfirmationService::class)->confirm($session, new Collection([$preview]));

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame('Claude Pro (Synthetic)', $subscription->plan_name);
        $this->assertSame(2000, $subscription->price_minor);
    }

    public function test_confirming_the_same_subscription_twice_updates_rather_than_duplicates(): void
    {
        $user = User::factory()->create();

        $session1 = $this->makeImportSession($user);
        app(SubscriptionConfirmationService::class)->confirm($session1, new Collection([
            $this->preview($session1, $this->basePayload(['original_status' => 'Active', 'normalized_status' => 'active'])),
        ]));

        $session2 = $this->makeImportSession($user);
        app(SubscriptionConfirmationService::class)->confirm($session2, new Collection([
            $this->preview($session2, $this->basePayload(['original_status' => 'Cancelled', 'normalized_status' => 'cancelled'])),
        ]));

        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
        $subscription = Subscription::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(\Savv\Enums\SubscriptionStatus::Cancelled, $subscription->normalized_status);
    }

    public function test_dedupes_by_plan_name_when_no_provider_subscription_id_is_available(): void
    {
        $user = User::factory()->create();

        $session1 = $this->makeImportSession($user);
        app(SubscriptionConfirmationService::class)->confirm($session1, new Collection([
            $this->preview($session1, $this->basePayload(['provider_subscription_id' => null])),
        ]));

        $session2 = $this->makeImportSession($user);
        app(SubscriptionConfirmationService::class)->confirm($session2, new Collection([
            $this->preview($session2, $this->basePayload(['provider_subscription_id' => null, 'price' => 2500])),
        ]));

        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
        $this->assertSame(2500, Subscription::where('user_id', $user->id)->firstOrFail()->price_minor);
    }

    public function test_a_user_correction_is_not_overwritten_by_a_later_import(): void
    {
        $user = User::factory()->create();

        $session1 = $this->makeImportSession($user);
        app(SubscriptionConfirmationService::class)->confirm($session1, new Collection([
            $this->preview($session1, $this->basePayload(['price' => 2000])),
        ]));

        $subscription = Subscription::where('user_id', $user->id)->firstOrFail();

        UserCorrection::create([
            'user_id' => $user->id,
            'correctable_type' => Subscription::class,
            'correctable_id' => $subscription->id,
            'field' => 'price_minor',
            'previous_value' => '2000',
            'corrected_value' => '1500',
        ]);
        $subscription->forceFill(['price_minor' => 1500])->save();

        $session2 = $this->makeImportSession($user);
        app(SubscriptionConfirmationService::class)->confirm($session2, new Collection([
            $this->preview($session2, $this->basePayload(['price' => 2000])),
        ]));

        $this->assertSame(1500, $subscription->fresh()->price_minor);
    }
}
