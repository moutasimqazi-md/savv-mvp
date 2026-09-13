<?php

namespace Savv\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Savv\Enums\ImportBatchSource;
use Savv\Enums\ImportBatchStatus;
use Savv\Enums\Provider;
use Savv\Models\ImportBatch;
use Savv\Models\ImportSession;
use Savv\Models\Subscription;
use Savv\Models\SubscriptionPreview;
use Savv\Models\UserCorrection;

/**
 * Turns confirmed SubscriptionPreview rows into permanent Subscription
 * records, mirroring ImportConfirmationService's approach for orders:
 * merge precedence via MergeService, one DB transaction, deduplicated
 * identity.
 */
final class SubscriptionConfirmationService
{
    public function confirm(ImportSession $session, Collection $selectedPreviews): ImportBatch
    {
        return DB::transaction(function () use ($session, $selectedPreviews) {
            $batch = ImportBatch::create([
                'user_id' => $session->user_id,
                'import_session_id' => $session->id,
                'provider' => $session->provider,
                'status' => ImportBatchStatus::Processing,
                'source' => ImportBatchSource::Runner,
                'parser_version' => $selectedPreviews->first()?->parser_version,
                'submitted_order_count' => $selectedPreviews->count(),
            ]);

            $imported = 0;
            $failed = 0;

            foreach ($selectedPreviews as $preview) {
                try {
                    $this->applySubscription($session, $batch, $preview);
                    $imported++;
                } catch (\Throwable) {
                    $failed++;
                }
            }

            $batch->forceFill([
                'status' => $failed > 0
                    ? ($imported > 0 ? ImportBatchStatus::CompletedWithErrors : ImportBatchStatus::Failed)
                    : ImportBatchStatus::Completed,
                'imported_order_count' => $imported,
                'failed_order_count' => $failed,
                'completed_at' => now(),
            ])->save();

            SubscriptionPreview::whereIn('id', $selectedPreviews->pluck('id'))->delete();

            AuditLogger::record('subscription_import_batch.completed', $session->user_id, ImportBatch::class, $batch->id, [
                'imported' => $imported,
                'failed' => $failed,
            ]);

            return $batch;
        });
    }

    private function applySubscription(ImportSession $session, ImportBatch $batch, SubscriptionPreview $preview): void
    {
        $payload = $preview->normalized_payload;
        $provider = Provider::from($payload['provider']);

        $query = Subscription::query()
            ->where('user_id', $session->user_id)
            ->where('provider', $provider->value);

        $subscription = ! empty($payload['provider_subscription_id'])
            ? (clone $query)->where('provider_subscription_id', $payload['provider_subscription_id'])->first()
            : (clone $query)->where('plan_name', $payload['plan_name'])->first();

        $correctedFields = $subscription
            ? UserCorrection::query()
                ->where('correctable_type', Subscription::class)
                ->where('correctable_id', $subscription->id)
                ->pluck('field')
                ->all()
            : [];

        $incoming = [
            'provider_subscription_id' => $payload['provider_subscription_id'] ?? null,
            'plan_name' => $payload['plan_name'],
            'original_status' => $payload['original_status'] ?? null,
            'normalized_status' => $payload['normalized_status'] ?? 'unknown',
            'price_minor' => $payload['price'] ?? null,
            'currency' => $payload['currency'] ?? 'USD',
            'billing_cycle' => $payload['billing_cycle'] ?? 'unknown',
            'renewal_at' => $payload['renewal_at'] ?? null,
            'started_at' => $payload['started_at'] ?? null,
            'official_billing_url' => $payload['official_billing_url'] ?? null,
        ];

        $merged = MergeService::mergeAttributes($subscription?->getAttributes() ?? [], $incoming, $correctedFields);

        $attributes = array_merge($merged['attributes'], [
            'user_id' => $session->user_id,
            'provider' => $provider->value,
            'provider_connection_id' => $subscription?->provider_connection_id,
            'source_observed_at' => $payload['observed_at'],
            'last_import_batch_id' => $batch->id,
            'parser_version' => $preview->parser_version,
        ]);

        if ($subscription) {
            $subscription->forceFill($attributes)->save();
        } else {
            Subscription::create($attributes);
        }
    }
}
