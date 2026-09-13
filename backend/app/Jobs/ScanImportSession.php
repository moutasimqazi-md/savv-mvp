<?php

namespace Savv\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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

            // The runner reports a structural problem (unsupported page
            // layout, disallowed host) as a 200 response with an `error`
            // key, not an HTTP error - it already safely produced zero
            // orders rather than guessing. Surface it as a real failure
            // with its specific safe error code, instead of silently
            // showing the user an empty "no orders found" preview.
            if (isset($result['error'])) {
                $session->forceFill([
                    'status' => ImportSessionStatus::Failed,
                    'safe_error_code' => $result['error'],
                ])->save();

                AuditLogger::record('import_session.scan_unsupported', $session->user_id, ImportSession::class, $session->id, [
                    'reason' => $result['error'],
                ]);

                return;
            }

            $validated = ImportPreviewValidator::validateScanResult($result['orders'] ?? [], Provider::from($session->provider->value));
        } catch (\Throwable $e) {
            // The technical message is safe to log (no order/personal data
            // ever passes through this exception) but must never reach the
            // redacted audit trail or the end user - see safe_error_code.
            Log::error('Scan failed for import session '.$session->public_id.': '.$e->getMessage());

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
