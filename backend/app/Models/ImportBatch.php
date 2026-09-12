<?php

namespace Savv\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Savv\Enums\ImportBatchSource;
use Savv\Enums\ImportBatchStatus;
use Savv\Enums\Provider;
use Savv\Models\Concerns\HasPublicId;

class ImportBatch extends Model
{
    use HasPublicId;

    protected $fillable = [
        'user_id', 'import_session_id', 'provider_connection_id', 'provider', 'status', 'source',
        'parser_version', 'submitted_order_count', 'imported_order_count', 'failed_order_count',
        'error_summary', 'source_metadata', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => Provider::class,
            'status' => ImportBatchStatus::class,
            'source' => ImportBatchSource::class,
            'source_metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importSession(): BelongsTo
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'last_import_batch_id');
    }
}
