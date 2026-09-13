<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Savv\Models\Concerns\HasPublicId;

class SubscriptionPreview extends Model
{
    use HasPublicId;

    protected $fillable = [
        'import_session_id', 'provider_subscription_key', 'selected', 'parser_version',
        'observed_at', 'normalized_payload', 'field_warnings',
    ];

    protected function casts(): array
    {
        return [
            'selected' => 'boolean',
            'observed_at' => 'datetime',
            'normalized_payload' => 'array',
            'field_warnings' => 'array',
        ];
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }
}
