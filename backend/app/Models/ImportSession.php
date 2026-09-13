<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Savv\Enums\ImportSessionStatus;
use Savv\Enums\Provider;
use Savv\Models\Concerns\HasPublicId;

class ImportSession extends Model
{
    use HasPublicId;

    protected $fillable = [
        'user_id', 'consent_id', 'provider', 'status', 'runner_process_ref', 'display_ref',
        'view_token_hash', 'view_token_expires_at', 'safe_error_code',
        'expires_at', 'last_activity_at', 'terminated_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'status' => ImportSessionStatus::class,
            'expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'terminated_at' => 'datetime',
            'view_token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }

    public function previews(): HasMany
    {
        return $this->hasMany(ImportPreview::class);
    }

    public function subscriptionPreviews(): HasMany
    {
        return $this->hasMany(SubscriptionPreview::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isInactive(int $inactivityMinutes): bool
    {
        return $this->last_activity_at !== null
            && $this->last_activity_at->addMinutes($inactivityMinutes)->isPast();
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }

    /**
     * Issue a new single-use, short-lived browser-view token. Returns the raw
     * token once; only its hash is persisted.
     */
    public function issueViewToken(int $ttlSeconds): string
    {
        $raw = Str::random(48);

        $this->forceFill([
            'view_token_hash' => hash('sha256', $raw),
            'view_token_expires_at' => now()->addSeconds($ttlSeconds),
        ])->save();

        return $raw;
    }

    public function verifyViewToken(string $raw): bool
    {
        if (! $this->view_token_hash || ! $this->view_token_expires_at || $this->view_token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->view_token_hash, hash('sha256', $raw));
    }
}
