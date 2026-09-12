<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Savv\Enums\Provider;
use Savv\Models\Concerns\HasPublicId;

class Consent extends Model
{
    use HasPublicId;

    protected $fillable = ['user_id', 'provider', 'consent_version', 'accepted_at', 'revoked_at', 'ip_address'];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
