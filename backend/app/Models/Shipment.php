<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Savv\Enums\NormalizedStatus;
use Savv\Models\Concerns\HasPublicId;

class Shipment extends Model
{
    use HasPublicId;

    protected $fillable = [
        'order_id', 'carrier', 'tracking_number', 'original_status', 'normalized_status',
        'expected_delivery_at', 'delivered_at', 'last_observed_at',
    ];

    protected function casts(): array
    {
        return [
            'normalized_status' => NormalizedStatus::class,
            'expected_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_observed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at');
    }
}
