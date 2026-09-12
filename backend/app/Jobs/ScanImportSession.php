<?php

namespace Savv\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Savv\Enums\ImportSessionStatus;
use Savv\Enums\Provider;
use Savv\Models\ImportSession;
use Savv\Services\AuditLogger;
use Savv\Services\ImportPreviewValidator;
use Savv\Services\RunnerClient;

class ScanImportSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public array $backoff = [5, 20];

    public function __construct(private readonly int $importSessionId) {}

    public function handle(RunnerClient $runner): void
    {
        $session = ImportSession::find($this->importSessionId);

        if (! $session || $session->status !== ImportSessionStatus::Scanning) {
            return;
        }

        try {
            $result = $runner->scan($session->public_id);
            $validated = ImportPreviewValidator::validateScanResult($result['orders'] ?? [], Provider::from($session->provider->value));
        } catch (\Throwable) {
            $session->forceFill([
                'status' => ImportSessionStatus::Failed,
                'safe_error_code' => 'scan_failed',
            ])->save();

            AuditLogger::record('import_session.scan_failed', $session->user_id, ImportSession::class, $session->id);

            return;
        }

        $session->previews()->delete();

        foreach ($validated['orders'] as $order) {
            $session->previews()->create([
                'provider_order_id' => $order['provider_order_id'],
                'selected' => true,
                'parser_version' => $result['parserVersion'] ?? null,
                'observed_at' => $order['observed_at'],
                'normalized_payload' => $order,
                'field_warnings' => $validated['warnings'],
            ]);
        }

        $session->forceFill([
            'status' => ImportSessionStatus::PreviewReady,
        ])->save();
        $session->touchActivity();

        AuditLogger::record('import_session.scan_completed', $session->user_id, ImportSession::class, $session->id, [
            'order_count' => count($validated['orders']),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $session = ImportSession::find($this->importSessionId);
        $session?->forceFill([
            'status' => ImportSessionStatus::Failed,
            'safe_error_code' => 'scan_failed',
        ])->save();
    }
}
