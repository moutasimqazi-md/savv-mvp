<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Savv\Models\Concerns\HasPublicId;

class UserCorrection extends Model
{
    use HasPublicId;

    protected $fillable = ['user_id', 'correctable_type', 'correctable_id', 'field', 'previous_value', 'corrected_value', 'reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function correctable(): MorphTo
    {
        return $this->morphTo();
    }
}
