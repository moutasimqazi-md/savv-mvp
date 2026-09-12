<?php

namespace Savv\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Adds a public-facing ULID identifier separate from the internal numeric
 * primary key, so route/URL identifiers never leak sequential row counts.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
