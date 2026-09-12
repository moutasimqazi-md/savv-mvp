<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Savv\Models\Concerns\HasPublicId;

class OrderItem extends Model
{
    use HasPublicId;

    protected $fillable = [
        'order_id', 'provider_item_id', 'fingerprint', 'title', 'variant', 'quantity',
        'unit_price_minor', 'line_total_minor', 'product_image_url', 'official_product_url',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lineTotalDecimal(): string
    {
        return number_format((int) $this->line_total_minor / 100, 2, '.', '');
    }
}
