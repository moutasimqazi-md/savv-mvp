<?php

namespace Savv\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Savv\Enums\ImportBatchSource;
use Savv\Enums\ImportBatchStatus;
use Savv\Enums\NormalizedStatus;
use Savv\Enums\Provider;
use Savv\Models\ImportBatch;
use Savv\Models\ImportPreview;
use Savv\Models\ImportSession;
use Savv\Models\Invoice;
use Savv\Models\Order;
use Savv\Models\OrderItem;
use Savv\Models\OrderReturn;
use Savv\Models\Refund;
use Savv\Models\Shipment;
use Savv\Models\ShipmentEvent;
use Savv\Models\UserCorrection;

/**
 * Turns confirmed ImportPreview rows into permanent Order/Item/Shipment/
 * Return/Refund/Invoice records inside a single transaction, applying
 * MergeService precedence against any existing record and user corrections.
 */
final class ImportConfirmationService
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
                    $this->applyOrder($session, $batch, $preview);
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

            ImportPreview::whereIn('id', $selectedPreviews->pluck('id'))->delete();

            AuditLogger::record('import_batch.completed', $session->user_id, ImportBatch::class, $batch->id, [
                'imported' => $imported,
                'failed' => $failed,
            ]);

            return $batch;
        });
    }

    private function applyOrder(ImportSession $session, ImportBatch $batch, ImportPreview $preview): void
    {
        $payload = $preview->normalized_payload;
        $provider = Provider::from($payload['provider']);

        $order = Order::query()
            ->where('user_id', $session->user_id)
            ->where('provider', $provider->value)
            ->where('provider_order_id', $payload['provider_order_id'])
            ->first();

        $correctedFields = $order
            ? UserCorrection::query()
                ->where('correctable_type', Order::class)
                ->where('correctable_id', $order->id)
                ->pluck('field')
                ->all()
            : [];

        $incoming = [
            'ordered_at' => $payload['order_date'] ?? null,
            'original_status' => $payload['original_status'] ?? null,
            'normalized_status' => $payload['normalized_status'] ?? NormalizedStatus::Unknown->value,
            'currency' => $payload['currency'] ?? 'INR',
            'subtotal_minor' => $payload['subtotal'] ?? null,
            'shipping_fee_minor' => $payload['delivery_fee'] ?? null,
            'discount_minor' => $payload['discount'] ?? null,
            'tax_minor' => $payload['tax'] ?? null,
            'total_minor' => $payload['total'] ?? null,
            'expected_delivery_at' => $payload['expected_delivery_at'] ?? null,
            'delivered_at' => $payload['delivered_at'] ?? null,
            'official_order_url' => $payload['official_order_url'] ?? null,
        ];

        $merged = MergeService::mergeAttributes($order?->getAttributes() ?? [], $incoming, $correctedFields);

        $attributes = array_merge($merged['attributes'], [
            'user_id' => $session->user_id,
            'provider' => $provider->value,
            'provider_order_id' => $payload['provider_order_id'],
            'provider_connection_id' => $order?->provider_connection_id,
            'source_observed_at' => $payload['observed_at'],
            'last_import_batch_id' => $batch->id,
            'parser_version' => $preview->parser_version,
        ]);

        if ($order) {
            $order->forceFill($attributes)->save();
        } else {
            $order = Order::create($attributes);
        }

        foreach ($payload['items'] ?? [] as $itemPayload) {
            $this->applyItem($order, $itemPayload);
        }

        foreach ($payload['shipments'] ?? [] as $shipmentPayload) {
            $this->applyShipment($order, $shipmentPayload);
        }

        foreach ($payload['returns'] ?? [] as $returnPayload) {
            $this->applyReturn($order, $returnPayload);
        }

        foreach ($payload['refunds'] ?? [] as $refundPayload) {
            $this->applyRefund($order, $refundPayload);
        }

        foreach ($payload['invoices'] ?? [] as $invoicePayload) {
            $this->applyInvoice($order, $invoicePayload);
        }
    }

    private function applyItem(Order $order, array $payload): void
    {
        $query = OrderItem::query()->where('order_id', $order->id);

        if (! empty($payload['provider_item_id'])) {
            $item = (clone $query)->where('provider_item_id', $payload['provider_item_id'])->first();
        } else {
            $fingerprint = DeduplicationService::itemFingerprint(
                $payload['title'],
                $payload['variant'] ?? null,
                $payload['quantity'],
                $payload['line_total_minor'] ?? null,
            );
            $payload['fingerprint'] = $fingerprint;
            $item = (clone $query)->where('fingerprint', $fingerprint)->first();
        }

        $merged = MergeService::mergeAttributes($item?->getAttributes() ?? [], $payload);
        $attributes = array_merge($merged['attributes'], ['order_id' => $order->id]);

        if ($item) {
            $item->forceFill($attributes)->save();
        } else {
            OrderItem::create($attributes);
        }
    }

    private function applyShipment(Order $order, array $payload): void
    {
        $shipment = Shipment::query()
            ->where('order_id', $order->id)
            ->when(! empty($payload['tracking_number']), fn ($q) => $q->where('tracking_number', $payload['tracking_number']))
            ->first();

        $merged = MergeService::mergeAttributes($shipment?->getAttributes() ?? [], $payload);
        $attributes = array_merge($merged['attributes'], [
            'order_id' => $order->id,
            'last_observed_at' => now(),
        ]);

        if ($shipment) {
            $shipment->forceFill($attributes)->save();
        } else {
            $shipment = Shipment::create($attributes);
        }

        $hash = ShipmentEvent::hashFor($shipment->id, $payload['normalized_status'] ?? null, $payload['delivered_at'] ?? $payload['expected_delivery_at'] ?? null, $payload['original_status'] ?? null);

        ShipmentEvent::firstOrCreate(
            ['shipment_id' => $shipment->id, 'event_hash' => $hash],
            [
                'occurred_at' => $payload['delivered_at'] ?? $payload['expected_delivery_at'] ?? now(),
                'original_status' => $payload['original_status'] ?? null,
                'normalized_status' => $payload['normalized_status'] ?? null,
                'description' => null,
                'source' => 'runner',
                'created_at' => now(),
            ],
        );
    }

    private function applyReturn(Order $order, array $payload): void
    {
        $query = OrderReturn::query()->where('order_id', $order->id);
        $return = ! empty($payload['provider_return_id'])
            ? (clone $query)->where('provider_return_id', $payload['provider_return_id'])->first()
            : (clone $query)->first();

        $merged = MergeService::mergeAttributes($return?->getAttributes() ?? [], $payload);
        $attributes = array_merge($merged['attributes'], ['order_id' => $order->id]);

        if ($return) {
            $return->forceFill($attributes)->save();
        } else {
            OrderReturn::create($attributes);
        }
    }

    private function applyRefund(Order $order, array $payload): void
    {
        $exists = Refund::query()
            ->where('order_id', $order->id)
            ->where('amount_minor', $payload['amount_minor'])
            ->where('initiated_at', $payload['initiated_at'] ?? null)
            ->exists();

        if ($exists) {
            return;
        }

        Refund::create(array_merge($payload, ['order_id' => $order->id]));
    }

    private function applyInvoice(Order $order, array $payload): void
    {
        $exists = ! empty($payload['invoice_number']) && Invoice::query()
            ->where('order_id', $order->id)
            ->where('invoice_number', $payload['invoice_number'])
            ->exists();

        if ($exists) {
            return;
        }

        Invoice::create(array_merge($payload, ['order_id' => $order->id]));
    }
}
