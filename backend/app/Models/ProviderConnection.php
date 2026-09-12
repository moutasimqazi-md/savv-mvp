<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Savv\Enums\Provider;
use Savv\Enums\ProviderConnectionStatus;
use Savv\Models\Concerns\HasPublicId;

class ProviderConnection extends Model
{
    use HasPublicId;

    protected $fillable = [
        'user_id', 'provider', 'status', 'consent_id',
        'connected_at', 'disconnected_at', 'last_import_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'status' => ProviderConnectionStatus::class,
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_import_at' => 'datetime',
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

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === ProviderConnectionStatus::Active;
    }
}
