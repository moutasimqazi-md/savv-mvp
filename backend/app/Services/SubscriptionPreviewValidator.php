<?php

namespace Savv\Services;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;
use Savv\Enums\BillingCycle;
use Savv\Enums\Provider;
use Savv\Support\ForbiddenFieldGuard;
use Savv\Support\MarketplaceUrlValidator;
use Savv\Support\Money;

/**
 * Validates and sanitizes the normalized subscription JSON a runner scan
 * reports (see Savv\Enums\ProviderKind::Subscription providers, e.g.
 * Claude) before it is stored as a SubscriptionPreview. Mirrors
 * ImportPreviewValidator's approach for orders: unknown top-level fields
 * are rejected, strings are cleaned, URLs are host-checked, and forbidden
 * (credential/session-shaped) keys are rejected outright.
 */
final class SubscriptionPreviewValidator
{
    private const array ALLOWED_KEYS = [
        'provider_subscription_id', 'plan_name', 'original_status', 'price',
        'currency', 'billing_cycle', 'renewal_at', 'started_at', 'official_billing_url', 'observed_at',
    ];

    private const int MAX_SUBSCRIPTIONS_PER_SCAN = 25;

    /**
     * @return array{subscriptions: array<int, array<string, mixed>>, warnings: array<int, string>}
     *
     * @throws InvalidArgumentException on anything structurally unsafe
     */
    public static function validateScanResult(array $rawSubscriptions, Provider $provider): array
    {
        ForbiddenFieldGuard::assertSafe($rawSubscriptions);

        if (count($rawSubscriptions) > self::MAX_SUBSCRIPTIONS_PER_SCAN) {
            throw new InvalidArgumentException('Scan reported more than the allowed '.self::MAX_SUBSCRIPTIONS_PER_SCAN.' subscriptions.');
        }

        $subscriptions = [];
        $warnings = [];

        foreach ($rawSubscriptions as $index => $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException("Subscription at index {$index} is not an object.");
            }

            $unknown = array_diff(array_keys($raw), self::ALLOWED_KEYS);
            if (! empty($unknown)) {
                $keys = implode(', ', $unknown);
                throw new InvalidArgumentException("subscription[{$index}] has unknown field(s): {$keys}.");
            }

            [$subscription, $subWarnings] = self::validateOne($raw, $provider, $index);
            $subscriptions[] = $subscription;
            array_push($warnings, ...$subWarnings);
        }

        return ['subscriptions' => $subscriptions, 'warnings' => $warnings];
    }

    private static function validateOne(array $raw, Provider $provider, int $index): array
    {
        $warnings = [];
        $maxField = (int) config('savv.imports.max_field_length', 500);

        $planName = TextSanitizer::clean($raw['plan_name'] ?? null, $maxField);
        if (TextSanitizer::isEmpty($planName)) {
            throw new InvalidArgumentException("Subscription at index {$index} is missing plan_name.");
        }

        $currency = strtoupper(TextSanitizer::clean($raw['currency'] ?? 'USD', 3) ?? 'USD');
        $originalStatus = TextSanitizer::clean($raw['original_status'] ?? null, $maxField);

        $observedAt = self::parseDateOrNull($raw['observed_at'] ?? null);
        if ($observedAt === null) {
            $warnings[] = "Subscription {$planName}: missing observation time, using current time.";
            $observedAt = Carbon::now('UTC');
        }

        $price = self::parseMoneyOrNull($raw['price'] ?? null, $currency);
        if (($raw['price'] ?? null) !== null && $price === null) {
            $warnings[] = "Subscription {$planName}: could not parse price amount, left blank.";
        }

        $billingCycle = BillingCycle::tryFrom(strtolower((string) ($raw['billing_cycle'] ?? '')))?->value
            ?? BillingCycle::Unknown->value;

        return [[
            'provider' => $provider->value,
            'provider_subscription_id' => TextSanitizer::clean($raw['provider_subscription_id'] ?? null, $maxField),
            'plan_name' => $planName,
            'original_status' => $originalStatus,
            'normalized_status' => SubscriptionStatusNormalizer::normalize($originalStatus)->value,
            'price' => $price,
            'currency' => $currency,
            'billing_cycle' => $billingCycle,
            'renewal_at' => self::parseDateOrNull($raw['renewal_at'] ?? null)?->toIso8601String(),
            'started_at' => self::parseDateOrNull($raw['started_at'] ?? null)?->toIso8601String(),
            'official_billing_url' => MarketplaceUrlValidator::sanitizeOrNull($raw['official_billing_url'] ?? null),
            'observed_at' => $observedAt->toIso8601String(),
        ], $warnings];
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
