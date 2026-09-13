<?php

namespace Savv\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Savv\Enums\ImportSessionStatus;
use Savv\Enums\Provider;
use Savv\Jobs\TerminateImportSession;
use Savv\Models\ImportSession;
use Savv\Models\User;
use Savv\Tests\TestCase;

class CleanupImportSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_expires_a_session_past_its_max_lifetime(): void
    {
        Bus::fake();

        $session = ImportSession::create([
            'user_id' => User::factory()->create()->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::AwaitingLogin,
            'expires_at' => now()->subMinute(),
            'last_activity_at' => now()->subMinute(),
        ]);

        $this->artisan('imports:cleanup')->assertSuccessful();

        Bus::assertDispatched(TerminateImportSession::class, fn ($job) => $this->jobTargets($job, $session->id) && $this->jobReason($job) === 'expired');
    }

    public function test_cleanup_expires_a_session_inactive_past_the_timeout(): void
    {
        Bus::fake();

        config(['savv.import_session.inactivity_timeout_minutes' => 5]);

        $session = ImportSession::create([
            'user_id' => User::factory()->create()->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::ReadyToScan,
            'expires_at' => now()->addMinutes(10),
            'last_activity_at' => now()->subMinutes(6),
        ]);

        $this->artisan('imports:cleanup')->assertSuccessful();

        Bus::assertDispatched(TerminateImportSession::class, fn ($job) => $this->jobTargets($job, $session->id));
    }

    public function test_cleanup_leaves_healthy_active_sessions_alone(): void
    {
        Bus::fake();

        ImportSession::create([
            'user_id' => User::factory()->create()->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::ReadyToScan,
            'expires_at' => now()->addMinutes(10),
            'last_activity_at' => now(),
        ]);

        $this->artisan('imports:cleanup')->assertSuccessful();

        Bus::assertNotDispatched(TerminateImportSession::class);
    }

    public function test_cleanup_force_terminates_a_session_stuck_terminating(): void
    {
        $session = ImportSession::create([
            'user_id' => User::factory()->create()->id,
            'provider' => Provider::AmazonIn,
            'status' => ImportSessionStatus::Terminating,
            'expires_at' => now()->addMinutes(10),
        ]);
        DB::table('import_sessions')->where('id', $session->id)->update(['updated_at' => now()->subMinutes(20)]);

        $this->artisan('imports:cleanup')->assertSuccessful();

        $this->assertSame(ImportSessionStatus::Terminated, $session->fresh()->status);
    }

    private function jobTargets($job, int $sessionId): bool
    {
        $ref = new \ReflectionObject($job);
        $prop = $ref->getProperty('importSessionId');
        $prop->setAccessible(true);

        return $prop->getValue($job) === $sessionId;
    }

    private function jobReason($job): string
    {
        $ref = new \ReflectionObject($job);
        $prop = $ref->getProperty('reason');
        $prop->setAccessible(true);

        return $prop->getValue($job);
    }
}
