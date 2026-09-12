<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Savv\Enums\NormalizedStatus;
use Savv\Models\Concerns\HasPublicId;

/**
 * Eloquent model for the `returns` table. Named OrderReturn because
 * "Return" is a reserved word in PHP and cannot be used as a class name.
 */
class OrderReturn extends Model
{
    use HasPublicId;

    protected $table = 'returns';

    protected $fillable = [
        'order_id', 'provider_return_id', 'original_status', 'normalized_status',
        'requested_at', 'approved_at', 'pickup_scheduled_at', 'picked_up_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'normalized_status' => NormalizedStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'pickup_scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'return_id');
    }
}
