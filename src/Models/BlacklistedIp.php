<?php

declare(strict_types=1);

namespace Watchtower\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Watchtower\Enums\BlockSource;

class BlacklistedIp extends Model
{
    use HasUlids;

    protected $table = 'blacklisted_ips';

    protected $fillable = [
        'ip',
        'reason',
        'source_env',
        'source',
        'expires_at',
        'blocked_by',
        'log_entry_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'source'     => BlockSource::class,
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
