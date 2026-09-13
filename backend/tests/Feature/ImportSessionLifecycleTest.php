<?php

namespace Savv\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Savv\Enums\ImportSessionStatus;
use Savv\Enums\Provider;
use Savv\Models\Consent;
use Savv\Models\ImportSession;
use Savv\Models\User;
use Savv\Services\ImportSessionService;
use Savv\Tests\TestCase;

class ImportSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function consentFor(User $user, Provider $provider = Provider::AmazonIn): Consent
    {
        return Consent::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'consent_version' => '2026-09-v1',
            'accepted_at' => now(),
        ]);
    }

    public function test_starting_a_session_calls_the_runner_and_persists_process_refs(): void
    {
        Http::fake([
            '127.0.0.1:3010/*' => Http::response(['processRef' => 'proc-1', 'displayRef' => ':100'], 201),
        ]);

        $user = User::factory()->create();
        $consent = $this->consentFor($user);

        $session = app(ImportSessionService::class)->start($user, Provider::AmazonIn, $consent);

        $this->assertSame(ImportSessionStatus::Starting, $session->status);
        $this->assertSame('proc-1', $session->runner_process_ref);
        $this->assertNotEmpty($session->public_id);
        $this->assertTrue($session->expires_at->isFuture());
    }

    public function test_only_one_active_import_session_is_allowed_per_user(): void
    {
        Http::fake(['127.0.0.1:3010/*' => Http::response(['processRef' => 'proc-1'], 201)]);

        $user = User::factory()->create();
        $service = app(ImportSessionService::class);

        $service->start($user, Provider::AmazonIn, $this->consentFor($user));

        $this->expectException(\RuntimeException::class);
        $service->start($user, Provider::Flipkart, $this->consentFor($user, Provider::Flipkart));
    }

    public function test_a_user_cannot_view_another_users_import_session(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $session = ImportSession::create([
            'user_id' => $owner->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::Ready,
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($intruder)->get(route('imports.show', $session));

        $response->assertForbidden();
    }

    public function test_an_expired_session_is_reported_as_expired(): void
    {
        $session = ImportSession::create([
            'user_id' => User::factory()->create()->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::Ready,
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($session->isExpired());
    }

    public function test_an_inactive_session_is_reported_as_inactive(): void
    {
        $session = ImportSession::create([
            'user_id' => User::factory()->create()->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::Ready,
            'expires_at' => now()->addMinutes(15),
            'last_activity_at' => now()->subMinutes(10),
        ]);

        $this->assertTrue($session->isInactive(5));
        $this->assertFalse($session->isInactive(15));
    }

    public function test_view_token_is_single_use_short_lived_and_verified_by_hash_not_plaintext(): void
    {
        $session = ImportSession::create([
            'user_id' => User::factory()->create()->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::Ready,
            'expires_at' => now()->addMinutes(15),
        ]);

        $raw = $session->issueViewToken(60);

        $this->assertNotSame($raw, $session->view_token_hash);
        $this->assertTrue($session->verifyViewToken($raw));
        $this->assertFalse($session->verifyViewToken('a-completely-wrong-token'));
    }

    public function test_an_expired_view_token_fails_verification(): void
    {
        $session = ImportSession::create([
            'user_id' => User::factory()->create()->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::Ready,
            'expires_at' => now()->addMinutes(15),
        ]);

        $raw = $session->issueViewToken(60);
        $session->forceFill(['view_token_expires_at' => now()->subSecond()])->save();

        $this->assertFalse($session->verifyViewToken($raw));
    }
}
