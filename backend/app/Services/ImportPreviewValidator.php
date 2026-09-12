<?php

namespace Savv\Services;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;
use Savv\Enums\Provider;
use Savv\Support\ForbiddenFieldGuard;
use Savv\Support\MarketplaceUrlValidator;
use Savv\Support\Money;

/**
 * Validates and sanitizes the normalized JSON a runner scan reports before
 * it is stored as an ImportPreview. Every extracted value is treated as
 * untrusted: unknown top-level fields are rejected, strings are cleaned,
 * URLs are host-checked, dates/amounts are strictly parsed, and hard limits
 * on order/item counts and field lengths are enforced.
 */
final class ImportPreviewValidator
{
    private const array ALLOWED_ORDER_KEYS = [
        'provider_order_id', 'order_date', 'original_status', 'currency',
        'subtotal', 'delivery_fee', 'discount', 'tax', 'total',
        'expected_delivery_at', 'delivered_at', 'official_order_url', 'observed_at',
        'items', 'shipments', 'returns', 'refunds', 'invoices',
    ];

    private const array ALLOWED_ITEM_KEYS = [
        'provider_item_id', 'title', 'variant', 'quantity', 'unit_price',
        'line_total', 'product_image_url', 'official_product_url',
    ];

    private const array ALLOWED_SHIPMENT_KEYS = [
        'carrier', 'tracking_number', 'original_status', 'expected_delivery_at', 'delivered_at',
    ];

    private const array ALLOWED_RETURN_KEYS = [
        'provider_return_id', 'original_status', 'requested_at', 'approved_at',
        'pickup_scheduled_at', 'picked_up_at', 'completed_at',
    ];

    private const array ALLOWED_REFUND_KEYS = [
        'amount', 'currency', 'original_status', 'initiated_at', 'completed_at',
    ];

    private const array ALLOWED_INVOICE_KEYS = [
        'invoice_number', 'invoice_date', 'official_invoice_url',
    ];

    /**
     * @return array{orders: array<int, array<string, mixed>>, warnings: array<int, string>}
     *
     * @throws InvalidArgumentException on anything structurally unsafe
     */
    public static function validateScanResult(array $rawOrders, Provider $provider): array
    {
        ForbiddenFieldGuard::assertSafe($rawOrders);

        $maxOrders = (int) config('savv.imports.max_orders_per_scan', 100);

        if (count($rawOrders) > $maxOrders) {
            throw new InvalidArgumentException("Scan reported more than the allowed {$maxOrders} orders.");
        }

        $orders = [];
        $warnings = [];

        foreach ($rawOrders as $index => $rawOrder) {
            if (! is_array($rawOrder)) {
                throw new InvalidArgumentException("Order at index {$index} is not an object.");
            }

            self::rejectUnknownKeys($rawOrder, self::ALLOWED_ORDER_KEYS, "order[{$index}]");

            [$order, $orderWarnings] = self::validateOrder($rawOrder, $provider, $index);
            $orders[] = $order;
            array_push($warnings, ...$orderWarnings);
        }

        return ['orders' => $orders, 'warnings' => $warnings];
    }

    private static function validateOrder(array $raw, Provider $provider, int $index): array
    {
        $warnings = [];
        $maxField = (int) config('savv.imports.max_field_length', 500);

        $providerOrderId = TextSanitizer::clean($raw['provider_order_id'] ?? null, $maxField);
        if (TextSanitizer::isEmpty($providerOrderId)) {
            throw new InvalidArgumentException("Order at index {$index} is missing provider_order_id.");
        }

        $originalStatus = TextSanitizer::clean($raw['original_status'] ?? null, $maxField);
        $currency = strtoupper(TextSanitizer::clean($raw['currency'] ?? 'INR', 3) ?? 'INR');

        $observedAt = self::parseDateOrNull($raw['observed_at'] ?? null);
        if ($observedAt === null) {
            $warnings[] = "Order {$providerOrderId}: missing observation time, using current time.";
            $observedAt = Carbon::now('UTC');
        }

        $amounts = [];
        foreach (['subtotal', 'delivery_fee', 'discount', 'tax', 'total'] as $field) {
            $amounts[$field] = self::parseMoneyOrNull($raw[$field] ?? null, $currency);
            if (($raw[$field] ?? null) !== null && $amounts[$field] === null) {
                $warnings[] = "Order {$providerOrderId}: could not parse {$field} amount, left blank.";
            }
        }

        $items = [];
        foreach (($raw['items'] ?? []) as $itemIndex => $rawItem) {
            self::rejectUnknownKeys($rawItem, self::ALLOWED_ITEM_KEYS, "order[{$index}].items[{$itemIndex}]");
            [$item, $itemWarnings] = self::validateItem($rawItem, $providerOrderId, $itemIndex, $maxField, $currency);
            $items[] = $item;
            array_push($warnings, ...$itemWarnings);
        }

        $shipments = [];
        foreach (($raw['shipments'] ?? []) as $shipmentIndex => $rawShipment) {
            self::rejectUnknownKeys($rawShipment, self::ALLOWED_SHIPMENT_KEYS, "order[{$index}].shipments[{$shipmentIndex}]");
            $shipments[] = self::validateShipment($rawShipment, $maxField);
        }

        $returns = [];
        foreach (($raw['returns'] ?? []) as $returnIndex => $rawReturn) {
            self::rejectUnknownKeys($rawReturn, self::ALLOWED_RETURN_KEYS, "order[{$index}].returns[{$returnIndex}]");
            $returns[] = self::validateReturn($rawReturn, $maxField);
        }

        $refunds = [];
        foreach (($raw['refunds'] ?? []) as $refundIndex => $rawRefund) {
            self::rejectUnknownKeys($rawRefund, self::ALLOWED_REFUND_KEYS, "order[{$index}].refunds[{$refundIndex}]");
            [$refund, $refundWarnings] = self::validateRefund($rawRefund, $providerOrderId, $maxField, $currency);
            if ($refund !== null) {
                $refunds[] = $refund;
            }
            array_push($warnings, ...$refundWarnings);
        }

        $invoices = [];
        foreach (($raw['invoices'] ?? []) as $invoiceIndex => $rawInvoice) {
            self::rejectUnknownKeys($rawInvoice, self::ALLOWED_INVOICE_KEYS, "order[{$index}].invoices[{$invoiceIndex}]");
            $invoices[] = self::validateInvoice($rawInvoice, $maxField);
        }

        if (empty($items)) {
            $warnings[] = "Order {$providerOrderId}: no items were extracted.";
        }

        $order = [
            'provider' => $provider->value,
            'provider_order_id' => $providerOrderId,
            'order_date' => self::parseDateOrNull($raw['order_date'] ?? null)?->toIso8601String(),
            'original_status' => $originalStatus,
            'normalized_status' => StatusNormalizer::normalize($originalStatus)->value,
            'currency' => $currency,
            ...$amounts,
            'expected_delivery_at' => self::parseDateOrNull($raw['expected_delivery_at'] ?? null)?->toIso8601String(),
            'delivered_at' => self::parseDateOrNull($raw['delivered_at'] ?? null)?->toIso8601String(),
            'official_order_url' => MarketplaceUrlValidator::sanitizeOrNull($raw['official_order_url'] ?? null),
            'observed_at' => $observedAt->toIso8601String(),
            'items' => $items,
            'shipments' => $shipments,
            'returns' => $returns,
            'refunds' => $refunds,
            'invoices' => $invoices,
        ];

        return [$order, $warnings];
    }

    private static function validateItem(array $raw, string $providerOrderId, int $index, int $maxField, string $currency): array
    {
        $warnings = [];
        $title = TextSanitizer::clean($raw['title'] ?? null, $maxField);

        if (TextSanitizer::isEmpty($title)) {
            throw new InvalidArgumentException("Order {$providerOrderId} item {$index} is missing a title.");
        }

        $quantity = max(1, (int) ($raw['quantity'] ?? 1));
        $unitPrice = self::parseMoneyOrNull($raw['unit_price'] ?? null, $currency);
        $lineTotal = self::parseMoneyOrNull($raw['line_total'] ?? null, $currency);

        if (($raw['unit_price'] ?? null) !== null && $unitPrice === null) {
            $warnings[] = "Order {$providerOrderId} item \"{$title}\": could not parse unit price.";
        }

        return [[
            'provider_item_id' => TextSanitizer::clean($raw['provider_item_id'] ?? null, $maxField),
            'title' => $title,
            'variant' => TextSanitizer::clean($raw['variant'] ?? null, $maxField),
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice,
            'line_total_minor' => $lineTotal,
            'product_image_url' => MarketplaceUrlValidator::sanitizeOrNull($raw['product_image_url'] ?? null),
            'official_product_url' => MarketplaceUrlValidator::sanitizeOrNull($raw['official_product_url'] ?? null),
        ], $warnings];
    }

    private static function validateShipment(array $raw, int $maxField): array
    {
        $originalStatus = TextSanitizer::clean($raw['original_status'] ?? null, $maxField);

        return [
            'carrier' => TextSanitizer::clean($raw['carrier'] ?? null, $maxField),
            'tracking_number' => TextSanitizer::clean($raw['tracking_number'] ?? null, $maxField),
            'original_status' => $originalStatus,
            'normalized_status' => StatusNormalizer::normalize($originalStatus)->value,
            'expected_delivery_at' => self::parseDateOrNull($raw['expected_delivery_at'] ?? null)?->toIso8601String(),
            'delivered_at' => self::parseDateOrNull($raw['delivered_at'] ?? null)?->toIso8601String(),
        ];
    }

    private static function validateReturn(array $raw, int $maxField): array
    {
        $originalStatus = TextSanitizer::clean($raw['original_status'] ?? null, $maxField);

        return [
            'provider_return_id' => TextSanitizer::clean($raw['provider_return_id'] ?? null, $maxField),
            'original_status' => $originalStatus,
            'normalized_status' => StatusNormalizer::normalize($originalStatus)->value,
            'requested_at' => self::parseDateOrNull($raw['requested_at'] ?? null)?->toIso8601String(),
            'approved_at' => self::parseDateOrNull($raw['approved_at'] ?? null)?->toIso8601String(),
            'pickup_scheduled_at' => self::parseDateOrNull($raw['pickup_scheduled_at'] ?? null)?->toIso8601String(),
            'picked_up_at' => self::parseDateOrNull($raw['picked_up_at'] ?? null)?->toIso8601String(),
            'completed_at' => self::parseDateOrNull($raw['completed_at'] ?? null)?->toIso8601String(),
        ];
    }

    private static function validateRefund(array $raw, string $providerOrderId, int $maxField, string $currency): array
    {
        $warnings = [];
        $amount = self::parseMoneyOrNull($raw['amount'] ?? null, $currency);

        if ($amount === null) {
            $warnings[] = "Order {$providerOrderId}: a refund entry was skipped because its amount could not be parsed.";

            return [null, $warnings];
        }

        $originalStatus = TextSanitizer::clean($raw['original_status'] ?? null, $maxField);

        return [[
            'amount_minor' => $amount,
            'currency' => strtoupper(TextSanitizer::clean($raw['currency'] ?? $currency, 3) ?? $currency),
            'original_status' => $originalStatus,
            'normalized_status' => StatusNormalizer::normalize($originalStatus)->value,
            'initiated_at' => self::parseDateOrNull($raw['initiated_at'] ?? null)?->toIso8601String(),
            'completed_at' => self::parseDateOrNull($raw['completed_at'] ?? null)?->toIso8601String(),
        ], $warnings];
    }

    private static function validateInvoice(array $raw, int $maxField): array
    {
        return [
            'invoice_number' => TextSanitizer::clean($raw['invoice_number'] ?? null, $maxField),
            'invoice_date' => self::parseDateOrNull($raw['invoice_date'] ?? null)?->toDateString(),
            'official_invoice_url' => MarketplaceUrlValidator::sanitizeOrNull($raw['official_invoice_url'] ?? null),
        ];
    }

    private static function rejectUnknownKeys(mixed $value, array $allowed, string $context): void
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("{$context} is not an object.");
        }

        $unknown = array_diff(array_keys($value), $allowed);

        if (! empty($unknown)) {
            $keys = implode(', ', $unknown);
            throw new InvalidArgumentException("{$context} has unknown field(s): {$keys}.");
        }
    }

    private static function parseDateOrNull(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (InvalidFormatException) {
            return null;
        }
    }

    private static function parseMoneyOrNull(mixed $value, string $currency): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) && ! is_float($value)) {
            return null;
        }

        try {
            return Money::fromDecimalString((string) $value, $currency)->minorUnits;
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
