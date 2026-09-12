<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = ['order_id', 'invoice_number', 'invoice_date', 'official_invoice_url'];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
