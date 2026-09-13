<?php

namespace Savv\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Savv\Models\Concerns\HasPublicId;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory, HasPublicId, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'retention_days'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function providerConnections(): HasMany
    {
        return $this->hasMany(ProviderConnection::class);
    }

    public function importSessions(): HasMany
    {
        return $this->hasMany(ImportSession::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function retentionDays(): int
    {
        return $this->retention_days ?? config('savv.retention.default_days');
    }
}
