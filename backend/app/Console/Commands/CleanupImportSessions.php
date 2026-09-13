<?php

namespace Savv\Console\Commands;

use Illuminate\Console\Command;
use Savv\Enums\ImportSessionStatus;
use Savv\Models\ImportPreview;
use Savv\Models\ImportSession;
use Savv\Models\SubscriptionPreview;
use Savv\Services\AuditLogger;
use Savv\Services\ImportSessionService;

class CleanupImportSessions extends Command
{
    protected $signature = 'imports:cleanup';

    protected $description = 'Terminate expired/inactive/orphaned import sessions and purge stale previews.';

    public function handle(ImportSessionService $service): int
    {
        $activeStatuses = array_map(fn ($s) => $s->value, ImportSessionStatus::activeStatuses());

        $expiredOrInactive = ImportSession::query()
            ->whereIn('status', $activeStatuses)
            ->get()
            ->filter(fn (ImportSession $session) => $session->isExpired()
                || $session->isInactive((int) config('savv.import_session.inactivity_timeout_minutes', 5)));

        foreach ($expiredOrInactive as $session) {
            $service->expire($session);
        }

        $this->info("Expired {$expiredOrInactive->count()} import session(s).");

        // Safety net: sessions stuck mid-termination for too long.
        $stuck = ImportSession::query()
            ->where('status', ImportSessionStatus::Terminating->value)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get();

        foreach ($stuck as $session) {
            $session->forceFill(['status' => ImportSessionStatus::Terminated, 'terminated_at' => now()])->save();
            AuditLogger::record('import_session.force_terminated', $session->user_id, ImportSession::class, $session->id);
        }

        // Safety net: any leftover unconfirmed preview rows belonging to a
        // terminal session older than the retention window.
        $purgeAfter = (int) config('savv.retention.expired_import_session_purge_after_minutes', 60);
        $terminalSessionIds = ImportSession::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, ImportSessionStatus::terminalStatuses()))
            ->where('updated_at', '<', now()->subMinutes($purgeAfter))
            ->pluck('id');

        $purgedPreviews = ImportPreview::query()->whereIn('import_session_id', $terminalSessionIds)->delete()
            + SubscriptionPreview::query()->whereIn('import_session_id', $terminalSessionIds)->delete();

        if ($purgedPreviews > 0) {
            $this->info("Purged {$purgedPreviews} stale preview row(s).");
        }

        return self::SUCCESS;
    }
}
