<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Savv\Enums\NormalizedStatus;
use Savv\Models\Concerns\HasPublicId;

class Refund extends Model
{
    use HasPublicId;

    protected $fillable = [
        'order_id', 'return_id', 'amount_minor', 'currency', 'original_status',
        'normalized_status', 'initiated_at', 'completed_at', 'masked_refund_method',
    ];

    protected function casts(): array
    {
        return [
            'normalized_status' => NormalizedStatus::class,
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function amountDecimal(): string
    {
        return number_format((int) $this->amount_minor / 100, 2, '.', '');
    }
}
