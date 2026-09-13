<?php

namespace Savv\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Savv\Enums\ImportSessionStatus;
use Savv\Enums\Provider;
use Savv\Jobs\ScanImportSession;
use Savv\Jobs\TerminateImportSession;
use Savv\Models\Consent;
use Savv\Models\ImportSession;
use Savv\Models\User;

final class ImportSessionService
{
    public function __construct(private readonly RunnerClient $runner) {}

    public function start(User $user, Provider $provider, Consent $consent): ImportSession
    {
        $existingActive = ImportSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', array_map(fn ($s) => $s->value, ImportSessionStatus::activeStatuses()))
            ->first();

        if ($existingActive) {
            throw new RuntimeException('You already have an active import session. Cancel it before starting another.');
        }

        $lifetimeMinutes = (int) config('savv.import_session.max_lifetime_minutes', 15);

        $session = ImportSession::create([
            'user_id' => $user->id,
            'consent_id' => $consent->id,
            'provider' => $provider,
            'status' => ImportSessionStatus::Requested,
            'expires_at' => now()->addMinutes($lifetimeMinutes),
            'last_activity_at' => now(),
        ]);

        try {
            $result = $this->runner->startSession($session->public_id, $provider->value);
        } catch (\Throwable $e) {
            $session->forceFill([
                'status' => ImportSessionStatus::Failed,
                'safe_error_code' => 'runner_unreachable',
                'terminated_at' => now(),
            ])->save();

            AuditLogger::record('import_session.start_failed', $user->id, ImportSession::class, $session->id);

            throw new RuntimeException('Could not start the temporary browser. Please try again shortly.', previous: $e);
        }

        $session->forceFill([
            'status' => ImportSessionStatus::Starting,
            'runner_process_ref' => $result['processRef'] ?? null,
            'display_ref' => $result['displayRef'] ?? null,
        ])->save();

        AuditLogger::record('import_session.started', $user->id, ImportSession::class, $session->id, [
            'provider' => $provider->value,
        ]);

        return $session;
    }

    public function refreshStatus(ImportSession $session): ImportSession
    {
        if ($session->status->isTerminal()) {
            return $session;
        }

        if ($session->isExpired() || $session->isInactive((int) config('savv.import_session.inactivity_timeout_minutes', 5))) {
            return $this->expire($session);
        }

        try {
            $result = $this->runner->getSessionStatus($session->public_id);
        } catch (\Throwable) {
            return $session;
        }

        // The runner's status vocabulary ('starting'/'ready'/'scanning'/
        // 'failed'/'not_found') describes its own process lifecycle, not
        // Laravel's richer 14-state business state machine - several
        // strings are spelled the same as ImportSessionStatus cases but
        // mean something narrower (e.g. the runner's internal 'ready'
        // stays 'ready' right after a scan completes too - it does not
        // mean our formal Ready state specifically). Laravel's own jobs
        // (ScanImportSession, ConfirmImportSession, TerminateImportSession)
        // are the sole authority for advancing status once scanning starts.
        // Only two narrow, unambiguous syncs happen here:
        $reportedStatus = $result['status'] ?? null;

        if ($reportedStatus === 'not_found' && ! $session->status->isTerminal()) {
            // The process is simply gone (e.g. it hit its own lifetime cap).
            $session->forceFill([
                'status' => ImportSessionStatus::Failed,
                'safe_error_code' => 'runner_process_lost',
            ])->save();
        } elseif ($reportedStatus === 'ready' && $session->status === ImportSessionStatus::Starting) {
            // The browser finished launching and navigating - purely
            // cosmetic (the "starting" status already permits scanning),
            // but the UI should reflect that it's ready to log into.
            $session->forceFill(['status' => ImportSessionStatus::Ready])->save();
        } elseif ($reportedStatus === 'failed' && $session->status === ImportSessionStatus::Starting) {
            // createSession()'s own navigation attempt failed outright.
            $session->forceFill([
                'status' => ImportSessionStatus::Failed,
                'safe_error_code' => 'runner_unreachable',
            ])->save();
        }

        $session->touchActivity();

        return $session->refresh();
    }

    public function requestScan(ImportSession $session): ImportSession
    {
        $this->assertOwnedAndActive($session);

        $session->forceFill(['status' => ImportSessionStatus::Scanning])->save();
        $session->touchActivity();

        ScanImportSession::dispatch($session->id)->onQueue('imports');

        return $session;
    }

    public function cancel(ImportSession $session): ImportSession
    {
        return DB::transaction(function () use ($session) {
            $session->previews()->delete();
            $session->subscriptionPreviews()->delete();
            $session->forceFill(['status' => ImportSessionStatus::Terminating])->save();

            TerminateImportSession::dispatch($session->id, 'cancelled');

            AuditLogger::record('import_session.cancelled', $session->user_id, ImportSession::class, $session->id);

            return $session;
        });
    }

    public function expire(ImportSession $session): ImportSession
    {
        return DB::transaction(function () use ($session) {
            $session->previews()->delete();
            $session->subscriptionPreviews()->delete();
            $session->forceFill(['status' => ImportSessionStatus::Terminating])->save();

            TerminateImportSession::dispatch($session->id, 'expired');

            AuditLogger::record('import_session.expired', $session->user_id, ImportSession::class, $session->id);

            return $session;
        });
    }

    private function assertOwnedAndActive(ImportSession $session): void
    {
        if (! $session->status->isActive()) {
            throw new RuntimeException('This import session is no longer active.');
        }

        if ($session->isExpired()) {
            throw new RuntimeException('This import session has expired.');
        }
    }
}
