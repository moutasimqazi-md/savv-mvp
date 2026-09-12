<?php

namespace Savv\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Savv\Enums\ImportSessionStatus;
use Savv\Models\ImportSession;
use Savv\Services\AuditLogger;
use Savv\Services\RunnerClient;

/**
 * Best-effort, idempotent shutdown of a runner-side Chromium process.
 * Always safe to retry or call on an already-terminated session.
 */
class TerminateImportSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public array $backoff = [2, 5, 15, 30, 60];

    public function __construct(
        private readonly int $importSessionId,
        private readonly string $reason,
    ) {}

    public function handle(RunnerClient $runner): void
    {
        $session = ImportSession::find($this->importSessionId);

        if (! $session) {
            return;
        }

        try {
            $runner->stop($session->public_id);
        } catch (\Throwable) {
            // Best-effort: the runner may have already cleaned itself up
            // (e.g. it hit its own max-lifetime timeout).
        }

        $terminalStatus = match ($this->reason) {
            'cancelled' => ImportSessionStatus::Cancelled,
            'expired' => ImportSessionStatus::Expired,
            'failed' => ImportSessionStatus::Failed,
            default => ImportSessionStatus::Terminated,
        };

        $session->forceFill([
            'status' => $terminalStatus,
            'terminated_at' => now(),
        ])->save();

        AuditLogger::record('import_session.terminated', $session->user_id, ImportSession::class, $session->id, [
            'reason' => $this->reason,
        ]);
    }
}
