<?php

namespace Savv\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Savv\Enums\ImportSessionStatus;
use Savv\Enums\ProviderKind;
use Savv\Models\ImportPreview;
use Savv\Models\ImportSession;
use Savv\Models\SubscriptionPreview;
use Savv\Services\ImportConfirmationService;
use Savv\Services\SubscriptionConfirmationService;

class ConfirmImportSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [5, 20, 60];

    /**
     * @param  array<int, int>  $selectedPreviewIds
     */
    public function __construct(
        private readonly int $importSessionId,
        private readonly array $selectedPreviewIds,
    ) {}

    public function handle(ImportConfirmationService $orderConfirmation, SubscriptionConfirmationService $subscriptionConfirmation): void
    {
        $session = ImportSession::find($this->importSessionId);

        if (! $session || $session->status !== ImportSessionStatus::Importing) {
            return;
        }

        if ($session->provider->kind() === ProviderKind::Subscription) {
            $selected = SubscriptionPreview::query()
                ->where('import_session_id', $session->id)
                ->whereIn('id', $this->selectedPreviewIds)
                ->get();

            $subscriptionConfirmation->confirm($session, $selected);
        } else {
            $selected = ImportPreview::query()
                ->where('import_session_id', $session->id)
                ->whereIn('id', $this->selectedPreviewIds)
                ->get();

            $orderConfirmation->confirm($session, $selected);
        }

        $session->forceFill(['status' => ImportSessionStatus::Completed])->save();

        TerminateImportSession::dispatch($session->id, 'completed');
        RecalculateDashboardSummary::dispatch($session->user_id);
    }

    public function failed(\Throwable $e): void
    {
        $session = ImportSession::find($this->importSessionId);
        $session?->forceFill([
            'status' => ImportSessionStatus::Failed,
            'safe_error_code' => 'import_failed',
        ])->save();

        TerminateImportSession::dispatch($this->importSessionId, 'failed');
    }
}
