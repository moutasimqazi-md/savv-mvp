<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Savv\Enums\NormalizedStatus;

class ShipmentEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shipment_id', 'event_hash', 'occurred_at', 'original_status',
        'normalized_status', 'description', 'source', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'normalized_status' => NormalizedStatus::class,
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public static function hashFor(int $shipmentId, ?string $status, ?string $occurredAt, ?string $description): string
    {
        return hash('sha256', implode('|', [$shipmentId, $status, $occurredAt, $description]));
    }
}
