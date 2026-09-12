<?php

namespace Savv\Services;

/**
 * Field-level merge precedence used whenever a scan/manual/CSV import
 * updates an existing record:
 *
 *   1. Fields with a user correction are never overwritten.
 *   2. Empty/null incoming values never overwrite a populated value.
 *   3. Otherwise the newly observed value wins (the caller is responsible
 *      for only calling this with the most recently observed scan).
 *
 * Financial totals are never recalculated here - they are stored exactly as
 * observed/entered.
 */
final class MergeService
{
    /**
     * @param  array<string, mixed>  $current  existing model attributes, keyed by field
     * @param  array<string, mixed>  $incoming  newly observed values, keyed by field
     * @param  array<int, string>  $correctedFields  fields the user has manually corrected
     * @return array{attributes: array<string, mixed>, conflicts: array<int, string>}
     */
    public static function mergeAttributes(array $current, array $incoming, array $correctedFields = []): array
    {
        $attributes = [];
        $conflicts = [];

        foreach ($incoming as $field => $value) {
            $existing = $current[$field] ?? null;

            if (in_array($field, $correctedFields, true)) {
                if (! self::valuesEqual($existing, $value)) {
                    $conflicts[] = $field;
                }

                continue;
            }

            if (self::isEmpty($value)) {
                continue;
            }

            $attributes[$field] = $value;
        }

        return ['attributes' => $attributes, 'conflicts' => $conflicts];
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private static function valuesEqual(mixed $a, mixed $b): bool
    {
        return (string) $a === (string) $b;
    }
}
