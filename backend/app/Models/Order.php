<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Savv\Enums\NormalizedStatus;
use Savv\Enums\Provider;
use Savv\Models\Concerns\HasPublicId;

class Order extends Model
{
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'user_id', 'provider_connection_id', 'provider', 'provider_order_id', 'ordered_at',
        'original_status', 'normalized_status', 'currency', 'subtotal_minor', 'shipping_fee_minor',
        'discount_minor', 'tax_minor', 'total_minor', 'expected_delivery_at', 'delivered_at',
        'official_order_url', 'source_observed_at', 'last_import_batch_id', 'parser_version',
        'has_user_corrections',
    ];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'normalized_status' => NormalizedStatus::class,
            'ordered_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'source_observed_at' => 'datetime',
            'has_user_corrections' => 'boolean',
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function totalDecimal(): string
    {
        return number_format((int) $this->total_minor / 100, 2, '.', '');
    }
}
