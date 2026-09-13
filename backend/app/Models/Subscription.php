<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Savv\Enums\BillingCycle;
use Savv\Enums\Provider;
use Savv\Enums\SubscriptionStatus;
use Savv\Models\Concerns\HasPublicId;

class Subscription extends Model
{
    use HasPublicId, SoftDeletes;

    protected $fillable = [
        'user_id', 'provider_connection_id', 'provider', 'provider_subscription_id', 'plan_name',
        'original_status', 'normalized_status', 'price_minor', 'currency', 'billing_cycle',
        'renewal_at', 'started_at', 'cancelled_at', 'official_billing_url', 'source_observed_at',
        'last_import_batch_id', 'parser_version',
    ];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'normalized_status' => SubscriptionStatus::class,
            'billing_cycle' => BillingCycle::class,
            'renewal_at' => 'datetime',
            'started_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'source_observed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class);
    }

    public function lastImportBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'last_import_batch_id');
    }

    public function priceDecimal(): ?string
    {
        return $this->price_minor === null ? null : number_format((int) $this->price_minor / 100, 2, '.', '');
    }
}
